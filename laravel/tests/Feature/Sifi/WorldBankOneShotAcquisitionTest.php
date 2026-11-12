<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiIssueCandidate;
use App\Models\SifiSourceDraft;
use App\Models\User;
use App\Services\Sifi\Ai\UserAiSettings;
use App\Services\Sifi\ExecutiveBriefSource;
use App\Services\Sifi\SourceObservationPipeline;
use App\Services\Sifi\WorldBankGdpSourceAdapter;
use App\Services\Sifi\WorldBankOneShotAcquisition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

require_once dirname(__DIR__, 2).'/Support/Ae10MockWorldBankHarness.php';

final class WorldBankOneShotAcquisitionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private WorldBankOneShotAcquisition $service;

    private string $dir;

    private array $receipts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $root = getenv('R17_RESULT_DIR');
        if (! $root) {
            throw new \RuntimeException('Run with the isolated test runner and owned mock server');
        }$this->dir = $root.'/evidence/mock';
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->app->bind(WorldBankOneShotAcquisition::class, fn () => new \Ae10MockWorldBankHarness);
        $this->service = app(WorldBankOneShotAcquisition::class);
        $this->scenario('valid');
        if (is_file($this->service->permitPath())) {
            unlink($this->service->permitPath());
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->service->permitPath())) {
            unlink($this->service->permitPath());
        }
        foreach ($this->receipts as $id) {
            $p = storage_path('app/private/sifi-worldbank-acquisitions/'.$id.'.enc');
            if (is_file($p)) {
                unlink($p);
            }
        }
        parent::tearDown();
    }

    private function scenario(string $s): void
    {
        file_put_contents($this->dir.'/scenario.txt', $s);
    }

    private function calls(): int
    {
        return is_file($this->dir.'/requests.jsonl') ? count(file($this->dir.'/requests.jsonl')) : 0;
    }

    private function permit(array $overrides = []): void
    {
        $p = array_replace(['version' => 1, 'owner_id' => (int) $this->owner->id, 'consumed' => false, 'endpoint' => WorldBankGdpSourceAdapter::ENDPOINT, 'kind' => 'MOCK_TEST_ONLY',
            'max_records' => 3, 'max_bytes' => 65536, 'connect_seconds' => 2, 'total_seconds' => 5, 'retries' => 0, 'scheduler' => false, 'scope_sha256' => str_repeat('a', 64),
            'approval_ref' => 'AUTOMATED_MOCK_TEST:NOT_A_HUMAN_APPROVAL', 'expires_at' => now()->timestamp + 300], $overrides);
        file_put_contents($this->service->permitPath(), Crypt::encryptString(json_encode($p, JSON_THROW_ON_ERROR)));
    }

    private function fetch(string $scenario = 'valid'): array
    {
        $this->scenario($scenario);
        $this->permit();
        $p = $this->service->acquire($this->owner);
        if (isset($p['receipt_id'])) {
            $this->receipts[] = $p['receipt_id'];
        }

        return $p;
    }

    private function register(): SifiSourceDraft
    {
        $this->actingAs($this->owner)->post('/sifi/source-catalog/sources', ['name' => 'AE10 automated mock source', 'source_type' => 'OPEN_DATA', 'homepage_url' => WorldBankGdpSourceAdapter::DOCUMENTATION,
            'source_geography' => 'MOCK Indonesia schema', 'notes' => 'AUTOMATED SYNTHETIC HTTP MOCK. Not World Bank actual data.'])->assertRedirect();

        return SifiSourceDraft::where('user_id', $this->owner->id)->firstOrFail();
    }

    private function create(SifiSourceDraft $source, string $receipt): SifiIssueCandidate
    {
        $p = app(SourceObservationPipeline::class)->preview('wb_acquisition', $receipt, $this->owner->id);
        $response = $this->actingAs($this->owner)->withSession(['ae10_acquisition' => [$this->owner->id => $receipt]])->post('/sifi/source-catalog/pipeline-draft',
            ['scenario' => 'wb_acquisition', 'analyst_note' => 'AUTOMATED MOCK ACCEPTANCE. Tidak ada statistik resmi atau verifikasi manusia.', 'confirm_archive' => true,
                'source_draft_id' => $source->id, 'source_revision' => $source->revision, 'input_digest' => $p['packet_hash']]);
        $response->assertSessionHasNoErrors()->assertRedirect();
        $id = basename(dirname(parse_url($response->headers->get('Location'), PHP_URL_PATH)));

        return SifiIssueCandidate::findOrFail($id);
    }

    public function test_default_no_grant_and_unauthorized_endpoint_never_connect(): void
    {
        $before = $this->calls();
        $this->assertFalse($this->service->canAcquire($this->owner));
        $this->assertSame('ONE_GET_APPROVAL_REQUIRED', $this->service->acquire($this->owner)['reason']);
        foreach (['http://api.worldbank.org/x', 'https://api.worldbank.org.evil.test/', 'http://169.254.169.254/', 'https://api.worldbank.org/v2/other'] as $url) {
            $this->assertSame('UNAUTHORIZED_ENDPOINT', $this->service->acquire($this->owner, $url)['reason']);
        }
        $this->assertSame($before, $this->calls());
        Http::assertNothingSent();
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
    }

    public function test_expired_foreign_altered_grant_and_operational_mock_are_denied(): void
    {
        $before = $this->calls();
        foreach ([['expires_at' => now()->timestamp - 1], ['expires_at' => now()->timestamp + 1000], ['owner_id' => 9999], ['consumed' => true], ['endpoint' => 'http://localhost'], ['max_bytes' => 65537], ['retries' => 1], ['scheduler' => true]] as $bad) {
            $this->permit($bad);
            $this->assertSame('ONE_GET_APPROVAL_REQUIRED', $this->service->acquire($this->owner)['reason']);
        }
        file_put_contents($this->service->permitPath(), 'tampered');
        $this->assertSame('ONE_GET_APPROVAL_REQUIRED', $this->service->acquire($this->owner)['reason']);
        $this->permit();
        $this->app->instance('env', 'local');
        $this->assertSame('ONE_GET_APPROVAL_REQUIRED', $this->service->acquire($this->owner)['reason']);
        $this->app->instance('env', 'testing');
        $this->assertSame($before, $this->calls());
    }

    public function test_actual_loopback_http_bounded_fetch_is_mock_permanent_and_one_shot(): void
    {
        $before = $this->calls();
        $p = $this->fetch();
        $this->assertSame('READY_FOR_ANALYST_DRAFT', $p['status']);
        $this->assertSame('MOCK_ACQUISITION_ONLY', $p['mode']);
        $this->assertCount(3, $p['observations']);
        $this->assertSame($before + 1, $this->calls());
        $this->assertSame(1, $p['transport']['attempts']);
        $this->assertSame(0, $p['transport']['retry_count']);
        $this->assertLessThanOrEqual(65536, $p['transport']['max_buffered_bytes']);
        $this->assertSame(strlen(json_decode(Crypt::decryptString(file_get_contents(storage_path('app/private/sifi-worldbank-acquisitions/'.$p['receipt_id'].'.enc'))), true)['raw']), $p['transport']['bytes']);
        foreach ($p['observations'] as $o) {
            $this->assertTrue($o['provenance']['synthetic']);
            $this->assertStringContainsString('MOCK/SIMULASI', $o['description']);
            $this->assertNull($o['issued_at']);
        }
        $this->assertSame('ONE_GET_APPROVAL_REQUIRED', $this->service->acquire($this->owner)['reason']);
        $this->assertSame($before + 1, $this->calls());
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
        Http::assertNothingSent();
    }

    public function test_redirect_content_type_encoding_http_and_rate_limit_fail_without_retry(): void
    {
        foreach (['redirect' => 'REDIRECT_BLOCKED', 'html' => 'CONTENT_TYPE_NOT_SUPPORTED', 'encoding' => 'CONTENT_ENCODING_NOT_SUPPORTED', 'http_error' => 'HTTP_FAILURE_NO_RETRY', 'rate' => 'RATE_LIMITED_NO_RETRY'] as $s => $reason) {
            $before = $this->calls();
            $p = $this->fetch($s);
            $this->assertSame($reason, $p['reason'], $s);
            $this->assertSame($before + 1, $this->calls());
            $this->assertSame(1, $p['transport']['attempts']);
            $this->assertSame('ONE_GET_APPROVAL_REQUIRED', $this->service->acquire($this->owner)['reason']);
        }
        $log = file_get_contents($this->dir.'/requests.jsonl');
        $this->assertStringNotContainsString('forbidden-redirect-target', $log);
    }

    public function test_content_length_and_chunked_oversize_abort_before_exceeding_sink_cap(): void
    {
        foreach (['oversize_length', 'oversize_chunked'] as $s) {
            $before = $this->calls();
            $p = $this->fetch($s);
            $this->assertSame('RESPONSE_TOO_LARGE', $p['reason']);
            $this->assertLessThanOrEqual(65536, $p['transport']['max_buffered_bytes']);
            $this->assertSame($before + 1, $this->calls());
        }
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
    }

    public function test_header_and_body_timeout_have_total_deadline_and_no_retry(): void
    {
        foreach (['slow_headers', 'slow_body'] as $s) {
            $before = $this->calls();
            $p = $this->fetch($s);
            $this->assertSame('TIMEOUT_NO_RETRY', $p['reason'], $s);
            $this->assertGreaterThanOrEqual(4500, $p['transport']['elapsed_ms']);
            $this->assertLessThan(6500, $p['transport']['elapsed_ms']);
            $this->assertSame($before + 1, $this->calls());
        }
    }

    public function test_network_denial_consumes_attempt_without_synthetic_fallback(): void
    {
        $before = $this->calls();
        $p = $this->fetch('disconnect');
        $this->assertSame($before + 1, $this->calls());
        $this->assertSame('NETWORK_DENIED_OR_UNAVAILABLE', $p['reason']);
        $this->assertCount(0, $p['observations']);
        $this->permit();
        $native = new WorldBankOneShotAcquisition;
        $this->assertSame('MOCK_TRANSPORT_REQUIRED', $native->acquire($this->owner)['reason']);
        Http::assertNothingSent();
    }

    public function test_decoder_rejects_malformed_schema_metadata_recordcount_and_stale_update(): void
    {
        foreach (['malformed' => 'MALFORMED_JSON', 'schema' => 'RECORD_SCHEMA_DRIFT', 'missing' => 'SOURCE_UPDATE_METADATA_INVALID', 'too_many' => 'INCOMPLETE_OR_UNSUPPORTED_PAGINATION', 'stale' => 'SOURCE_UPDATE_REVIEW_REQUIRED'] as $s => $reason) {
            $p = $this->fetch($s);
            $this->assertSame('BLOCKED', $p['status']);
            $this->assertSame($reason, $p['reason']);
        }
    }

    public function test_unverified_rights_are_blocked_before_any_connection_or_consumption(): void
    {
        $file = resource_path('sifi/ae09-wb-rights.json');
        $old = file_get_contents($file);
        $rights = json_decode($old, true);
        $rights['commercial_reuse'] = false;
        $before = $this->calls();
        $this->permit();
        try {
            file_put_contents($file, json_encode($rights));
            $p = $this->service->acquire($this->owner);
            $this->assertSame('RIGHTS_NOT_SATISFIED', $p['reason']);
            $this->assertSame($before, $this->calls());
        } finally {
            file_put_contents($file, $old);
        }
        $this->assertTrue($this->service->canAcquire($this->owner));
    }

    public function test_receipt_is_owner_bound_authenticated_and_not_user_supplied_raw(): void
    {
        $p = $this->fetch();
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->assertSame('NO_OWNED_ACQUISITION', $this->service->load($other, $p['receipt_id'])['reason']);
        $path = storage_path('app/private/sifi-worldbank-acquisitions/'.$p['receipt_id'].'.enc');
        file_put_contents($path, 'tamper');
        $this->assertSame('ACQUISITION_RECEIPT_INVALID', $this->service->load($this->owner, $p['receipt_id'])['reason']);
        $this->assertSame('NO_OWNED_ACQUISITION', $this->service->load($this->owner, '../../.env')['reason']);
    }

    public function test_auth_csrf_and_payload_override_are_rejected(): void
    {
        $this->post('/sifi/source-catalog/acquisition-preview', ['confirm_one_shot' => true])->assertRedirect('/login');
        $this->actingAs($this->owner)->post('/sifi/source-catalog/acquisition-preview', ['confirm_one_shot' => true, 'endpoint' => 'http://169.254.169.254'])->assertSessionHasErrors('endpoint');
        $this->app->instance('env', 'local');
        $this->post('/sifi/source-catalog/acquisition-preview', ['confirm_one_shot' => true])->assertStatus(419);
        $this->app->instance('env', 'testing');
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($unverified)->post('/sifi/source-catalog/acquisition-preview', ['confirm_one_shot' => true])->assertRedirect('/email/verify');
    }

    public function test_route_preview_writes_only_private_receipt_and_session_not_domain(): void
    {
        $before = DB::table('sifi_source_drafts')->count();
        $this->permit();
        $this->actingAs($this->owner)->post('/sifi/source-catalog/acquisition-preview', ['confirm_one_shot' => true])->assertSessionHasNoErrors()->assertRedirect('/sifi/source-catalog?scenario=wb_acquisition');
        $id = session('ae10_acquisition.'.$this->owner->id);
        $this->receipts[] = $id;
        $this->assertTrue(Str::isUuid($id));
        $this->assertDatabaseCount('sifi_source_drafts', $before);
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
        $this->assertFalse($this->service->canAcquire($this->owner));
    }

    public function test_mock_fetch_duplicate_revision_history_and_edits_use_existing_transaction(): void
    {
        $s = $this->register();
        $one = $this->fetch();
        $c = $this->create($s, $one['receipt_id']);
        $c->title = '[MOCK] User preserved edit';
        $c->revision++;
        $c->save();
        $two = $this->fetch();
        $same = $this->create($s, $two['receipt_id']);
        $this->assertSame($c->id, $same->id);
        $this->assertSame('[MOCK] User preserved edit', $same->title);
        $p = app(SourceObservationPipeline::class)->preview('wb_acquisition', $two['receipt_id'], $this->owner->id);
        $this->assertSame(3, $p['history']['already_seen']);
        $this->travelTo(Carbon::parse('2026-10-26T12:00:00Z'));
        $three = $this->fetch('revised');
        $p = app(SourceObservationPipeline::class)->preview('wb_acquisition', $three['receipt_id'], $this->owner->id);
        $this->assertSame(1, $p['history']['updated']);
        $this->assertSame(2, $p['history']['already_seen']);
        $c2 = $this->create($s, $three['receipt_id']);
        $this->assertNotSame($c->id, $c2->id);
        $this->assertSame('[MOCK] User preserved edit', $c->fresh()->title);
        $this->travelBack();
        $this->assertDatabaseCount('sifi_issue_candidates', 2);
        $this->assertDatabaseCount('sifi_evidence_drafts', 6);
    }

    public function test_same_version_revision_conflict_and_expired_receipt_fail_closed(): void
    {
        $s = $this->register();
        $one = $this->fetch();
        $this->create($s, $one['receipt_id']);
        $two = $this->fetch('same_version_conflict');
        $p = app(SourceObservationPipeline::class)->preview('wb_acquisition', $two['receipt_id'], $this->owner->id);
        $this->assertSame('SOURCE_REVISION_CONFLICT', $p['reason']);
        $this->travel(31)->days();
        $p = $this->service->load($this->owner, $one['receipt_id']);
        $this->assertSame('BLOCKED', $p['status']);
        $this->travelBack();
    }

    public function test_concurrent_attempts_share_one_locked_consumption_and_one_http_request(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl required for cross-process concurrency proof');
        }
        $this->permit();
        $before = $this->calls();
        $path = $this->dir.'/concurrent-'.Str::uuid().'.json';
        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid);
        if ($pid === 0) {
            try {
                $p = $this->service->acquire($this->owner);
                file_put_contents($path, json_encode(['status' => $p['status'], 'receipt' => $p['receipt_id'] ?? null]));
                exit(0);
            } catch (\Throwable) {
                file_put_contents($path, json_encode(['status' => 'CHILD_ERROR']));
                exit(1);
            }
        }
        $parent = $this->service->acquire($this->owner);
        pcntl_waitpid($pid, $status);
        $child = json_decode(file_get_contents($path), true);
        if (isset($parent['receipt_id'])) {
            $this->receipts[] = $parent['receipt_id'];
        }if (! empty($child['receipt'])) {
            $this->receipts[] = $child['receipt'];
        }
        $results = [$parent['status'], $child['status']];
        sort($results);
        $this->assertSame(['BLOCKED', 'READY_FOR_ANALYST_DRAFT'], $results);
        $this->assertSame(0, pcntl_wexitstatus($status));
        $this->assertSame($before + 1, $this->calls());
    }

    public function test_exception_logging_does_not_include_payload_secret_or_permission(): void
    {
        Log::spy();
        $this->permit();
        $transport = new class extends \Ae10MockWorldBankHarness
        {
            protected function request(string $endpoint, array $options): Response
            {
                throw new \RuntimeException('SECRET_AE10_CANARY must never appear in an acquisition audit');
            }
        };
        $p = $transport->acquire($this->owner);
        $this->assertSame('NETWORK_DENIED_OR_UNAVAILABLE', $p['reason']);
        Log::shouldHaveReceived('info')->once()->withArgs(function ($name, $data) {
            return $name === 'sifi_source_acquisition' && ! str_contains(json_encode($data), 'SECRET_AE10_CANARY') && ! isset($data['raw']) && ! isset($data['approval_ref']);
        });
    }

    public function test_native_throttle_blocks_sixth_request_before_transport(): void
    {
        $before = $this->calls();
        $this->actingAs($this->owner);
        for ($i = 0; $i < 5; $i++) {
            $this->permit();
            $this->post('/sifi/source-catalog/acquisition-preview', ['confirm_one_shot' => true])->assertSessionHasNoErrors()->assertRedirect();
            $this->receipts[] = session('ae10_acquisition.'.$this->owner->id);
        }
        $this->permit();
        $this->post('/sifi/source-catalog/acquisition-preview', ['confirm_one_shot' => true])->assertStatus(429);
        $this->assertSame($before + 5, $this->calls());
        $this->assertTrue($this->service->canAcquire($this->owner));
    }

    public function test_mock_http_to_brief_is_traceable_permanently_synthetic_and_ai_off(): void
    {
        $s = $this->register();
        $p = $this->fetch();
        $c = $this->create($s, $p['receipt_id']);
        $pipeline = app(SourceObservationPipeline::class);
        $lineage = $pipeline->lineage($c);
        $this->assertSame('MOCK_ACQUISITION_ONLY', $lineage['mode']);
        $this->assertSame('AE10_MOCK_NOT_AQ01_CUSTODY', $lineage['reference_namespace']);
        $this->assertCount(3, $lineage['records']);
        $this->assertSame('HISTORICAL_PERIOD_NOT_CURRENT', $lineage['source']['observation_currency']);
        $this->assertNull($lineage['source']['record_publication_at']);
        foreach ($lineage['records'] as $row) {
            $this->assertTrue($row['observation']['provenance']['synthetic']);
            $this->assertCount(1, $row['draft_links']);
        }
        $service = app(ExecutiveBriefSource::class);
        [$case,$tr,$fs] = $service->parents($c, $this->owner->id);
        $src = $service->assemble($c, $case, $tr, $fs);
        $this->assertSame('MOCK_ACQUISITION_ONLY', $src['data_mode']);
        $url = '/sifi/issue-candidates/'.$c->id.'/executive-brief';
        $this->actingAs($this->owner)->post($url, ['candidate_revision' => 1, 'case_revision' => 1, 'trajectory_revision' => 1, 'foresight_revision' => 1, 'input_digest' => $service->digest($src),
            'title' => '[MOCK/SIMULASI] One-shot source brief', 'audience' => 'Automated isolated acceptance', 'executive_summary' => $src['trajectory']['direction'],
            'decision_questions' => 'Apa yang harus diperiksa sebelum memakai angka nyata?', 'verification_priorities' => 'Periksa izin sumber, periode dan publikasi yang tidak tersedia.',
            'limitations' => $lineage['source']['limitation'], 'review_state' => 'DRAFT'])->assertSessionHasNoErrors()->assertRedirect();
        $download = $this->get($url.'/download')->assertOk();
        foreach (['MOCK/SYNTHETIC ACQUISITION', 'LOCAL_MOCK_HTTP_TEST_ONLY', 'MOCK/SIMULASI', 'raw_record', 'wire_sha256', 'NOT_VERIFIED', 'record_publication_at'] as $text) {
            $this->assertStringContainsString($text, $download->getContent());
        }
        $this->assertStringNotContainsString('REAL PUBLIC ARCHIVE', $download->getContent());
        $this->assertStringNotContainsString('BMKG sebagai rujukan format', $download->getContent());
        $path = storage_path('app/private/sifi-worldbank-acquisitions/'.$p['receipt_id'].'.enc');
        unlink($path);
        $this->assertEquals($lineage, $pipeline->lineage($c));
        $this->assertDatabaseCount('sifi_event_hypotheses', 0);
        $this->assertDatabaseCount('sifi_evidence_review_notes', 0);
        $this->assertFalse(app(UserAiSettings::class)->read($this->owner->id)['enabled']);
        Http::assertNothingSent();
        $dir = getenv('R17_RESULT_DIR');
        if ($dir) {
            file_put_contents($dir.'/evidence/MOCK_SOURCE_TO_BRIEF.md', $download->getContent());
            file_put_contents($dir.'/evidence/MOCK_SOURCE_TO_BRIEF_TRACE.json', json_encode($lineage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }
}
