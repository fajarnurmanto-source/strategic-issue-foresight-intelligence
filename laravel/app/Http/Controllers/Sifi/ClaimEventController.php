<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sifi\StoreClaimEventRequest;
use App\Models\SifiClaimDraft;
use App\Models\SifiEventHypothesis;
use App\Models\SifiIssueCandidate;
use App\Services\Sifi\Aq01ClaimEventBridge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** Local structural hypotheses only. No real events or verified claim/evidence objects. */
final class ClaimEventController extends Controller
{
    /** @return array<string, mixed> */
    private function input(SifiIssueCandidate $candidate): array
    {
        $claims = DB::table('sifi_claim_drafts as c')
            ->join('sifi_evidence_drafts as e', 'e.id', '=', 'c.evidence_id')
            ->where('c.candidate_id', $candidate->id)->where('e.candidate_id', $candidate->id)
            ->orderBy('c.id')->get(['c.id', 'c.evidence_id', 'e.signal_id', 'c.statement', 'c.attribution', 'c.modality', 'c.revision', 'e.revision as evidence_revision'])
            ->map(fn ($x) => ['id' => $x->id, 'evidence_id' => $x->evidence_id,
                'signal_id' => $x->signal_id, 'statement' => $x->statement,
                'attribution' => $x->attribution, 'modality' => $x->modality, 'revision' => (int) $x->revision, 'evidence_revision' => (int) $x->evidence_revision])->all();
        $events = SifiEventHypothesis::query()->where('candidate_id', $candidate->id)->orderBy('id')
            ->get(['id', 'summary', 'time_note', 'place_note', 'revision'])
            ->map(fn ($x) => ['id' => $x->id, 'summary' => $x->summary, 'time_note' => $x->time_note,
                'place_note' => $x->place_note, 'revision' => (int) $x->revision])->all();
        $links = DB::table('sifi_claim_event_links')->where('candidate_id', $candidate->id)
            ->orderBy('claim_id')->orderBy('event_id')->get(['claim_id', 'event_id', 'relation'])
            ->map(fn ($x) => ['claim_id' => $x->claim_id, 'event_id' => $x->event_id, 'relation' => $x->relation])->all();

        return ['candidate_id' => $candidate->id, 'candidate_revision' => (int) $candidate->revision,
            'claims' => $claims, 'events' => $events, 'links' => $links];
    }

