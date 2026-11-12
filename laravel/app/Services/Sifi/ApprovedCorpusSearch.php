<?php

namespace App\Services\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Server-only one-request entry point; no public issuance/activation endpoint. */
final class ApprovedCorpusSearch
{
    /**
     * @param  array<string, mixed>  $dateWindow
     * @return array<string, mixed>
     */
    public function context(User $u, SifiMonitoringObject $o, string $sessionId, string $provider, int $queryIndex, array $dateWindow = []): array
    {
        Gate::forUser($u)->authorize('update', $o);
        if ($o->state !== 'CONFIGURED') {
            throw ValidationException::withMessages(['research' => 'Objek dijeda.']);
        }app(SearchProviderRegistry::class)->get($provider);
        $row = app(ResearchRetrievalWorkspace::class)->session($u, $o, $sessionId);
        $plan = json_decode($row->plan_json, true, 32, JSON_THROW_ON_ERROR);
        $query = $plan['queries'][$queryIndex]['query'] ?? null;
        if (! is_string($query)) {
            throw ValidationException::withMessages(['research' => 'Query bukan bagian rencana sesi.']);
        }
        $context = ['mode' => 'LIVE', 'query' => $query, 'query_sha256' => hash('sha256', $query), 'binding' => ['owner_id' => $u->id, 'object_id' => $o->id, 'session_id' => $row->id, 'object_revision' => $o->revision, 'session_revision' => (int) $row->revision, 'plan_sha256' => hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR))], 'limit' => 5];
        if ($provider === 'TAVILY_KEYLESS') {
            $context['date_window'] = $dateWindow;
            app(SearchProviderRegistry::class)->parameters($provider, $query, 5, $context);
        }

        return $context;
    }

    /** @param array<string, mixed> $dateWindow */
    public function run(User $u, SifiMonitoringObject $o, string $sessionId, string $provider, int $queryIndex, bool $recover = false, array $dateWindow = []): string
    {
        $context = $this->context($u, $o, $sessionId, $provider, $queryIndex, $dateWindow);
        $row = app(ResearchRetrievalWorkspace::class)->session($u, $o, $sessionId);
        $results = json_decode($row->results_json, true, 32, JSON_THROW_ON_ERROR);
        if (isset($results['multi_source'])) {
            throw ValidationException::withMessages(['research' => 'Sesi sudah mempunyai hasil multi-sumber; hasil tidak ditimpa.']);
        }
        $plan = json_decode($row->plan_json, true, 32, JSON_THROW_ON_ERROR);
        $envelope = app(RealSearchWorkspace::class)->execute($o, $plan, $context['binding'], 'approved_live', [$provider], 'LIVE', $queryIndex, $recover, $dateWindow);
        $envelope['insights'] = app(ResearchSignalInsights::class)->summarize($envelope);
        DB::transaction(function () use ($u, $o, $sessionId, $context, $envelope) {
            $fresh = SifiMonitoringObject::whereKey($o->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($u)->authorize('update', $fresh);
            $s = app(ResearchRetrievalWorkspace::class)->session($u, $fresh, $sessionId);
            $s = DB::table('sifi_research_sessions')->where('id', $s->id)->lockForUpdate()->firstOrFail();
            $r = json_decode($s->results_json, true, 32, JSON_THROW_ON_ERROR);
            if ($fresh->state !== 'CONFIGURED' || isset($r['multi_source']) || (int) $s->revision !== $context['binding']['session_revision'] || hash('sha256', json_encode(json_decode($s->plan_json, true), JSON_THROW_ON_ERROR)) !== $context['binding']['plan_sha256']) {
                throw ValidationException::withMessages(['research' => 'Konteks berubah; receipt tetap ada, request tidak diulang.']);
            }
            $digest = app(MultiSourceResearchWorkspace::class)->digest($envelope);
            $r['multi_source'] = ['state' => 'DRAFT', 'envelope' => $envelope, 'envelope_sha256' => $digest, 'saved_at' => null, 'analyst_note' => null, 'selected_ids' => []];
            DB::table('sifi_research_sessions')->where('id', $s->id)->update(['results_json' => json_encode($r, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            DB::table('sifi_research_session_audit')->insert(['session_id' => $s->id, 'actor_user_id' => $u->id, 'action' => 'APPROVED_CORPUS_SEARCH_COMPLETED', 'snapshot_json' => json_encode(['envelope_sha256' => $digest, 'request_context_sha256' => hash('sha256', json_encode($context, JSON_THROW_ON_ERROR)), 'network_requests' => $envelope['usage']['network_requests'], 'evidence_admission' => 'NONE'], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });

        return $sessionId;
    }
}
