<?php

namespace Tests\Feature\Sifi;

use App\Services\Sifi\OneShotSearchAuthority;
use App\Services\Sifi\OneShotSearchTransport;
use App\Services\Sifi\SearchProviderRegistry;
use GuzzleHttp\Psr7\Response;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Native transport contract uses an overridden in-process wire, never DNS/socket. */
final class RealSearchAuthorityTest extends TestCase
{
    private string $temporary;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-04 12:00:00', 'UTC'));
        $this->temporary = sys_get_temp_dir().'/sifi-search-authority-fixture-'.Str::uuid();
        mkdir($this->temporary, 0700);
        $this->app->useStoragePath($this->temporary);
        config(['sifi.real_search_live' => true]);
        Http::preventStrayRequests();
        $this->context = ['mode' => 'LIVE', 'query' => 'SYNTHETIC kendaraan listrik', 'query_sha256' => hash('sha256', 'SYNTHETIC kendaraan listrik'), 'binding' => ['owner_id' => 999999, 'object_id' => (string) Str::uuid(), 'session_id' => (string) Str::uuid(), 'object_revision' => 1, 'session_revision' => 1, 'plan_sha256' => str_repeat('a', 64)], 'limit' => 5];
    }

    protected function tearDown(): void
    {
        if (str_starts_with($this->temporary, sys_get_temp_dir().'/sifi-search-authority-fixture-')) {
            (new Filesystem)->deleteDirectory($this->temporary);
        }parent::tearDown();
    }

    private function permit(array $changes = []): string
    {
        $path = app(OneShotSearchAuthority::class)->path('CROSSREF', $this->context);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }file_put_contents($path, Crypt::encryptString(json_encode(array_replace(['version' => 'sifi-search-authority-1', 'context_hash' => app(OneShotSearchAuthority::class)->contextHash('CROSSREF', $this->context), 'endpoint' => app(SearchProviderRegistry::class)->get('CROSSREF')['endpoint'], 'method' => 'GET', 'cost_limit' => 0, 'request_limit' => 1, 'response_byte_limit' => 32768, 'consumed' => false, 'expires_at' => now()->addMinutes(5)->timestamp, 'approval_scope_sha256' => str_repeat('c', 64), 'nonce' => str_repeat('d', 64), 'contact_email' => 'synthetic@example.org'], $changes), JSON_THROW_ON_ERROR)));

        return $path;
    }

    private function wire(int $status = 200, ?string $body = null, array $headers = []): OneShotSearchTransport
    {
        return new class($status, $body ?? json_encode(['message' => ['items' => [['DOI' => '10.99999/synthetic-test', 'title' => ['SYNTHETIC test'], 'publisher' => 'SYNTHETIC publisher', 'abstract' => 'MUST_NOT_RETAIN', 'author' => [['email' => 'private@example.test']]]]]]), $headers) extends OneShotSearchTransport
        {
            public int $calls = 0;

            public int $dns = 0;

            public array $options = [];

            public function __construct(private int $status, private string $body, private array $headers) {}

            protected function address(string $host): string
            {
                $this->dns++;

                return '8.8.8.8';
            }

            protected function request(string $endpoint, array $options): \Illuminate\Http\Client\Response
            {
                $this->calls++;
                $this->options = $options;
                $r = new Response($this->status, $this->headers + ['Content-Type' => 'application/json'], $this->body);
                $options['on_headers']($r);
                $options['sink']->write($this->body);

                return new \Illuminate\Http\Client\Response($r);
            }
        };
    }

    public function test_missing_and_mismatched_authority_never_resolve_or_dispatch(): void
    {
        $wire = $this->wire();
        try {
            $wire->get('CROSSREF', $this->context);
            $this->fail('Missing grant accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame('SEARCH_AUTHORITY_MISSING', $e->getMessage());
        }$this->assertSame(0, $wire->dns);
        $this->assertSame(0, $wire->calls);
        $this->permit(['context_hash' => str_repeat('b', 64)]);
        try {
            $wire->get('CROSSREF', $this->context);
            $this->fail('Wrong grant accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame('SEARCH_AUTHORITY_INVALID', $e->getMessage());
        }$this->assertSame(0, $wire->dns);
        Http::assertNothingSent();
    }

    public function test_one_shot_reserves_before_dispatch_and_persists_filtered_encrypted_receipt(): void
    {
        $path = $this->permit();
        $wire = $this->wire();
        $packet = $wire->get('CROSSREF', $this->context);
        $this->assertSame('OK', $packet['state']);
        $this->assertSame(1, $wire->calls);
        $this->assertSame(1, $wire->dns);
        $this->assertTrue(json_decode(Crypt::decryptString(file_get_contents($path)), true)['consumed']);
        $this->assertNotNull($packet['retrieved_at']);
        $this->assertNull($packet['fixture_loaded_at']);
        $this->assertSame(0, $packet['retry_count']);
        $this->assertFalse($wire->options['allow_redirects']);
        $this->assertTrue($wire->options['verify']);
        $this->assertSame(2, $wire->options['_curl_retries']);
        $this->assertSame(['api.crossref.org:443:8.8.8.8'], $wire->options['curl'][CURLOPT_RESOLVE]);
        $file = storage_path('app/private/sifi-search-runtime/'.app(OneShotSearchAuthority::class)->contextHash('CROSSREF', $this->context).'.receipt.enc');
        $raw = file_get_contents($file);
        $this->assertStringNotContainsString('SYNTHETIC test', $raw);
        $receipt = Crypt::decryptString($raw);
        $this->assertStringNotContainsString('MUST_NOT_RETAIN', $receipt);
        $this->assertStringNotContainsString('private@example.test', $receipt);
        $this->assertSame($packet['response_sha256'], json_decode($receipt, true)['response_sha256']);
        Http::assertNothingSent();
    }

    public function test_failure_consumes_authority_and_retry_does_not_dispatch(): void
    {
        $this->permit();
        $wire = $this->wire(429, '{}', ['Retry-After' => '60']);
        $packet = $wire->get('CROSSREF', $this->context);
        $this->assertSame('QUOTA', $packet['state']);
        $this->assertSame(60, $packet['retry_after_seconds']);
        try {
            $wire->get('CROSSREF', $this->context);
            $this->fail('Retry accepted');
        } catch (\RuntimeException $e) {
            $this->assertContains($e->getMessage(), ['LOCAL_RATE_LIMIT', 'SEARCH_AUTHORITY_INVALID']);
        }$this->assertSame(1, $wire->calls);
    }

    public function test_redirect_and_oversize_fail_closed_with_failure_receipt(): void
    {
        $this->permit();
        $wire = $this->wire(302, '', ['Location' => 'https://127.0.0.1/private']);
        $p = $wire->get('CROSSREF', $this->context);
        $this->assertSame('REDIRECT_BLOCKED', $p['state']);
        $this->assertNull($p['body']);
        $this->assertSame(0, $p['redirects_followed']);
        $this->context['binding']['session_id'] = (string) Str::uuid();
        $this->permit();
        file_put_contents(storage_path('app/private/sifi-search-runtime/CROSSREF.lock'), '0');
        $wire = $this->wire(200, str_repeat('x', 32769));
        $p = $wire->get('CROSSREF', $this->context);
        $this->assertSame('BYTE_LIMIT', $p['state']);
        $this->assertNull($p['body']);
        $this->assertSame(1, $wire->calls);
    }

    public function test_consumed_expired_or_nonzero_cost_permission_is_rejected(): void
    {
        foreach ([['consumed' => true], ['expires_at' => now()->subSecond()->timestamp], ['cost_limit' => 1], ['request_limit' => 2], ['contact_email' => 'synthetic@example.invalid']] as $changes) {
            $this->permit($changes);
            try {
                app(OneShotSearchAuthority::class)->consume('CROSSREF', $this->context);
                $this->fail('Invalid permission accepted');
            } catch (\RuntimeException) {
                $this->assertTrue(true);
            }
        }Http::assertNothingSent();
    }

    public function test_provider_review_expiry_denies_authority_before_dns_or_dispatch(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 12:00:00', 'UTC'));
        $this->permit();
        $wire = $this->wire();
        try {
            $wire->get('CROSSREF', $this->context);
            $this->fail('Expired provider review accepted');
        } catch (\RuntimeException $error) {
            $this->assertSame('PROVIDER_REVIEW_EXPIRED', $error->getMessage());
        }
        $this->assertSame(0, $wire->dns);
        $this->assertSame(0, $wire->calls);
        Http::assertNothingSent();
    }

    public function test_default_off_precedes_storage_dns_and_http(): void
    {
        config(['sifi.real_search_live' => false]);
        $wire = $this->wire();
        try {
            $wire->get('CROSSREF', $this->context);
            $this->fail('Disabled search accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame('LIVE_SEARCH_LOCKED', $e->getMessage());
        }$this->assertSame(0, $wire->dns);
        $this->assertSame(0, $wire->calls);
        $this->assertDirectoryDoesNotExist(storage_path('app/private/sifi-search-runtime'));
    }

    public function test_receipt_recovery_is_read_only_bound_and_expires(): void
    {
        $this->permit();
        $wire = $this->wire();
        $packet = $wire->get('CROSSREF', $this->context);
        $this->assertSame($packet, $wire->recover('CROSSREF', $this->context));
        $this->assertSame(1, $wire->calls);
        $changed = $this->context;
        $changed['binding']['owner_id'] = 1;
        try {
            $wire->recover('CROSSREF', $changed);
            $this->fail('Foreign recovery accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame('RECEIPT_MISSING', $e->getMessage());
        }
        $this->travel(31)->days();
        try {
            $wire->recover('CROSSREF', $this->context);
            $this->fail('Expired recovery accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame('RECEIPT_CONTEXT_OR_EXPIRY', $e->getMessage());
        }$this->assertSame(1, $wire->calls);
    }
}
