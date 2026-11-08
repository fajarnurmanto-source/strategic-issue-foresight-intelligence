<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\BlueskySearchTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SocialWatchTickTest extends TestCase
{
    use RefreshDatabase;

    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-09 12:00:00', 'Asia/Jakarta'));
        $this->temporary = sys_get_temp_dir().'/sifi-autowatch-'.Str::uuid();
        mkdir($this->temporary, 0700);
        $this->app->useStoragePath($this->temporary);
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'sifi_ih03.shadow_ingest_enabled' => true,
            'sifi_social.bluesky_public_search_enabled' => true,
            'sifi_social.auto_watch_enabled' => false,
            'sifi_social.contact_email' => 'maintainer@example.org',
            'sifi_social.max_requests_per_day' => 3,
            'sifi_social.min_interval_seconds' => 0,
        ]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if (str_starts_with($this->temporary, sys_get_temp_dir().'/sifi-autowatch-')) {
            (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($this->temporary);
        }
        parent::tearDown();
    }

    public function test_autowatch_is_opted_out_without_a_provider_request(): void
    {
        $this->assertSame(0, Artisan::call('sifi:social-watch:tick'));
        $this->assertStringContainsString('OFF_BY_DEFAULT', Artisan::output());
        $this->assertDatabaseCount('sifi_research_sessions', 0);
        Http::assertNothingSent();
    }

    public function test_enabled_tick_rotates_objects_even_when_first_query_is_empty(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        config([
            'sifi_social.auto_watch_enabled' => true,
            'sifi_social.auto_watch_owner_id' => $owner->id,
        ]);
        $first = $this->makeObject($owner, '00000000-0000-4000-8000-000000000001', 'sampah');
        $second = $this->makeObject($owner, '00000000-0000-4000-8000-000000000002', 'banjir');

        $fake = new class extends BlueskySearchTransport {
            public array $queries = [];

            public function fetch(string $query, string $contact): array
            {
                $this->queries[] = $query;

                return [
                    'state' => 'OK',
                    'network_requests' => 1,
                    'http_status' => 200,
                    'response_bytes' => 24,
                    'response_sha256' => str_repeat('b', 64),
                    'elapsed_ms' => 1,
                    'entries' => [],
                ];
            }
        };
        app()->instance(BlueskySearchTransport::class, $fake);

        $this->assertSame(0, Artisan::call('sifi:social-watch:tick'));
        $this->assertSame(0, Artisan::call('sifi:social-watch:tick'));
        $this->assertSame(['sampah', 'banjir'], $fake->queries);

        $scanned = DB::table('sifi_research_session_audit as a')
            ->join('sifi_research_sessions as s', 's.id', '=', 'a.session_id')
            ->where('a.action', 'SIFI_SOCIAL_BLUESKY_METADATA_RECEIPT_SAVED')
            ->orderBy('a.id')
            ->pluck('s.object_id')
            ->all();
        $this->assertSame([$first->id, $second->id], $scanned);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
        Http::assertNothingSent();
    }

    private function makeObject(User $owner, string $id, string $keyword): SifiMonitoringObject
    {
        return SifiMonitoringObject::create([
            'id' => $id,
            'user_id' => $owner->id,
            'name' => '[SYNTHETIC] '.$keyword,
            'description' => 'Testing-only social watch',
            'category' => 'Research',
            'objective' => 'Monitor public signals',
            'horizon' => '90 hari',
            'geography' => null,
            'aliases_json' => [],
            'keywords_json' => [$keyword],
            'exclusions_json' => [],
            'state' => 'CONFIGURED',
            'revision' => 1,
        ]);
    }
}
