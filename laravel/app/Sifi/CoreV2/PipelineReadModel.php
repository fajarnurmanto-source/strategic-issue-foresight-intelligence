<?php

declare(strict_types=1);

namespace App\Sifi\CoreV2;

/**
 * Read-only projection of the already-persisted E2E-01..04 session artifacts.
 * This class does not infer that a real-source snapshot is verified evidence.
 */
final class PipelineReadModel
{
    /**
     * @return array{
     *     schema: string,
     *     mode: string,
     *     session_id: string,
     *     object_id: string,
     *     session_object_revision: int,
     *     current_object_revision: int|null,
     *     source_url: string|null,
     *     source_sha256: string|null,
     *     image_sha256: string|null,
     *     brief_sha256: string|null,
     *     stages: array<string, string>,
     *     warnings: list<string>,
     *     limitations: list<string>
     * }
     */
    public static function build(object $session, ?object $object, ?object $realSignal): array
    {
        $identity = get_object_vars($session);
        $sessionId = $identity['id'] ?? null;
        $objectId = $identity['object_id'] ?? null;
        if ((!is_string($sessionId) && !is_int($sessionId))
            || (!is_string($objectId) && !is_int($objectId))) {
            throw new \UnexpectedValueException('SESSION_IDENTITY_MISSING');
        }

        $data = json_decode((string) ($session->results_json ?? '{}'), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \UnexpectedValueException('SESSION_RESULTS_NOT_AN_OBJECT');
        }

        $acquisition = self::part($data, 'e2e01_pilot');
        $image = self::part($data, 'e2e02_image');
        $candidate = self::part($data, 'e2e02_review_packet');
        $intelligence = self::part($data, 'e2e03_intelligence');
        $handoff = self::part($data, 'e2e04_handoff');
        $sourceHash = self::digest($acquisition['body_sha256'] ?? null);
        $imageHash = self::digest($image['sha256'] ?? null);
        $briefHash = self::digest($intelligence['brief_sha256'] ?? null);

        $warnings = [];
        $stages = [];
        $stages['research_session'] = 'RECORDED';
        $stages['real_acquisition'] = ($acquisition['state'] ?? null) === 'RETRIEVED_EXTRACTED'
            && $sourceHash !== null ? 'SOURCE_METADATA_RECORDED' : 'NOT_RECORDED';
        $text = $acquisition['extraction']['text'] ?? null;
        $stages['text_extraction'] = is_string($text) && trim($text) !== ''
            && $stages['real_acquisition'] !== 'NOT_RECORDED' ? 'TEXT_RECORDED' : 'NOT_RECORDED';
        $stages['image_acquisition'] = $imageHash !== null && is_string($image['private_path'] ?? null)
            ? 'IMAGE_METADATA_RECORDED' : 'NOT_RECORDED';
        $stages['claim_candidate'] = ($candidate['state'] ?? null) === 'CANDIDATE_REVIEW_REQUIRED'
            ? 'REVIEW_REQUIRED' : 'NOT_RECORDED';

        if ($sourceHash !== null && $stages['claim_candidate'] !== 'NOT_RECORDED') {
            if (!hash_equals($sourceHash, (string) ($candidate['source_sha256'] ?? ''))) {
                $warnings[] = 'CLAIM_SOURCE_HASH_REFERENCE_MISMATCH';
            }
        }
        if ($imageHash !== null && $stages['claim_candidate'] !== 'NOT_RECORDED') {
            if (!hash_equals($imageHash, (string) ($candidate['image_sha256'] ?? ''))) {
                $warnings[] = 'CLAIM_IMAGE_HASH_REFERENCE_MISMATCH';
            }
        }

        $stages['analyst_intelligence'] = ($intelligence['state'] ?? null) === 'ANALYST_DRAFT_NOT_EVIDENCE'
            ? 'ANALYST_DRAFT' : 'NOT_RECORDED';
        $brief = $intelligence['brief'] ?? null;
        $stages['intelligence_brief'] = $stages['analyst_intelligence'] === 'ANALYST_DRAFT'
            && is_string($brief) && $brief !== '' && $briefHash !== null
            ? 'DRAFT_RECORDED' : 'NOT_RECORDED';
        if ($stages['intelligence_brief'] === 'DRAFT_RECORDED'
            && !hash_equals($briefHash, hash('sha256', $brief))) {
            $stages['intelligence_brief'] = 'INTEGRITY_WARNING';
            $warnings[] = 'BRIEF_CONTENT_HASH_MISMATCH';
        }
        if ($sourceHash !== null && $stages['analyst_intelligence'] !== 'NOT_RECORDED'
            && !hash_equals($sourceHash, (string) ($intelligence['source_sha256'] ?? ''))) {
            $warnings[] = 'INTELLIGENCE_SOURCE_HASH_REFERENCE_MISMATCH';
        }

        $handoffSignal = self::digest($handoff['signal_id'] ?? null);
        // Local signal IDs are SHA-256 digests of session + source metadata.
        $signalId = $realSignal !== null ? (string) ($realSignal->id ?? '') : '';
        $handoffReady = ($handoff['state'] ?? null) === 'LOCAL_TRIAGE_DRAFT_NOT_ADMITTED'
            && $handoffSignal !== null && $signalId !== '' && hash_equals($signalId, $handoffSignal);
        $stages['issue_handoff'] = $handoffReady ? 'LOCAL_DRAFT_LINKED' : 'NOT_CONFIRMED';
        if ($handoffSignal !== null && !$handoffReady) {
            $warnings[] = 'LOCAL_SIGNAL_REGISTRY_NOT_CONFIRMED';
        }

        // Intentionally cannot be changed by the presence of a draft or a review note.
        $stages['admitted_evidence'] = 'NOT_ESTABLISHED';
        $stages['verified_brief'] = 'NOT_ESTABLISHED';
        $stages['automated_multisource_analysis'] = 'NOT_ESTABLISHED';

        $objectRevision = $object === null ? null : (int) ($object->revision ?? 0);
        $sessionRevision = (int) ($session->object_revision ?? 0);
        if ($objectRevision === null) {
            $warnings[] = 'MONITORING_OBJECT_NOT_FOUND';
        } elseif ($objectRevision !== $sessionRevision) {
            $warnings[] = 'OBJECT_REVISION_MISMATCH';
        }

        $sourceUrl = $acquisition['source_url'] ?? null;

        return [
            'schema' => 'sifi-core-v2-read-model-1',
            'mode' => 'SHADOW_READ_ONLY',
            'session_id' => (string) $sessionId,
            'object_id' => (string) $objectId,
            'session_object_revision' => $sessionRevision,
            'current_object_revision' => $objectRevision,
            'source_url' => is_string($sourceUrl) ? $sourceUrl : null,
            'source_sha256' => $sourceHash,
            'image_sha256' => $imageHash,
            'brief_sha256' => $briefHash,
            'stages' => $stages,
            'warnings' => $warnings,
            'limitations' => [
                'Raw web bytes were not re-fetched or re-hashed in this read-only view.',
                'Image file bytes were not re-hashed in this read-only view.',
                'Drafts and review notes do not establish official evidence admission.',
                'No AI, network, background job, or database write was executed.',
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private static function part(array $data, string $key): array
    {
        return is_array($data[$key] ?? null) ? $data[$key] : [];
    }

    private static function digest(mixed $value): ?string
    {
        return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1 ? $value : null;
    }
}
