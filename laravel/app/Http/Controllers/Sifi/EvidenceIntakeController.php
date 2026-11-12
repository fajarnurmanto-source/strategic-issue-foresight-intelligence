<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sifi\AssignEvidenceReviewerRequest;
use App\Http\Requests\Sifi\StoreEvidenceDraftRequest;
use App\Http\Requests\Sifi\SubmitEvidenceReviewRequest;
use App\Http\Requests\Sifi\UpdateEvidenceDraftRequest;
use App\Models\SifiEvidenceDraft;
use App\Models\SifiIssueCandidate;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Local-only evidence triage. No Python calls, source fetches, rights/admission or official Issue Objects. */
final class EvidenceIntakeController extends Controller
{
    public function index(Request $request, SifiIssueCandidate $candidate): Response
    {
        Gate::authorize('view', $candidate);
        $items = SifiEvidenceDraft::query()->where('candidate_id', $candidate->id)
            ->where('owner_user_id', $request->user()->id)->orderByDesc('created_at')->limit(100)->get();
        $ids = $this->linkedReferences($candidate)
            ->orderBy('signal_id')->pluck('signal_id')->all();
        $linked = array_fill_keys($ids, true);
        $auditRows = DB::table('sifi_evidence_draft_audit')
            ->whereIn('evidence_id', $items->pluck('id'))
            ->select('evidence_id', 'action', 'revision', 'created_at')
            ->selectRaw('row_number() over (partition by evidence_id order by revision desc, id desc) as position');
        $history = DB::query()->fromSub($auditRows, 'recent_audit')->where('position', '<=', 10)
            ->orderBy('position')->get(['evidence_id', 'action', 'revision', 'created_at'])->groupBy('evidence_id');
        $reviews = DB::table('sifi_evidence_review_notes')
            ->whereIn('evidence_id', $items->pluck('id')->all())->orderByDesc('id')->get()
            ->groupBy('evidence_id');
        $reviewers = User::query()->whereIn('id', $items->pluck('reviewer_user_id')->filter()->all())
            ->pluck('email', 'id');
        $provenance = $this->provenanceFor($items);

        return Inertia::render('sifi/evidence-intake', [
            'candidate' => ['id' => $candidate->id, 'title' => $candidate->title, 'review_state' => $candidate->review_state],
            'linkedSignalIds' => $ids,
            'investigationUrl' => route('sifi.investigations.show', $candidate->investigation_id, false),
            'evidence' => $items->map(fn ($e) => array_merge($this->present($e), [
                'reviewer_email' => $reviewers[$e->reviewer_user_id] ?? null,
                'source_provenance' => $provenance[$e->id] ?? null,
                'review_readiness' => $this->readiness($e, isset($linked[$e->signal_id])),
                'history' => ($history[$e->id] ?? collect())->map(fn ($r) => [
                    'action' => $r->action, 'revision' => (int) $r->revision, 'created_at' => $r->created_at,
                ])->all(),
                'reviews' => ($reviews[$e->id] ?? collect())->map(fn ($r) => [
                    'id' => $r->id, 'review_finding' => $r->review_finding,
                    'review_notes' => $r->review_notes, 'created_at' => $r->created_at,
                    'evidence_revision' => (int) $r->evidence_revision,
                ])->all(),
            ]))->all(),
            'scope' => 'LOCAL_TRIAGE_ONLY_NOT_ADMITTED',
        ]);
    }

    public function store(StoreEvidenceDraftRequest $request, SifiIssueCandidate $candidate): RedirectResponse
    {
        Gate::authorize('create', [SifiEvidenceDraft::class, $candidate]);
        $data = $request->validated();
        $uid = (int) $request->user()->id;
        DB::transaction(function () use ($candidate, $data, $uid): void {
            $locked = SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->user_id === $uid, 403);
            $isLinked = $this->linkedReferences($locked)->where('signal_id', strtolower($data['signal_id']))->exists();
            if (! $isLinked) {
                throw ValidationException::withMessages(['signal_id' => 'Referensi tidak ada di investigasi ini.']);
            }
            if (SifiEvidenceDraft::query()->where('candidate_id', $locked->id)->where('signal_id', strtolower($data['signal_id']))->exists()) {
                throw ValidationException::withMessages(['signal_id' => 'Referensi sudah tercatat; sunting draf yang ada.']);
            }
            $row = SifiEvidenceDraft::create([
                'id' => (string) Str::uuid(), 'candidate_id' => $locked->id, 'owner_user_id' => $uid,
                'signal_id' => strtolower($data['signal_id']), 'claim_text' => trim($data['claim_text']),
                'rationale' => $data['rationale'] ?? null, 'known_gaps' => $data['known_gaps'] ?? null,
                'relationship' => $data['relationship'], 'workflow_state' => 'DRAFT', 'revision' => 1,
            ]);
            $this->audit($row, $uid, 'CREATED');
        });

