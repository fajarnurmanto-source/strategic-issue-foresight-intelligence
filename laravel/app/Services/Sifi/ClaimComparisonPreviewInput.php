<?php

namespace App\Services\Sifi;

use App\Models\SifiIssueCandidate;
use Illuminate\Support\Facades\DB;

/** Server-owned projection, never client snapshots or file paths. */
class ClaimComparisonPreviewInput
{
    /** @return array<string, mixed> */
    public function read(SifiIssueCandidate $candidate): array
    {
        $claims = DB::table('sifi_claim_drafts as c')->join('sifi_evidence_drafts as e', 'e.id', '=', 'c.evidence_id')
            ->where('c.candidate_id', $candidate->id)->where('e.candidate_id', $candidate->id)->orderBy('c.id')
            ->get(['c.id', 'c.evidence_id', 'e.signal_id', 'c.statement', 'c.attribution', 'c.modality', 'c.revision', 'e.revision as evidence_revision'])
            ->map(fn ($x) => ['id' => $x->id, 'evidence_id' => $x->evidence_id, 'signal_id' => $x->signal_id, 'statement' => $x->statement, 'attribution' => $x->attribution, 'modality' => $x->modality, 'revision' => (int) $x->revision, 'evidence_revision' => (int) $x->evidence_revision])->all();
        $events = DB::table('sifi_event_hypotheses')->where('candidate_id', $candidate->id)->orderBy('id')->get(['id', 'summary', 'time_note', 'place_note', 'revision'])->map(fn ($x) => ['id' => $x->id, 'summary' => $x->summary, 'time_note' => $x->time_note, 'place_note' => $x->place_note, 'revision' => (int) $x->revision])->all();
        $links = DB::table('sifi_claim_event_links')->where('candidate_id', $candidate->id)->orderBy('claim_id')->orderBy('event_id')->get(['claim_id', 'event_id', 'relation'])->map(fn ($x) => ['claim_id' => $x->claim_id, 'event_id' => $x->event_id, 'relation' => $x->relation])->all();

        return ['candidate_id' => $candidate->id, 'candidate_revision' => (int) $candidate->revision, 'claims' => $claims, 'events' => $events, 'links' => $links];
    }
}
