<?php

namespace Tests\Unit\Sifi;

use App\Services\Sifi\Integration\ResearchReceiptSignalIntelligence;
use PHPUnit\Framework\TestCase;

final class ResearchReceiptSignalIntelligenceTest extends TestCase
{
    private function item(string $title, string $doi): array
    {
        $url = 'https://doi.org/'.$doi;
        return [
            'id' => hash('sha256', $url),
            'canonical_url' => $url,
            'title' => $title,
            'published_at' => '2025',
            'publication_precision' => 'YEAR',
            'rights_state' => 'BIBLIOGRAPHIC_METADATA_REUSE_NO_ABSTRACT',
            'evidence_admission' => 'NONE',
            'lineage' => [['provider' => 'CROSSREF']],
            'relevance' => ['score' => 6],
        ];
    }

    public function test_groups_lexical_topics_without_inventing_independence_or_confidence(): void
    {
        $receipt = [
            'mode' => 'APPROVED_LIVE_CORPUS_METADATA',
            'calls' => [['provider' => 'CROSSREF', 'state' => 'OK', 'network_requests' => 1]],
            'entries' => [
                $this->item('Misinformation and digital media patterns', '10.1000/a'),
                $this->item('Digital media misinformation research', '10.1000/b'),
                $this->item('Climate resilience evidence', '10.1000/c'),
            ],
        ];
        $result = (new ResearchReceiptSignalIntelligence())->project($receipt);
        $this->assertSame(3, $result['unique_publications']);
        $this->assertSame(1, $result['network_requests']);
        $this->assertSame(1, $result['lexical_topic_cluster_count']);
        $this->assertSame(2, $result['lexical_topic_clusters'][0]['item_count']);
        $this->assertNull($result['independent_origin_count']);
        $this->assertNull($result['claim_confidence']);
        $this->assertSame('NONE', $result['evidence_admission']);
        $this->assertSame('DENIED', $result['production_admission']);
    }

    public function test_rejects_synthetic_and_unadmitted_or_tampered_entries(): void
    {
        $service = new ResearchReceiptSignalIntelligence();
        try {
            $service->project(['mode' => 'SYNTHETIC_HTTP_FAKE_API_ADAPTER', 'entries' => [], 'calls' => []]);
            $this->fail('Synthetic source should be rejected');
        } catch (\InvalidArgumentException $error) {
            $this->assertSame('IH03C_LIVE_METADATA_RECEIPT_REQUIRED', $error->getMessage());
        }

        $entry = $this->item('Misinformation evidence', '10.1000/a');
        $entry['evidence_admission'] = 'APPROVED';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IH03C_INVALID_METADATA_ENTRY');
        $service->project([
            'mode' => 'APPROVED_LIVE_CORPUS_METADATA',
            'calls' => [],
            'entries' => [$entry],
        ]);
    }
}
