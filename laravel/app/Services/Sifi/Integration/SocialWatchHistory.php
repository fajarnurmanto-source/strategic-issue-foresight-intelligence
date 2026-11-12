<?php

namespace App\Services\Sifi\Integration;

use App\Models\SifiMonitoringObject;
use Illuminate\Support\Facades\DB;

/**
 * Cross-scan novelty for one owner's monitoring object.
 * A first local sighting never means a new platform post or a distinct event.
 */
final class SocialWatchHistory
{
    /**
     * @param  array<int, mixed>  $entries
     * @return array<string, mixed>
     */
    public function classify(int $ownerId, SifiMonitoringObject $object, array $entries): array
    {
        if (count($entries) > 10 || ! array_is_list($entries)
            || (int) $object->user_id !== $ownerId) {
            throw new \InvalidArgumentException('SIFI_SOCIAL_HISTORY_CONTEXT_INVALID');
        }
        $ids = [];
        foreach ($entries as $entry) {
            $id = is_array($entry) ? ($entry['id'] ?? null) : null;
            if (! is_string($id) || preg_match('/\A[a-f0-9]{64}\z/D', $id) !== 1) {
                throw new \InvalidArgumentException('SIFI_SOCIAL_HISTORY_ENTRY_INVALID');
            }
            $ids[] = $id;
        }

        if (! app(ConnectorObservationProjector::class)->eligible()) {
            foreach ($entries as &$entry) {
                $entry['local_history_state'] = 'HISTORY_UNAVAILABLE';
            }
            unset($entry);

            return [
                'entries' => $entries,
                'summary' => [
                    'state' => 'HISTORY_UNAVAILABLE',
                    'first_observed_count' => null,
                    'previously_observed_count' => null,
                    'scope' => 'Only this owner and monitoring object; no platform-wide novelty claim.',
                ],
            ];
        }

        $previous = $ids === [] ? [] : DB::table('sifi_connector_observations')
            ->where('owner_user_id', $ownerId)
            ->where('object_id', $object->id)
            ->whereIn('item_id', array_values(array_unique($ids)))
            ->distinct()
            ->pluck('item_id')
            ->all();
        $previous = array_fill_keys($previous, true);
        $first = 0;
        $seen = 0;
        foreach ($entries as &$entry) {
            if (isset($previous[$entry['id']])) {
                $entry['local_history_state'] = 'PREVIOUSLY_OBSERVED';
                $seen++;
            } else {
                $entry['local_history_state'] = 'FIRST_OBSERVED_IN_LOCAL_INDEX';
                $first++;
            }
        }
        unset($entry);

        return [
            'entries' => $entries,
            'summary' => [
                'state' => 'LOCAL_HISTORY_AVAILABLE',
                'first_observed_count' => $first,
                'previously_observed_count' => $seen,
                'scope' => 'Compared with this owner/object shadow observation history only; not proof of new posts or events.',
            ],
        ];
    }
}
