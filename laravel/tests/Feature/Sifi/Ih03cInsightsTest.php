<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\ResearchSessionWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Ih03cInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_isolated_owner_receipt_is_read_without_new_network_or_database_writes(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $object = SifiMonitoringObject::create([
            'id' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'name' => '[SYNTHETIC] Metadata monitoring',
            'description' => 'Isolated test object',
            'category' => 'Research',
            'objective' => 'Review public bibliographic metadata',
            'horizon' => '90 hari',
            'geography' => null,
            'aliases_json' => [],
            'keywords_json' => ['misinformation'],
            'exclusions_json' => [],
            'state' => 'CONFIGURED',
            'revision' => 1,
        ]);
        $id = app(ResearchSessionWorkspace::class)->create($owner, $object, [
            'question' => 'misinformation',
            'object_revision' => 1,
            'query_limit' => 1,
            'result_limit' => 5,
        ]);
        $url = 'https://doi.org/10.1000/ih03c';
        $receipt = [
            'mode' => 'APPROVED_LIVE_CORPUS_METADATA',
            'calls' => [['provider' => 'CROSSREF', 'state' => 'OK', 'network_requests' => 1]],
            'entries' => [[
                'id' => hash('sha256', $url),
                'canonical_url' => $url,
                'title' => 'Misinformation in digital media',
                'published_at' => '2025',
                'publication_precision' => 'YEAR',
                'rights_state' => 'BIBLIOGRAPHIC_METADATA_REUSE_NO_ABSTRACT',
                'evidence_admission' => 'NONE',
                'lineage' => [['provider' => 'CROSSREF']],
                'relevance' => ['score' => 3],
            ]],
        ];
        $results = json_decode(DB::table('sifi_research_sessions')->where('id', $id)->value('results_json'), true);
        $results['multi_source'] = ['envelope' => $receipt];
        DB::table('sifi_research_sessions')->where('id', $id)
            ->update(['results_json' => json_encode($results, JSON_THROW_ON_ERROR)]);

        $before = DB::table('sifi_research_sessions')->where('id', $id)->value('results_json');
        $this->artisan('sifi:ih03c-insights', ['--owner' => (string) $owner->id, '--session' => $id])
            ->expectsOutputToContain('sifi.ih03c.shadow-signal-intelligence.v1')
            ->assertExitCode(0);
        $this->assertSame($before, DB::table('sifi_research_sessions')->where('id', $id)->value('results_json'));
        $this->assertDatabaseCount('sifi_connector_observations', 0);
    }

    public function test_unapproved_database_path_denied_without_changing_active_connection(): void
    {
        $previous = config('database.connections.sqlite.database');
        try {
            config(['database.connections.sqlite.database' => '/tmp/ih03c-unapproved.sqlite']);
            $this->artisan('sifi:ih03c-insights', ['--owner' => '1', '--session' => (string) Str::uuid()])
                ->expectsOutputToContain('IH03C_ISOLATED_SQLITE_REQUIRED')
                ->assertExitCode(1);
        } finally {
            config(['database.connections.sqlite.database' => $previous]);
        }
    }
}
