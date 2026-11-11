<?php

namespace App\Services\Sifi;

use InvalidArgumentException;

/** Explains a trusted draft projection; never creates comparison context or authority. */
final class ClaimComparisonInputDiagnostics
{
    private const MAX_CLAIMS = 50;
    private const MAX_EVENTS_PER_CLAIM = 10;

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function summarize(array $input): array
    {
        $candidateId = $this->identifier($input['candidate_id'] ?? null);
        $candidateRevision = $this->revision($input['candidate_revision'] ?? null);
        foreach (['claims', 'events', 'links'] as $key) {
            if (!isset($input[$key]) || !is_array($input[$key]) || !array_is_list($input[$key])) {
                throw new InvalidArgumentException('Invalid server-owned draft projection.');
            }
        }

        $claims = [];
        foreach ($input['claims'] as $claim) {
            if (!is_array($claim)) {
                throw new InvalidArgumentException('Invalid server-owned claim projection.');
            }
            if (array_key_exists('candidate_id', $claim) && $claim['candidate_id'] !== $candidateId) {
                continue;
            }
            $id = $this->identifier($claim['id'] ?? null);
            if (isset($claims[$id])) {
                throw new InvalidArgumentException('Ambiguous server-owned claim projection.');
            }
            $modality = $claim['modality'] ?? null;
            if (!is_string($modality) || trim($modality) === '' || strlen($modality) > 80) {
                throw new InvalidArgumentException('Invalid server-owned draft modality.');
            }
            $claims[$id] = [
                'claim_id' => $id,
                'evidence_id' => $this->identifier($claim['evidence_id'] ?? null),
                'claim_revision' => $this->revision($claim['revision'] ?? null),
                'evidence_revision' => $this->revision($claim['evidence_revision'] ?? null),
                'modality' => $modality,
            ];
        }

        $events = [];
        foreach ($input['events'] as $event) {
            if (!is_array($event)) {
                throw new InvalidArgumentException('Invalid server-owned event projection.');
            }
            if (array_key_exists('candidate_id', $event) && $event['candidate_id'] !== $candidateId) {
                continue;
            }
            $id = $this->identifier($event['id'] ?? null);
            if (isset($events[$id])) {
                throw new InvalidArgumentException('Ambiguous server-owned event projection.');
            }
            if (!array_key_exists('time_note', $event) || !array_key_exists('place_note', $event)
                || ($event['time_note'] !== null && !is_string($event['time_note']))
                || ($event['place_note'] !== null && !is_string($event['place_note']))) {
                throw new InvalidArgumentException('Invalid server-owned event notes.');
            }
            $events[$id] = [
                'event_id' => $id,
                'revision' => $this->revision($event['revision'] ?? null),
                'has_time_note' => is_string($event['time_note']) && trim($event['time_note']) !== '',
                'has_place_note' => is_string($event['place_note']) && trim($event['place_note']) !== '',
            ];
        }

        $linksByClaim = [];
        $linkCount = 0;
        foreach ($input['links'] as $link) {
            if (!is_array($link)
                || (array_key_exists('candidate_id', $link) && $link['candidate_id'] !== $candidateId)
                || !is_string($link['claim_id'] ?? null)
                || !is_string($link['event_id'] ?? null)
                || !in_array($link['relation'] ?? null, ['UNKNOWN', 'POSSIBLE_ASSOCIATION'], true)) {
                continue;
            }
            $claimId = $link['claim_id'];
            $eventId = $link['event_id'];
            if (!isset($claims[$claimId], $events[$eventId]) || isset($linksByClaim[$claimId][$eventId])) {
                continue;
            }
            $linksByClaim[$claimId][$eventId] = $events[$eventId];
            $linkCount++;
        }

        ksort($claims, SORT_STRING);
        $rows = [];
        foreach (array_slice($claims, 0, self::MAX_CLAIMS, true) as $id => $claim) {
            $linkedEvents = $linksByClaim[$id] ?? [];
            ksort($linkedEvents, SORT_STRING);
            $reasons = [
                'STRUCTURED_PROPOSITION_NOT_CAPTURED',
                'COMPARISON_SCOPE_NOT_CAPTURED',
                'HISTORICAL_CUSTODY_NOT_ATTESTED',
            ];
            if ($linkedEvents === []) {
                $reasons[] = 'DRAFT_EVENT_LINK_MISSING';
            }
            if ($claim['modality'] !== 'ASSERTED') {
                $reasons[] = 'NON_ASSERTED_DRAFT_MODALITY';
            }
            $rows[] = $claim + [
                'linked_event_count' => count($linkedEvents),
                'links_truncated' => count($linkedEvents) > self::MAX_EVENTS_PER_CLAIM,
                'linked_events' => array_values(array_slice($linkedEvents, 0, self::MAX_EVENTS_PER_CLAIM, true)),
                'reason_codes' => $reasons,
            ];
        }

        return [
            'schema_version' => 'sifi.claim.input.diagnostics.v1',
            'kind' => 'NON_BINDING_DIAGNOSTIC',
            'candidate_id' => $candidateId,
            'candidate_revision' => $candidateRevision,
            'claim_count' => count($claims),
            'displayed_claim_count' => count($rows),
            'event_count' => count($events),
            'link_count' => $linkCount,
            'claims_truncated' => count($claims) > self::MAX_CLAIMS,
            'claims' => $rows,
            'comparison_eligible' => false,
            'review_authorized' => false,
            'evidence_admission' => 'NOT_EVALUATED',
            'structured_context' => 'NOT_ESTABLISHED',
            'historical_custody' => 'NOT_ATTESTED',
        ];
    }

    private function identifier(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '' || strlen($value) > 128) {
            throw new InvalidArgumentException('Invalid server-owned projection identifier.');
        }

        return $value;
    }

    private function revision(mixed $value): int
    {
        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException('Invalid server-owned projection revision.');
        }

        return $value;
    }
}