        return redirect()->route('sifi.evidence.index', $candidate->id);
    }

    public function update(UpdateEvidenceDraftRequest $request, SifiEvidenceDraft $evidence): RedirectResponse
    {
        Gate::authorize('update', $evidence);
        $data = $request->validated();
        $uid = (int) $request->user()->id;
        DB::transaction(function () use ($evidence, $data, $uid): void {
            $row = SifiEvidenceDraft::query()->whereKey($evidence->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $row);
            $this->revision($row, (int) $data['revision']);
            $afterReview = $row->workflow_state === 'REVIEW_RECORDED';
            if ($afterReview && ! DB::table('sifi_evidence_review_notes')->where('evidence_id', $row->id)
                ->where('evidence_revision', $row->revision)->exists()) {
                throw ValidationException::withMessages(['follow_up' => 'Catatan telaah untuk revisi ini tidak tersedia.']);
            }
            $fields = ['claim_text' => trim($data['claim_text']), 'rationale' => $data['rationale'] ?? null,
                'known_gaps' => $data['known_gaps'] ?? null, 'relationship' => $data['relationship']];
            $row->fill($fields);
            if (! $row->isDirty()) {
                if ($afterReview) {
                    throw ValidationException::withMessages(['follow_up' => 'Ubah isi draf berdasarkan catatan telaah sebelum menyimpan revisi.']);
                }

                return;
            }
            if ($afterReview) {
                $row->workflow_state = 'DRAFT';
                $row->reviewer_user_id = null;
            }
            $row->revision++;
            $row->save();
            $this->audit($row, $uid, $afterReview ? 'REVISED_AFTER_REVIEW' : 'EDITED');
        });

        return redirect()->route('sifi.evidence.index', $evidence->candidate_id);
    }

    public function assign(AssignEvidenceReviewerRequest $request, SifiEvidenceDraft $evidence): RedirectResponse
    {
        Gate::authorize('assign', $evidence);
        $data = $request->validated();
        $uid = (int) $request->user()->id;
        DB::transaction(function () use ($evidence, $data, $uid): void {
            $row = SifiEvidenceDraft::query()->whereKey($evidence->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $row->owner_user_id === $uid && $row->workflow_state === 'DRAFT', 403);
            $this->revision($row, (int) $data['revision']);
            $candidate = SifiIssueCandidate::query()->whereKey($row->candidate_id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $candidate->user_id === $uid, 403);
            $linked = $this->linkedReferences($candidate)->where('signal_id', $row->signal_id)->exists();
            $blockers = $this->readiness($row, $linked)['blockers'];
            if ($blockers !== []) {
                throw ValidationException::withMessages($blockers);
            }
            $reviewer = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['reviewer_email']))])
                ->whereNotNull('email_verified_at')->where('id', '!=', $uid)->first();
            if ($reviewer === null) {
                throw ValidationException::withMessages([
                    'reviewer_email' => 'Pengguna Laravel terverifikasi yang berbeda dari pemilik tidak tersedia.',
                ]);
            }
            $reviewerId = (int) $reviewer->id;
            if ($reviewerId <= 0) {
                throw ValidationException::withMessages(['reviewer_email' => 'ID penelaah tidak valid.']);
            }
            $row->reviewer_user_id = $reviewerId;
            $row->workflow_state = 'REVIEW_REQUESTED';
            $row->revision++;
            $row->save();
            $previouslyReviewed = DB::table('sifi_evidence_review_notes')->where('evidence_id', $row->id)->exists();
            $this->audit($row, $uid, $previouslyReviewed ? 'REVIEW_RESUBMITTED' : 'REVIEW_REQUESTED');
        });

        return redirect()->route('sifi.evidence.index', $evidence->candidate_id);
    }

    public function reviewQueue(Request $request): Response
    {
        Gate::authorize('viewAny', SifiEvidenceDraft::class);
        $items = SifiEvidenceDraft::query()->where('reviewer_user_id', $request->user()->id)
            ->where('workflow_state', 'REVIEW_REQUESTED')->whereColumn('owner_user_id', '!=', 'reviewer_user_id')
            ->orderBy('updated_at')->limit(75)->get();
        $provenance = $this->provenanceFor($items);

        return Inertia::render('sifi/evidence-review-queue', [
            'evidence' => $items->map(fn ($e) => array_merge($this->present($e), ['source_provenance' => $provenance[$e->id] ?? null]))->all(),
            'scope' => 'REVIEW_NOT_ADMISSION',
        ]);
    }

    public function review(SubmitEvidenceReviewRequest $request, SifiEvidenceDraft $evidence): RedirectResponse
    {
        Gate::authorize('review', $evidence);
        $data = $request->validated();
        $uid = (int) $request->user()->id;
        DB::transaction(function () use ($evidence, $data, $uid): void {
            $row = SifiEvidenceDraft::query()->whereKey($evidence->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $row->reviewer_user_id === $uid && (int) $row->owner_user_id !== $uid && $row->workflow_state === 'REVIEW_REQUESTED', 403);
            $this->revision($row, (int) $data['revision']);
            $row->workflow_state = 'REVIEW_RECORDED';
            $row->revision++;
            $row->save();
            DB::table('sifi_evidence_review_notes')->insert([
                'evidence_id' => $row->id, 'reviewer_user_id' => $uid,
                'evidence_revision' => $row->revision, 'review_finding' => $data['review_finding'],
                'review_notes' => trim($data['review_notes']), 'created_at' => now(),
            ]);
            $this->audit($row, $uid, 'REVIEW_RECORDED', [
                'review_finding' => $data['review_finding'], 'review_notes' => trim($data['review_notes']),
            ]);
        });

        return redirect()->route('sifi.evidence.review-queue');
    }

    private function linkedReferences(SifiIssueCandidate $candidate): Builder
    {
        $references = DB::table('sifi_investigation_signals')->where('investigation_id', $candidate->investigation_id);
        if (Schema::hasTable('sifi_real_source_signals')) {
            $references->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('sifi_real_source_signals as real_signal')
                ->whereColumn('real_signal.id', 'sifi_investigation_signals.signal_id')
                ->where('real_signal.owner_user_id', '!=', $candidate->user_id));
        }

        return $references;
    }

    /** @return array{state: string, blockers: array<string, string>} */
    private function readiness(SifiEvidenceDraft $draft, bool $linked): array
    {
        $blockers = [];
        if ($draft->workflow_state === 'DRAFT') {
            if (! $linked) {
                $blockers['signal_id'] = 'Tautkan kembali referensi signal pada investigasi.';
            }
            if (trim($draft->claim_text) === '') {
                $blockers['claim_text'] = 'Tulis dan simpan klaim yang akan ditelaah.';
            }
        }

        return [
            'state' => match ($draft->workflow_state) {
                'DRAFT' => $blockers === [] ? 'READY_TO_REQUEST' : 'NEEDS_CONTEXT',
                'REVIEW_REQUESTED' => 'WAITING_REVIEW',
                'REVIEW_RECORDED' => 'REVIEW_RECORDED',
                default => 'UNAVAILABLE',
            },
            'blockers' => $blockers,
        ];
    }

    /** Resolve only real signals still linked to the evidence owner's candidate. */
    /** @param Collection<int, SifiEvidenceDraft> $items
     * @return array<string, array<string, mixed>>
     */
    private function provenanceFor(Collection $items): array
    {
        if ($items->isEmpty() || ! Schema::hasTable('sifi_real_source_signals')) {
            return [];
        }

        return DB::table('sifi_evidence_drafts as evidence')
            ->join('sifi_issue_candidates as candidate', 'candidate.id', '=', 'evidence.candidate_id')
            ->join('sifi_investigation_signals as link', fn ($join) => $join
                ->on('link.investigation_id', '=', 'candidate.investigation_id')
                ->on('link.signal_id', '=', 'evidence.signal_id'))
            ->join('sifi_real_source_signals as signal', fn ($join) => $join
                ->on('signal.id', '=', 'evidence.signal_id')
                ->on('signal.owner_user_id', '=', 'evidence.owner_user_id'))
            ->whereColumn('candidate.user_id', 'evidence.owner_user_id')
            ->whereIn('evidence.id', $items->pluck('id'))
            ->get(['evidence.id as evidence_id', 'signal.session_id', 'signal.source_url', 'signal.source_sha256',
                'signal.image_sha256', 'signal.brief_sha256', 'signal.provenance_mode', 'signal.review_state'])
            ->mapWithKeys(fn ($row) => [$row->evidence_id => [
                'session_id' => $row->session_id, 'source_url' => $row->source_url, 'source_sha256' => $row->source_sha256,
                'image_sha256' => $row->image_sha256, 'brief_sha256' => $row->brief_sha256,
                'provenance_mode' => $row->provenance_mode, 'review_state' => $row->review_state,
                'rights_status' => 'UNASSESSED', 'evidence_admission' => 'NONE',
            ]])->all();
    }

    private function revision(SifiEvidenceDraft $row, int $expected): void
    {
        if ((int) $row->revision !== $expected) {
            throw ValidationException::withMessages([
                'revision' => 'Revisi berubah; muat ulang halaman sebelum melanjutkan.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function present(SifiEvidenceDraft $row): array
    {
        return $row->only(['id', 'candidate_id', 'signal_id', 'claim_text', 'rationale', 'known_gaps',
            'relationship', 'workflow_state', 'revision', 'created_at', 'updated_at']);
    }

    /** @param array<string, mixed> $additional */
    private function audit(SifiEvidenceDraft $row, int $actor, string $action, array $additional = []): void
    {
        DB::table('sifi_evidence_draft_audit')->insert([
            'evidence_id' => $row->id, 'actor_user_id' => $actor, 'revision' => $row->revision,
            'action' => $action, 'snapshot_json' => json_encode(array_merge($this->present($row),$additional),JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
