<?php

declare(strict_types=1);

namespace App\Sifi\CoreV2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A one-way, read-only bridge. R2 may replace the backend without changing reports. */
final class LegacySessionReadGateway
{
    public function inspect(?string $sessionId = null): array
    {
        if (!Schema::hasTable('sifi_research_sessions')) {
            return ['status' => 'SESSION_TABLE_MISSING'];
        }
        $q = DB::table('sifi_research_sessions');
        if ($sessionId !== null) {
            $q->where('id', $sessionId);
        } else {
            $q->where('results_json', 'like', '%"e2e01_pilot"%')->orderByDesc('updated_at');
        }
        $session = $q->first();
        if ($session === null) {
            return ['status' => 'NO_MATCHING_SESSION'];
        }
        $object = Schema::hasTable('sifi_monitoring_objects')
            ? DB::table('sifi_monitoring_objects')->where('id', $session->object_id)->first()
            : null;
        $signal = Schema::hasTable('sifi_real_source_signals')
            ? DB::table('sifi_real_source_signals')->where('session_id', $session->id)->first()
            : null;

        return [
            'status' => 'SESSION_PROJECTION_AVAILABLE',
            'timeline' => PipelineReadModel::build($session, $object, $signal),
        ];
    }
}
