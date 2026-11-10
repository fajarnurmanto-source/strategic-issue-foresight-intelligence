<?php
namespace App\Services\Sifi;
use App\Models\SifiIssueCandidate;
use App\Models\SifiIssueCaseDraft;
use App\Models\SifiTrajectoryDraft;
use App\Models\SifiForesightDraft;
use Illuminate\Support\Facades\DB;
/** Canonical, human-reviewable input for briefing; does not call external services. */
final class ExecutiveBriefSource {
 /** @return array{SifiIssueCaseDraft, SifiTrajectoryDraft, SifiForesightDraft} */
 public function parents(SifiIssueCandidate $candidate,int $uid,bool $lock=false):array {
  $caseQuery=SifiIssueCaseDraft::query()->where('candidate_id',$candidate->id);
  if($lock)$caseQuery->lockForUpdate();
  $case=$caseQuery->firstOrFail();
  $trajectoryQuery=SifiTrajectoryDraft::query()->where('case_id',$case->id);
  if($lock)$trajectoryQuery->lockForUpdate();
  $trajectory=$trajectoryQuery->firstOrFail();
  $foresightQuery=SifiForesightDraft::query()->where('trajectory_id',$trajectory->id);
  if($lock)$foresightQuery->lockForUpdate();
  $foresight=$foresightQuery->firstOrFail();
  abort_unless((int)$candidate->user_id===$uid&&(int)$case->owner_user_id===$uid&&
   (int)$trajectory->owner_user_id===$uid&&(int)$foresight->owner_user_id===$uid,403);
  abort_unless($case->verification_state==='NOT_VERIFIED',409);
  return [$case,$trajectory,$foresight];
 }
 /** @return array<string, mixed> */
 public function assemble(SifiIssueCandidate $candidate,SifiIssueCaseDraft $case,
  SifiTrajectoryDraft $trajectory,SifiForesightDraft $foresight):array {
  $counts=[
   'claim_drafts'=>(int)DB::table('sifi_claim_drafts')->where('candidate_id',$candidate->id)->count(),
   'event_hypotheses'=>(int)DB::table('sifi_event_hypotheses')->where('candidate_id',$candidate->id)->count(),
   'possible_links'=>(int)DB::table('sifi_claim_event_links')->where('candidate_id',$candidate->id)->count(),
   'evidence_drafts'=>(int)DB::table('sifi_evidence_drafts')->where('candidate_id',$candidate->id)->count(),
  ];
  // Track actual draft record changes, not just counts. Only hashes are displayed.
  $recordDigests=[];
  foreach(['sifi_claim_drafts','sifi_event_hypotheses','sifi_claim_event_links','sifi_evidence_drafts'] as $table){
   $rows=DB::table($table)->where('candidate_id',$candidate->id)->orderBy('id')->get()->all();
   $recordDigests[$table]=hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
  }
  $evidenceIds=DB::table('sifi_evidence_drafts')->where('candidate_id',$candidate->id)->pluck('id')->all();
  $reviewNotes=$evidenceIds?DB::table('sifi_evidence_review_notes')->whereIn('evidence_id',$evidenceIds)
    ->orderBy('id')->get()->all():[];
  $recordDigests['sifi_evidence_review_notes']=hash('sha256',json_encode($reviewNotes,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
  $last=DB::table('sifi_foresight_advisories')->where('draft_id',$foresight->id)->orderByDesc('id')->first();
  $advisory=null;
  if($last) {
   $j=json_decode($last->result_json,true);
   $advisory=['snapshot_id'=>(int)$last->id,'draft_revision'=>(int)$last->draft_revision,
    'marker'=>is_array($j)?($j['foresight_marker']??'UNKNOWN'): 'UNKNOWN',
    'created_at'=>(string)$last->created_at,
    'input_revision_matches'=>(int)$last->draft_revision===(int)$foresight->revision];
  }
  $lineage=app(SourceObservationPipeline::class)->lineage($candidate);
  return [
   'candidate'=>['id'=>$candidate->id,'revision'=>(int)$candidate->revision,
    'title'=>$candidate->title,'hypothesis'=>$candidate->issue_hypothesis,
    'evidence_gaps'=>$candidate->evidence_gaps,'next_checks'=>$candidate->next_checks],
   'issue_case'=>['id'=>$case->id,'revision'=>(int)$case->revision,'title'=>$case->title,
    'domain'=>$case->issue_domain,'geography'=>$case->geography_label,
    'lifecycle'=>$case->lifecycle_state,'verification_state'=>'NOT_VERIFIED'],
   'trajectory'=>['id'=>$trajectory->id,'revision'=>(int)$trajectory->revision,
    'horizon'=>$trajectory->horizon,'direction'=>$trajectory->trajectory_hypothesis,
    'risk'=>$trajectory->risk_hypothesis,'opportunity'=>$trajectory->opportunity_hypothesis,
    'watch_condition'=>$trajectory->watch_condition,'uncertainty'=>$trajectory->uncertainty_note],
   'foresight'=>['id'=>$foresight->id,'revision'=>(int)$foresight->revision,
    'baseline'=>$foresight->baseline_scenario,'alternative'=>$foresight->alternative_scenario,
    'stress'=>$foresight->stress_scenario,'drivers'=>$foresight->critical_drivers,
    'falsification'=>$foresight->falsification_checks,'options'=>$foresight->decision_options,
    'uncertainty'=>$foresight->uncertainty_note],
   'draft_counts'=>$counts,'source_record_digests'=>$recordDigests,'foresight_advisory'=>$advisory,
   ...($lineage !== null ? ['source_lineage'=>$lineage] : []),
   'data_mode'=>($lineage['mode']??null)==='MOCK_ACQUISITION_ONLY'?'MOCK_ACQUISITION_ONLY':(in_array($lineage['connector']??null,['wb_gdp','wb_acquisition'],true)?'REAL_PUBLIC_ARCHIVE':'OFFLINE_FIXTURE_ONLY'),'claim_verified'=>false,'event_verified'=>false,
   'evidence_admission'=>'NOT_EVALUATED','decision_authorized'=>false,'forecast_available'=>false,
  ];
 }
 /** @param array<string, mixed> $snapshot */
 public function digest(array $snapshot):string {
  return hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
 }
}
