<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only, user-owned dashboard metrics. Never infers admission or temporal risk. */
final class IntelligenceDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $owner = $request->user()->getAuthIdentifier();
        $own = static fn (string $table, string $field): int => (int) DB::table($table)->where($field, $owner)->count();

        $metrics = [
            'investigations' => $own('sifi_investigations', 'user_id'),
            'candidates' => $own('sifi_issue_candidates', 'user_id'),
            'evidence_drafts' => $own('sifi_evidence_drafts', 'owner_user_id'),
            'review_requested' => (int) DB::table('sifi_issue_candidates')
                ->where('user_id', $owner)->where('review_state', 'REVIEW_REQUESTED')->count(),
            'source_proposals' => $own('sifi_source_drafts', 'user_id'),
            'coverage_profiles' => $own('sifi_coverage_profiles', 'user_id'),
            'brief_drafts' => $own('sifi_executive_briefs', 'owner_user_id'),
        ];

        // The same owned lineage contract already used by Increment 16 Workbench.
        $cases = DB::table('sifi_issue_candidates as c')
            ->where('c.user_id', $owner)
            ->leftJoin('sifi_issue_case_drafts as ic', 'ic.candidate_id', '=', 'c.id')
            ->leftJoin('sifi_trajectory_drafts as tr', 'tr.case_id', '=', 'ic.id')
            ->leftJoin('sifi_foresight_drafts as fs', 'fs.trajectory_id', '=', 'tr.id')
            ->leftJoin('sifi_executive_briefs as br', 'br.candidate_id', '=', 'c.id')
            ->select([
                'c.id', 'c.title', 'c.review_state', 'c.updated_at',
                'ic.id as case_id', 'tr.id as trajectory_id',
                'fs.id as foresight_id', 'br.id as brief_id',
            ])->orderByDesc('c.updated_at')->limit(8)->get()
            ->map(static fn ($c): array => [
                'id' => $c->id,
                'title' => $c->title,
                'review_state' => $c->review_state,
                'updated_at' => $c->updated_at,
                'has_case' => $c->case_id !== null,
                'has_trajectory' => $c->trajectory_id !== null,
                'has_foresight' => $c->foresight_id !== null,
                'has_brief' => $c->brief_id !== null,
            ])->all();

        return Inertia::render('dashboard', [
            'metrics' => $metrics,
            'cases' => $cases,
            'mode' => 'OFFLINE_FIXTURE_ONLY',
            'evidence_state' => 'NOT_EVALUATED',
            'production_authorized' => false,
        ]);
    }
}
