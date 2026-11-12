<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sifi\StoreIssueCandidateRequest;
use App\Http\Requests\Sifi\UpdateIssueCandidateRequest;
use App\Models\SifiInvestigation;
use App\Models\SifiIssueCandidate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Laravel-native local workflow; no Python DB write, admission, or automatic Issue Object. */
final class IssueCandidateController extends Controller
{
    public function index(Request $request): Response
    {
        $candidates = SifiIssueCandidate::query()->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')->paginate(25)->through(static fn (SifiIssueCandidate $item) => [
                'id' => $item->id, 'title' => $item->title,
                'investigation_id' => $item->investigation_id,
                'review_state' => $item->review_state, 'revision' => $item->revision,
                'updated_at' => $item->updated_at?->toIso8601String(),
                'issue_hypothesis' => $item->issue_hypothesis,
            ]);

        return Inertia::render('sifi/issue-candidates-index', [
            'candidates' => $candidates, 'scope' => 'LOCAL_HUMAN_DRAFT_ONLY',
        ]);
    }

    public function store(StoreIssueCandidateRequest $request): RedirectResponse
    {
        $investigation = SifiInvestigation::query()->findOrFail((string) $request->validated('investigation_id'));
        Gate::authorize('create', [SifiIssueCandidate::class, $investigation]);

        $candidate = DB::transaction(function () use ($investigation, $request): SifiIssueCandidate {
            // Lock the originating investigation so repeated submissions do not create new candidates.
            SifiInvestigation::query()->whereKey($investigation->id)->lockForUpdate()->firstOrFail();
            $existing = SifiIssueCandidate::where('investigation_id', $investigation->id)->first();
            if ($existing !== null) {
                return $existing;
            }
            $candidate = SifiIssueCandidate::create([
                'id' => (string) Str::uuid(), 'investigation_id' => $investigation->id,
                'user_id' => $request->user()->id,
                'title' => mb_substr($investigation->title, 0, 180),
                'review_state' => 'DRAFT', 'revision' => 1,
            ]);
            $this->audit($candidate, (int) $request->user()->id, 1, 'CREATED', [
                'from_investigation' => $investigation->id,
                'status' => 'DRAFT',
                'origin_provenance' => 'OFFLINE_FIXTURE_ONLY',
            ]);

            return $candidate;
        });

        return redirect()->route('sifi.issue-candidates.show', $candidate->id);
    }

    public function show(Request $request, SifiIssueCandidate $candidate): Response
    {
        Gate::authorize('view', $candidate);
        $audit = DB::table('sifi_issue_candidate_audit')->where('candidate_id', $candidate->id)
            ->orderByDesc('revision')->limit(30)
            ->get(['revision', 'action', 'created_at'])->map(static fn ($row) => [
                'revision' => (int) $row->revision, 'action' => $row->action, 'created_at' => $row->created_at,
            ])->all();

        $assessments = DB::table('sifi_intelligence_assessments')
            ->where('candidate_id', $candidate->id)->orderByDesc('id')->limit(10)
            ->get(['id', 'candidate_revision', 'status', 'result_json', 'created_at'])
            ->map(static fn ($row) => [
                'id' => (int) $row->id,
                'candidate_revision' => (int) $row->candidate_revision,
                'status' => $row->status,
                'result' => json_decode($row->result_json, true, 32, JSON_THROW_ON_ERROR),
                'created_at' => $row->created_at,
            ])->all();

        return Inertia::render('sifi/issue-candidate-detail', [
            'intelligence' => $assessments,
            'attachedSignalIds' => DB::table('sifi_investigation_signals')
                ->where('investigation_id', $candidate->investigation_id)
                ->orderBy('signal_id')->pluck('signal_id')->all(),
            'candidate' => [
                'id' => $candidate->id, 'investigation_id' => $candidate->investigation_id,
                'title' => $candidate->title, 'issue_hypothesis' => $candidate->issue_hypothesis ?? '',
                'exposure_notes' => $candidate->exposure_notes ?? '',
                'evidence_gaps' => $candidate->evidence_gaps ?? '',
                'next_checks' => $candidate->next_checks ?? '',
                'review_state' => $candidate->review_state, 'revision' => $candidate->revision,
            ],
            'audit' => $audit, 'scope' => 'LOCAL_HUMAN_DRAFT_ONLY',
        ]);
    }

    public function update(UpdateIssueCandidateRequest $request, SifiIssueCandidate $candidate): RedirectResponse
    {
        $data = $request->validated();
        $userId = (int) $request->user()->id;
        DB::transaction(function () use ($candidate, $data, $userId): void {
            $row = SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $row->user_id === $userId, 403);
            if ((int) $row->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages([
                    'revision' => 'Data diubah dari sesi lain. Muat ulang sebelum menyimpan.',
                ]);
            }
            $fields = [
                'title' => trim($data['title']),
                'issue_hypothesis' => $data['issue_hypothesis'] ?? null,
                'exposure_notes' => $data['exposure_notes'] ?? null,
                'evidence_gaps' => $data['evidence_gaps'] ?? null,
                'next_checks' => $data['next_checks'] ?? null,
                'review_state' => $data['review_state'],
            ];
            if (! collect($fields)->contains(fn ($value, $key) => $row->{$key} !== $value)) {
                return;
            }
            $row->fill($fields);
            $row->revision += 1;
            $row->save();
            $this->audit($row, $userId, (int) $row->revision, 'CANDIDATE_EDITED', $fields);
        });

        return redirect()->route('sifi.issue-candidates.show', $candidate->id);
    }

    /** @param array<string, mixed> $snapshot */
    private function audit(SifiIssueCandidate $candidate, int $userId, int $revision, string $action, array $snapshot): void
    {
        DB::table('sifi_issue_candidate_audit')->insert([
            'candidate_id' => $candidate->id,
            'actor_user_id' => $userId,
            'revision' => $revision,
            'action' => $action,
            'snapshot_json' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
