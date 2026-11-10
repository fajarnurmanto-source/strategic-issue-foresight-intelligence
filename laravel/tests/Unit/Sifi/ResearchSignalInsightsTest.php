<?php

namespace Tests\Unit\Sifi;

use App\Services\Sifi\ResearchSignalInsights;
use PHPUnit\Framework\TestCase;

final class ResearchSignalInsightsTest extends TestCase
{
    private function entry(string $id, string $url, string $snippet, string $origin): array
    {
        return [
            'id' => str_repeat($id, 64),
            'canonical_url' => $url,
            'snippet' => $snippet,
            'snippet_sha256' => hash('sha256', $snippet),
            'origins' => [$origin],
            'lineage' => [['provider' => 'SYNTHETIC_FEED', 'origin' => $origin]],
            'published_at' => null,
            'rights_state' => 'UNASSESSED',
        ];
    }

    public function test_identical_metadata_is_only_a_republication_candidate(): void
    {
        $summary = (new ResearchSignalInsights)->summarize([
            'entries' => [
                $this->entry('a', 'https://a.example/news', 'Identical metadata', 'Press A'),
                $this->entry('b', 'https://b.example/news', 'Identical metadata', 'Press B'),
            ],
            'raw_result_count' => 7,
        ]);

        $this->assertSame(2, $summary['candidate_item_count']);
        $this->assertSame(7, $summary['raw_hit_count']);
        $this->assertSame(2, $summary['declared_origin_label_count']);
        $this->assertNull($summary['independent_origin_count']);
        $this->assertSame('NOT_VERIFIED', $summary['independence_verification']);
        $this->assertSame(1, $summary['possible_republication_cluster_count']);
        $this->assertSame(2, $summary['possible_republication_clusters'][0]['distinct_url_count']);
        $this->assertSame(2, $summary['unknown_publication_date_count']);
        $this->assertSame(2, $summary['rights_unassessed_count']);
        $this->assertSame(['SYNTHETIC_FEED' => 2], $summary['provider_hit_counts']);
        $this->assertNull($summary['claim_confidence']);
        $this->assertNull($summary['engagement_normalized_velocity']);
        $this->assertNull($summary['event_frequency']);
        $this->assertNull($summary['impact_severity']);
        $this->assertFalse($summary['rights_grant']);
        $this->assertSame('NONE', $summary['evidence_admission']);
        $this->assertSame('DENIED', $summary['production_admission']);
    }

    public function test_empty_or_same_url_or_empty_snippets_cannot_invent_independence_or_copying(): void
    {
        $service = new ResearchSignalInsights;
        $empty = $service->summarize(['entries' => [], 'raw_result_count' => 0]);
        $this->assertSame(0, $empty['declared_origin_label_count']);
        $this->assertNull($empty['independent_origin_count']);
        $this->assertSame(0, $empty['possible_republication_cluster_count']);

        $sameUrl = $service->summarize(['entries' => [
            $this->entry('c', 'https://one.example/news', 'same', 'Source 1'),
            $this->entry('d', 'https://one.example/news', 'same', 'Source 2'),
            $this->entry('e', 'https://three.example/news', '', 'Source 3'),
            $this->entry('f', 'https://four.example/news', '', 'Source 4'),
        ]]);
        $this->assertSame(0, $sameUrl['possible_republication_cluster_count']);
    }

    public function test_malformed_or_unbounded_input_is_rejected(): void
    {
        $service = new ResearchSignalInsights;
        foreach ([['entries' => ['invalid']], ['entries' => [0 => ['id' => 'bad']]], ['entries' => array_fill(0, 1001, [])]] as $input) {
            try {
                $service->summarize($input);
                $this->fail('Invalid envelope accepted');
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
