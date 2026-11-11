<?php

namespace App\Services\Sifi\Integration;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * IH03A: project existing, owner-bound research receipts to a separate shadow index.
 * No HTTP, AI, provider activation, evidence admission, or operational database writes.
 */
final class ConnectorObservationProjector
{
    public function eligible(): bool
    {
        if (config('sifi_ih03.shadow_ingest_enabled') !== true
            || ! app()->environment(['local', 'testing'])
            || config('database.default') !== 'sqlite') {
            return false;
        }

        $database = config('database.connections.sqlite.database');

        // SQLite ':memory:' is used by isolated Laravel test processes.
        if ($database === ':memory:') {
            return true;
        }

        return is_string($database)
            && preg_match('#\A/mnt/d/Research/SIFI/05_VALIDATION/[^\r\n]+\.sqlite\z#D', $database) === 1;
    }

    /**
     * @param array<string, mixed> $envelope
     * @return list<array<string, mixed>>
     */
    public function normalize(string $sessionId, int $ownerId, SifiMonitoringObject $object, array $envelope): array
    {
        if ((int) $object->user_id !== $ownerId
            || preg_match('/\A[0-9a-f-]{36}\z/iD', $sessionId) !== 1) {
            throw new \InvalidArgumentException('IH03_CONTEXT_MISMATCH');
        }

        $mode = $envelope['mode'] ?? null;
        if (! is_string($mode) || ($mode !== 'APPROVED_LIVE_CORPUS_METADATA'
            && ! str_starts_with($mode, 'SYNTHETIC_'))) {
            throw new \InvalidArgumentException('IH03_MODE_NOT_RECOGNIZED');
        }
        $items = $envelope['entries'] ?? null;
        if (! is_array($items) || ! array_is_list($items) || count($items) > 1000) {
            throw new \InvalidArgumentException('IH03_RECEIPT_BOUNDS');
        }

        $rows = [];
        foreach ($items as $item) {
            if (! is_array($item)
                || ! is_string($item['id'] ?? null)
                || preg_match('/\A[a-f0-9]{64}\z/D', $item['id']) !== 1
                || ! is_string($item['canonical_url'] ?? null)
                || strlen($item['canonical_url']) > 1000
                || ! is_string($item['title'] ?? null)
                || trim($item['title']) === '' || mb_strlen($item['title']) > 300) {
                throw new \InvalidArgumentException('IH03_INVALID_RECEIPT');
            }
            $parts = parse_url($item['canonical_url']);
            if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
                || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
                || isset($parts['fragment']) || isset($parts['port'])
                || hash('sha256', $item['canonical_url']) !== $item['id']) {
                throw new \InvalidArgumentException('IH03_UNSAFE_RECEIPT_URL');
            }

            $origin = $item['origins'] ?? [];
            $lineage = $item['lineage'] ?? [];
            if (! is_array($origin) || ! array_is_list($origin) || count($origin) > 100
                || ! is_array($lineage) || ! array_is_list($lineage) || count($lineage) > 100) {
                throw new \InvalidArgumentException('IH03_LINEAGE_BOUNDS');
            }
            $origins = [];
            $providers = [];
            foreach ($origin as $value) {
                if (! is_string($value) || mb_strlen($value) > 200) {
                    throw new \InvalidArgumentException('IH03_ORIGIN_INVALID');
                }
                $origins[] = mb_strtolower(trim($value));
            }
            foreach ($lineage as $hit) {
                $provider = is_array($hit) ? ($hit['provider'] ?? null) : null;
                if (! is_string($provider) || strlen($provider) > 64) {
                    throw new \InvalidArgumentException('IH03_PROVIDER_INVALID');
                }
                $providers[$provider] = true;
            }
            $origins = array_values(array_unique($origins));
            sort($origins, SORT_STRING);
            $providerIds = array_keys($providers);
            sort($providerIds, SORT_STRING);

            $published = $item['published_at'] ?? null;
            $rights = $item['rights_state'] ?? 'UNASSESSED';
            $snippetHash = $item['snippet_sha256'] ?? null;
            if (($published !== null && (! is_string($published) || strlen($published) > 40))
                || ! is_string($rights) || strlen($rights) > 100
                || ($snippetHash !== null && (! is_string($snippetHash)
                    || preg_match('/\A[a-f0-9]{64}\z/D', $snippetHash) !== 1))) {
                throw new \InvalidArgumentException('IH03_METADATA_INVALID');
            }

            $rows[] = [
                'id' => hash('sha256', $sessionId.'|'.$item['id']),
                'session_id' => $sessionId,
                'owner_user_id' => $ownerId,
                'object_id' => $object->id,
                'object_revision' => (int) $object->revision,
                'item_id' => $item['id'],
                'canonical_url' => $item['canonical_url'],
                'title' => $item['title'],
                'snippet_sha256' => $snippetHash,
                'published_at' => $published,
                'origin_labels_sha256' => hash('sha256', json_encode($origins, JSON_THROW_ON_ERROR)),
                'provider_ids_json' => json_encode($providerIds, JSON_THROW_ON_ERROR),
                'rights_state' => $rights,
                'source_mode' => $mode,
                'evidence_admission' => 'NONE',
                'created_at' => now(),
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $envelope */
    public function record(User $owner, SifiMonitoringObject $object, string $sessionId, array $envelope): int
    {
        if (! $this->eligible()) {
            return 0;
        }
        $rows = $this->normalize($sessionId, (int) $owner->id, $object, $envelope);
        $stored = 0;
        foreach (array_chunk($rows, 50) as $chunk) {
            $stored += DB::table('sifi_connector_observations')->insertOrIgnore($chunk);
        }

        return $stored;
    }
}
