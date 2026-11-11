<?php

namespace App\Services\Sifi\Integration;

use App\Services\Sifi\Ai\AiTaskCapabilityRegistry;
use App\Services\Sifi\Ai\FreeAiCatalogue;
use App\Services\Sifi\SearchProviderRegistry;

/**
 * Read-only projection of existing canonical registries.
 * Never reads owner credentials, probes providers, or establishes eligibility.
 */
final class ProviderInventory
{
    /** Presentation-only list; deliberately separate from dispatchable SearchProviderRegistry::IDS. */
    public const VISIBLE_RESEARCH_IDS = ['WIKIPEDIA_ID', 'CROSSREF', 'GDELT_DOC', 'TAVILY_KEYLESS'];

    /** @return array<string, mixed> */
    public function snapshot(?string $asOf = null): array
    {
        $asOf ??= now('Asia/Jakarta')->toDateString();

        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $asOf) !== 1) {
            throw new \InvalidArgumentException('INVALID_SNAPSHOT_DATE');
        }

        $ai = [];

        foreach (FreeAiCatalogue::entries() as $entry) {
            $ai[] = [
                'id' => (string) $entry['protocol'],
                'label' => (string) $entry['label'],
                'catalog_allowed' => (bool) ($entry['available'] ?? false),
                'policy_state' => (string) ($entry['status'] ?? 'UNKNOWN'),
                'model_count' => count($entry['models'] ?? []),
                'connection_probe' => 'NOT_RUN',
            ];
        }

        $registry = new SearchProviderRegistry;
        $research = [];

        foreach (self::VISIBLE_RESEARCH_IDS as $id) {
            $entry = $registry->get($id);
            $reviewUntil = (string) ($entry['expires_on'] ?? '');

            $research[] = [
                'id' => $id,
                'label' => (string) ($entry['corpus'] ?? $id),
                'rights' => (string) ($entry['rights'] ?? 'UNASSESSED'),
                'review_until' => $reviewUntil,
                'review_state' => $reviewUntil === ''
                    ? 'REVIEW_UNVERIFIED'
                    : ($reviewUntil <= $asOf ? 'REVIEW_EXPIRED' : 'REVIEW_IN_DATE'),
                'connection_probe' => 'NOT_RUN',
                'dispatch_state' => 'NOT_EVALUATED_BY_INVENTORY',
            ];
        }

        return [
            'as_of' => $asOf,
            'read_only' => true,
            'grants_dispatch_authority' => false,
            'ai' => $ai,
            'research' => $research,
            'google_sources' => (new GoogleSourceCoverage)->snapshot($asOf),
            'social_sources' => (new SocialSourceCoverage)->snapshot($asOf),
            'ai_tasks' => (new AiTaskCapabilityRegistry)->tasks(),
        ];
    }
}