    /** @param array<string, mixed> $input */
    private function digest(array $input): string
    {
        return hash('sha256', json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function checkRevision(SifiIssueCandidate $candidate, int $revision): void
    {
        if ((int) $candidate->revision !== $revision) {
            throw ValidationException::withMessages(['candidate_revision' => 'Revisi kandidat berubah. Muat ulang halaman.']);
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function audit(SifiIssueCandidate $c, int $actor, string $action, string $obj, array $snapshot): void
    {
        DB::table('sifi_claim_event_audit')->insert(['candidate_id' => $c->id, 'actor_user_id' => $actor,
            'action' => $action, 'object_id' => $obj,
            'snapshot_json' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now()]);
    }

    public function show(Request $request, SifiIssueCandidate $candidate): Response
    {
        Gate::authorize('view', $candidate);
        $input = $this->input($candidate);
        $evidence = DB::table('sifi_evidence_drafts')->where('candidate_id', $candidate->id)->orderBy('created_at')
            ->get(['id', 'signal_id', 'claim_text', 'relationship'])->all();
        $snapshots = DB::table('sifi_claim_event_assessments')->where('candidate_id', $candidate->id)
            ->orderByDesc('id')->limit(15)->get(['id', 'input_digest', 'result_json', 'created_at']);
        $digest = $this->digest($input);

        return Inertia::render('sifi/claim-event-workspace', [
            'candidate' => ['id' => $candidate->id, 'title' => $candidate->title, 'revision' => (int) $candidate->revision],
            'claims' => $input['claims'], 'events' => $input['events'], 'links' => $input['links'],
            'evidence' => $evidence, 'assessments' => $snapshots->map(fn ($x) => [
                'id' => $x->id, 'is_current' => hash_equals($x->input_digest, $digest),
                'result' => json_decode($x->result_json, true, 32, JSON_THROW_ON_ERROR),
                'created_at' => $x->created_at])->all(),
            'audit' => DB::table('sifi_claim_event_audit')->where('candidate_id', $candidate->id)
                ->orderByDesc('id')->limit(15)->get(['action', 'object_id', 'created_at'])->all(),
            'scope' => 'HUMAN_STRUCTURAL_DRAFT_ONLY_NOT_VERIFIED']);
    }

    public function storeClaim(StoreClaimEventRequest $request, SifiIssueCandidate $candidate): RedirectResponse
    {
        $v = $request->validated();
        DB::transaction(function () use ($v, $candidate, $request): void {
            $locked = SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->user_id === (int) $request->user()->id, 403);
            $this->checkRevision($locked, (int) $v['candidate_revision']);
            if (SifiClaimDraft::query()->where('candidate_id', $locked->id)->count() >= 20) {
                throw ValidationException::withMessages(['statement' => 'Maksimal 20 klaim draf.']);
            }
            $evidence = DB::table('sifi_evidence_drafts')->where('candidate_id', $locked->id)
                ->where('id', $v['evidence_id'])->first(['id', 'signal_id', 'claim_text']);
            if (! $evidence) {
                throw ValidationException::withMessages(['evidence_id' => 'Referensi kandidat bukti tidak berlaku.']);
            }
            $row = SifiClaimDraft::query()->create(['id' => (string) Str::uuid(),
                'candidate_id' => $locked->id, 'evidence_id' => $evidence->id,
                'owner_user_id' => (int) $request->user()->id, 'statement' => trim($v['statement']),
                'attribution' => trim($v['attribution'] ?? ''), 'modality' => $v['modality'], 'revision' => 1]);
            $this->audit($locked, (int) $request->user()->id, 'CLAIM_DRAFT_CREATED', $row->id,
                ['statement' => $row->statement, 'evidence_id' => $row->evidence_id, 'verification' => 'NOT_VERIFIED']);
        });

        return redirect()->route('sifi.claim-events.show', $candidate->id);
    }

    public function storeEvent(StoreClaimEventRequest $request, SifiIssueCandidate $candidate): RedirectResponse
    {
        $v = $request->validated();
        DB::transaction(function () use ($v, $candidate, $request): void {
            $locked = SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->user_id === (int) $request->user()->id, 403);
            $this->checkRevision($locked, (int) $v['candidate_revision']);
            if (SifiEventHypothesis::query()->where('candidate_id', $locked->id)->count() >= 20) {
                throw ValidationException::withMessages(['summary' => 'Maksimal 20 hipotesis peristiwa.']);
            }
            $row = SifiEventHypothesis::query()->create(['id' => (string) Str::uuid(), 'candidate_id' => $locked->id,
                'owner_user_id' => (int) $request->user()->id, 'summary' => trim($v['summary']),
                'time_note' => trim($v['time_note'] ?? ''), 'place_note' => trim($v['place_note'] ?? ''), 'revision' => 1]);
            $this->audit($locked, (int) $request->user()->id, 'EVENT_HYPOTHESIS_CREATED', $row->id,
                ['summary' => $row->summary, 'time_note' => $row->time_note,
                    'place_note' => $row->place_note, 'verification' => 'NOT_VERIFIED']);
        });

        return redirect()->route('sifi.claim-events.show', $candidate->id);
    }

    public function storeLink(StoreClaimEventRequest $request, SifiIssueCandidate $candidate): RedirectResponse
    {
        $v = $request->validated();
        DB::transaction(function () use ($v, $candidate, $request): void {
            $locked = SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->user_id === (int) $request->user()->id, 403);
            $this->checkRevision($locked, (int) $v['candidate_revision']);
            $claim = SifiClaimDraft::query()->where('candidate_id', $locked->id)->whereKey($v['claim_id'])->first();
            $event = SifiEventHypothesis::query()->where('candidate_id', $locked->id)->whereKey($v['event_id'])->first();
            if (! $claim || ! $event) {
                throw ValidationException::withMessages(['relation' => 'Klaim/peristiwa harus berasal dari kandidat yang sama.']);
            }
            if (DB::table('sifi_claim_event_links')->where('claim_id', $claim->id)->where('event_id', $event->id)->exists()) {
                return;
            }
            if (DB::table('sifi_claim_event_links')->where('candidate_id', $locked->id)->count() >= 50) {
                throw ValidationException::withMessages(['relation' => 'Maksimal 50 relasi.']);
            }
            DB::table('sifi_claim_event_links')->insert(['candidate_id' => $locked->id, 'claim_id' => $claim->id,
                'event_id' => $event->id, 'relation' => $v['relation'], 'actor_user_id' => (int) $request->user()->id,
                'created_at' => now()]);
            $this->audit($locked, (int) $request->user()->id, 'POSSIBLE_LINK_CREATED', $claim->id,
                ['claim_id' => $claim->id, 'event_id' => $event->id, 'relation' => $v['relation']]);
        });

        return redirect()->route('sifi.claim-events.show', $candidate->id);
    }

    public function assess(StoreClaimEventRequest $request, SifiIssueCandidate $candidate, Aq01ClaimEventBridge $bridge): RedirectResponse
    {
        $revision = (int) $request->validated('candidate_revision');
        $this->checkRevision($candidate, $revision);
        $input = $this->input($candidate);
        if (count($input['claims']) < 1) {
            throw ValidationException::withMessages(['analysis' => 'Buat minimal satu klaim berdasarkan kandidat bukti.']);
        }
        $digest = $this->digest($input);
        if (DB::table('sifi_claim_event_assessments')->where('candidate_id', $candidate->id)
            ->where('input_digest', $digest)->exists()) {
            return redirect()->route('sifi.claim-events.show', $candidate->id);
        }
        try {
            $result = $bridge->assess($input);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['analysis' => 'Python offline/tidak sesuai kontrak; hasil tidak disimpan.']);
        }
        DB::transaction(function () use ($candidate, $input, $digest, $revision, $result, $request): void {
            $locked = SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->user_id === (int) $request->user()->id, 403);
            if ((int) $locked->revision !== $revision || $this->input($locked) !== $input ||
                $this->digest($this->input($locked)) !== $digest) {
                throw ValidationException::withMessages(['analysis' => 'Input berubah selama analisis. Tidak ada hasil disimpan.']);
            }
            if (DB::table('sifi_claim_event_assessments')->where('candidate_id', $locked->id)
                ->where('input_digest', $digest)->exists()) {
                return;
            }
            DB::table('sifi_claim_event_assessments')->insert(['candidate_id' => $locked->id,
                'actor_user_id' => (int) $request->user()->id, 'candidate_revision' => $revision,
                'input_digest' => $digest, 'status' => 'ADVISORY_ONLY',
                'result_json' => json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => now()]);
        });

        return redirect()->route('sifi.claim-events.show', $candidate->id);
    }
}
