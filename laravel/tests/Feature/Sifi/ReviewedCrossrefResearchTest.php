<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\ReviewedCrossrefResearch;
use App\Services\Sifi\OneShotSearchTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReviewedCrossrefResearchTest extends TestCase
{
    use RefreshDatabase;

    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-08 12:00:00', 'Asia/Jakarta'));
        $this->temporary = sys_get_temp_dir().'/sifi-ih03b-review-'.Str::uuid();
        mkdir($this->temporary, 0700);
        $this->app->useStoragePath($this->temporary);
        config([
            'sifi_ih03b.crossref_metadata_enabled' => true,
            'sifi_ih03.shadow_ingest_enabled' => true,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if (str_starts_with($this->temporary, sys_get_temp_dir().'/sifi-ih03b-review-')) {
            (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($this->temporary);
        }
        parent::tearDown();
    }

    public function test_one_real_metadata_receipt_reuses_existing_research_storage_without_http_or_evidence(): void
    {
        $wire = new class extends OneShotSearchTransport {
            public int $calls = 0;
            protected function address(string $host): string { return '8.8.8.8'; }
            protected function request(string $endpoint, array $options): \Illuminate\Http\Client\Response
            {
                $this->calls++;
                $body = json_encode(['message' => ['items' => [[
                    'DOI' => '10.99999/sifi-ih03b',
                    'title' => ['SIFI synthetic wire response'],
                    'publisher' => 'Fixture Publisher',
                    'type' => 'journal-article',
                    'published' => ['date-parts' => [[2025, 7, 1]]],
                ]]]], JSON_THROW_ON_ERROR);
                $response = new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], $body);
                $options['on_headers']($response);
                $options['sink']->write($body);
                return new \Illuminate\Http\Client\Response($response);
            }
        };
        app()->instance(OneShotSearchTransport::class, $wire);

        $owner = User::factory()->create(['email_verified_at' => now()]);
        $object = SifiMonitoringObject::create([
            'id' => (string) Str::uuid(), 'user_id' => $owner->id,
            'name' => '[SYNTHETIC] Renewable energy',
            'description' => 'Disposable integration test',
            'category' => 'Research', 'objective' => 'Review metadata',
            'horizon' => '90 hari', 'geography' => null,
            'aliases_json' => [], 'keywords_json' => ['renewable energy'],
            'exclusions_json' => [], 'state' => 'CONFIGURED', 'revision' => 1,
        ]);
        $sessionId = app(ReviewedCrossrefResearch::class)->collect($owner, $object, [
            'question' => 'What research reports renewable energy?',
            'object_revision' => 1,
            'submission_key' => (string) Str::uuid(),
            'contact_email' => 'maintainer@example.org',
        ]);
        $row = DB::table('sifi_research_sessions')->where('id', $sessionId)->firstOrFail();
        $envelope = json_decode($row->results_json, true, 32, JSON_THROW_ON_ERROR)['multi_source']['envelope'];
        $this->assertSame(1, $wire->calls);
        $this->assertSame('APPROVED_LIVE_CORPUS_METADATA', $envelope['mode']);
        $this->assertSame(1, $envelope['usage']['network_requests']);
        $this->assertCount(1, $envelope['entries']);
        $this->assertSame('sifi.research.insights.v1', $envelope['insights']['schema_version']);
        $this->assertSame('NONE', $envelope['entries'][0]['evidence_admission']);
        $this->assertDatabaseCount('sifi_connector_observations', 1);
        $this->assertDatabaseCount('sifi_real_source_signals', 0);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);

        $observations = app(\App\Services\Sifi\CollectorObservations::class)->forObject($owner, $object);
        $this->assertCount(1, $observations);
        $this->assertSame(['CROSSREF'], $observations[0]['provider_ids']);
        $this->assertSame('SCHOLARLY_BIBLIOGRAPHIC_METADATA', $observations[0]['source_kind']);
        $this->assertSame('SIFI synthetic wire response', $observations[0]['entries'][0]['title']);
        $this->assertSame('NONE', $observations[0]['entries'][0]['evidence_admission']);
        $this->assertSame('NOT_VERIFIED', $observations[0]['independent_origin_state']);
        $this->assertNotNull($observations[0]['envelope_sha256']);
        Http::assertNothingSent();
    }

    public function test_preview_bootstrap_is_idempotent_and_never_dispatches(): void
    {
        $this->artisan('sifi:ih03b-crossref', ['--bootstrap-preview' => true])
            ->expectsOutputToContain('IH03B_PREVIEW_OWNER_ID=')
            ->assertExitCode(0);
        $this->artisan('sifi:ih03b-crossref', ['--bootstrap-preview' => true])
            ->expectsOutputToContain('IH03B_PREVIEW_OWNER_ID=')
            ->assertExitCode(0);
        $this->assertSame(1, User::query()->where('email', 'sifi-ih03b-synthetic@preview.invalid')->count());
        $this->assertSame(1, SifiMonitoringObject::query()->where('name', '[LOCAL PREVIEW] Crossref metadata research')->count());
        Http::assertNothingSent();
    }
}
