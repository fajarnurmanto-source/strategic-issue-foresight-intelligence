<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\Integration\CrossSourceReceiptFusion;
use App\Services\Sifi\MultiSourceResearchWorkspace;
use App\Services\Sifi\ResearchSessionWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CrossSourceReceiptFusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_real_receipts_fuse_without_new_network_or_writes(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        Http::preventStrayRequests();
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $object = SifiMonitoringObject::create([
            'id' => (string) Str::uuid(), 'user_id' => $owner->id,
            'name' => '[SYNTHETIC] Two-source metadata', 'description' => 'Isolated test',
            'category' => 'Research', 'objective' => 'Compare headlines',
            'horizon' => '90 hari', 'geography' => null, 'aliases_json' => [],
            'keywords_json' => ['news'], 'exclusions_json' => [],
            'state' => 'CONFIGURED', 'revision' => 1,
        ]);
        $crossref = $this->receipt($owner, $object, 'CROSSREF',
            'https://doi.org/10.1000/ih03f', 'Misinformation in society');
        $antara = $this->receipt($owner, $object, 'ANTARA_RSS',
            'https://www.antaranews.com/berita/123/hoaks-terbaru', 'Berita tentang hoaks');

        $before = DB::table('sifi_research_sessions')->count();
        $report = app(CrossSourceReceiptFusion::class)->analyze($owner, $crossref, $antara);
        $this->assertSame(['CROSSREF' => 1, 'ANTARA_RSS' => 1], $report['source_counts']);
        $this->assertSame(2, $report['distinct_canonical_urls']);
        $this->assertSame([], $report['same_url_cross_source_candidates']);
        $this->assertNull($report['independent_origin_count']);
        $this->assertSame(0, $report['new_network_requests']);
        $this->assertSame(0, $report['new_database_writes']);
        $this->assertSame($before, DB::table('sifi_research_sessions')->count());
        $this->assertDatabaseCount('sifi_connector_observations', 0);
        Http::assertNothingSent();
    }

    private function receipt(User $owner, SifiMonitoringObject $object, string $provider,
        string $url, string $title): string
    {
        $id = app(ResearchSessionWorkspace::class)->create($owner, $object, [
            'question' => 'Research public metadata',
            'object_revision' => 1, 'query_limit' => 1, 'result_limit' => 5,
        ]);
        $envelope = [
            'mode' => 'APPROVED_LIVE_CORPUS_METADATA',
            'provider_ids' => [$provider],
            'calls' => [['provider' => $provider, 'state' => 'OK', 'network_requests' => 1]],
            'entries' => [[
                'id' => hash('sha256', $url), 'canonical_url' => $url,
                'title' => $title, 'published_at' => null,
                'publication_precision' => 'UNKNOWN',
                'evidence_admission' => 'NONE',
            ]],
        ];
        $results = json_decode(DB::table('sifi_research_sessions')
            ->where('id', $id)->value('results_json'), true, 32, JSON_THROW_ON_ERROR);
        $results['multi_source'] = [
            'state' => 'DRAFT', 'envelope' => $envelope,
            'envelope_sha256' => app(MultiSourceResearchWorkspace::class)->digest($envelope),
        ];
        DB::table('sifi_research_sessions')->where('id', $id)
            ->update(['results_json' => json_encode($results, JSON_THROW_ON_ERROR)]);
        return $id;
    }
}
