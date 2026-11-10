<?php

namespace Tests\Unit\Sifi;

use App\Models\SifiMonitoringObject;
use App\Services\Sifi\Integration\ConnectorObservationProjector;
use Tests\TestCase;

final class ConnectorObservationProjectorTest extends TestCase
{
    private function object(): SifiMonitoringObject
    {
        $object = new SifiMonitoringObject;
        $object->id = '5c2d1282-f796-4019-8529-26c3f6867154';
        $object->user_id = 7;
        $object->revision = 2;

        return $object;
    }

    private function envelope(string $mode = 'APPROVED_LIVE_CORPUS_METADATA'): array
    {
        $url = 'https://doi.org/10.1000/ih03';

        return [
            'mode' => $mode,
            'entries' => [[
                'id' => hash('sha256', $url),
                'canonical_url' => $url,
                'title' => 'Real metadata candidate',
                'published_at' => '2026-11-01',
                'snippet_sha256' => hash('sha256', 'citation metadata'),
                'rights_state' => 'BIBLIOGRAPHIC_METADATA_REUSE_NO_ABSTRACT',
                'origins' => ['Journal A', 'Journal A'],
                'lineage' => [['provider' => 'CROSSREF'], ['provider' => 'CROSSREF']],
            ]],
        ];
    }

    public function test_live_receipt_is_normalized_without_claiming_independent_origin_or_evidence(): void
    {
        $session = '9f076fec-19a0-40b3-bf3a-cf67d2fe02c2';
        $projector = new ConnectorObservationProjector;
        $rows = $projector->normalize($session, 7, $this->object(), $this->envelope());

        $this->assertCount(1, $rows);
        $this->assertSame('NONE', $rows[0]['evidence_admission']);
        $this->assertSame('APPROVED_LIVE_CORPUS_METADATA', $rows[0]['source_mode']);
        $this->assertSame('["CROSSREF"]', $rows[0]['provider_ids_json']);
        $this->assertArrayNotHasKey('claim_confidence', $rows[0]);
        $this->assertArrayNotHasKey('independent_origin_count', $rows[0]);
    }

    public function test_untrusted_urls_and_wrong_owner_are_rejected(): void
    {
        $projector = new ConnectorObservationProjector;
        $envelope = $this->envelope();
        $envelope['entries'][0]['canonical_url'] = 'http://127.0.0.1/private';

        try {
            $projector->normalize('9f076fec-19a0-40b3-bf3a-cf67d2fe02c2', 7, $this->object(), $envelope);
            $this->fail('Unsafe URL was accepted');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('IH03_UNSAFE_RECEIPT_URL', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IH03_CONTEXT_MISMATCH');
        $projector->normalize('9f076fec-19a0-40b3-bf3a-cf67d2fe02c2', 8, $this->object(), $this->envelope());
    }

    public function test_unknown_mode_and_unbounded_payload_fail_closed(): void
    {
        $projector = new ConnectorObservationProjector;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IH03_MODE_NOT_RECOGNIZED');
        $projector->normalize('9f076fec-19a0-40b3-bf3a-cf67d2fe02c2', 7, $this->object(), $this->envelope('PRODUCTION_GRANTED'));
    }

    public function test_record_is_off_by_default(): void
    {
        config(['sifi_ih03.shadow_ingest_enabled' => false]);
        $this->assertFalse((new ConnectorObservationProjector)->eligible());
    }
}
