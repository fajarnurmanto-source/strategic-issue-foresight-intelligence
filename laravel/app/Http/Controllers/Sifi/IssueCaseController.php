<?php
namespace App\Http\Controllers\Sifi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sifi\SaveIssueCaseRequest;
use App\Models\SifiIssueCandidate;
use App\Models\SifiIssueCaseDraft;
use App\Services\Sifi\Aq01IssueScopeBridge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** Identity and lifecycle of a local issue-case DRAFT, never an admitted Issue Object. */
final class IssueCaseController extends Controller {
 private function find(SifiIssueCandidate $candidate):?SifiIssueCaseDraft {
  $case=SifiIssueCaseDraft::query()->where('candidate_id',$candidate->id)->first();
  abort_if($case!==null && (int)$case->owner_user_id!==(int)$candidate->user_id,403);
  return $case;
 }
 /** @return array<string, mixed> */
 private function snapshot(SifiIssueCaseDraft $case):array {
  return ['id'=>$case->id,'candidate_id'=>$case->candidate_id,'revision'=>(int)$case->revision,
    'title'=>$case->title,'issue_domain'=>$case->issue_domain,'geography_label'=>$case->geography_label,
    'scope_note'=>$case->scope_note,'lifecycle_state'=>$case->lifecycle_state,
    'coverage_profile_id'=>$case->coverage_profile_id,'verification_state'=>'NOT_VERIFIED'];
 }
 /** @return array<string, mixed>|null */
 private function profile(?string $id,int $userId):?array {
  if(!$id) return null;
  $row=DB::table('sifi_coverage_profiles')->where('id',$id)->where('user_id',$userId)->first();
  if(!$row) throw ValidationException::withMessages(['coverage_profile_id'=>'Profil tidak tersedia untuk pengguna ini.']);
  $domains=json_decode($row->domains_json,true);
  if(!is_array($domains)||!array_is_list($domains))
   throw ValidationException::withMessages(['coverage_profile_id'=>'Domain profil tidak valid.']);
  return ['id'=>$row->id,'name'=>$row->name,'region_name'=>$row->region_name,
    'domains'=>$domains,'revision'=>(int)$row->revision];
 }
 /** @return array<string, mixed> */
 private function input(SifiIssueCandidate $candidate,SifiIssueCaseDraft $case,int $userId):array {
  return ['candidate_id'=>$candidate->id,'candidate_revision'=>(int)$candidate->revision,
    'case'=>['id'=>$case->id,'revision'=>(int)$case->revision,'title'=>$case->title,
      'issue_domain'=>$case->issue_domain,'geography_label'=>$case->geography_label,
      'scope_note'=>$case->scope_note,'lifecycle_state'=>$case->lifecycle_state],
    'coverage'=>$this->profile($case->coverage_profile_id,$userId),
    'claim_count'=>DB::table('sifi_claim_drafts')->where('candidate_id',$candidate->id)->count(),
    'event_count'=>DB::table('sifi_event_hypotheses')->where('candidate_id',$candidate->id)->count(),
    'link_count'=>DB::table('sifi_claim_event_links')->where('candidate_id',$candidate->id)->count()];
 }
 /** @param array<string, mixed> $input */
 private function digest(array $input):string {
  return hash('sha256',json_encode($input,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
 }
 public function show(Request $request,SifiIssueCandidate $candidate):Response {
  Gate::authorize('view',$candidate);$case=$this->find($candidate);
  $profiles=DB::table('sifi_coverage_profiles')->where('user_id',$request->user()->id)
    ->orderBy('name')->get(['id','name','region_name','domains_json','revision'])
    ->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'region_name'=>$p->region_name,
      'domains'=>json_decode($p->domains_json,true),'revision'=>(int)$p->revision])->all();
  $input=$case?$this->input($candidate,$case,(int)$request->user()->id):null;
  $digest=$input?$this->digest($input):null;
  $results=$case?DB::table('sifi_issue_case_advisories')->where('case_id',$case->id)
    ->orderByDesc('id')->limit(15)->get(['id','input_digest','result_json','created_at']):collect();
  $audit=$case?DB::table('sifi_issue_case_audit')->where('case_id',$case->id)
    ->orderByDesc('id')->limit(20)->get(['revision','action','created_at']):collect();
  return Inertia::render('sifi/issue-case-workspace',[
    'candidate'=>['id'=>$candidate->id,'title'=>$candidate->title,'revision'=>(int)$candidate->revision],
    'case'=>$case?$this->snapshot($case):null,'profiles'=>$profiles,
    'counts'=>['claims'=>$input['claim_count']??0,'events'=>$input['event_count']??0,'links'=>$input['link_count']??0],
    'advisories'=>$results->map(fn($r)=>['id'=>$r->id,'is_current'=>$digest!==null && hash_equals($digest,$r->input_digest),
      'result'=>json_decode($r->result_json,true,32,JSON_THROW_ON_ERROR),'created_at'=>$r->created_at])->all(),
    'audit'=>$audit->all(),'mode'=>'LOCAL_ISSUE_CASE_DRAFT_ONLY']);
 }
 public function save(SaveIssueCaseRequest $request,SifiIssueCandidate $candidate):RedirectResponse {
  $v=$request->validated();
  DB::transaction(function() use($request,$candidate,$v):void {
    $locked=SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
    abort_unless((int)$locked->user_id===(int)$request->user()->id,403);
    if((int)$locked->revision!==(int)$v['candidate_revision'])
      throw ValidationException::withMessages(['candidate_revision'=>'Kandidat berubah. Muat ulang halaman.']);
    $profile=$this->profile($v['coverage_profile_id']??null,(int)$request->user()->id);
    $case=SifiIssueCaseDraft::query()->where('candidate_id',$locked->id)->lockForUpdate()->first();
    abort_if($case!==null && (int)$case->owner_user_id!==(int)$request->user()->id,403);
    if(($case===null && isset($v['case_revision'])) || ($case!==null && (!isset($v['case_revision']) || (int)$case->revision!==(int)$v['case_revision'])))
      throw ValidationException::withMessages(['case_revision'=>'Revisi Issue Case berubah. Muat ulang.']);
    $fields=['title'=>trim($v['title']),'issue_domain'=>trim($v['issue_domain']),
      'geography_label'=>trim($v['geography_label']),'scope_note'=>trim($v['scope_note']),
      'lifecycle_state'=>$v['lifecycle_state'],'coverage_profile_id'=>$profile['id']??null];
    if($case===null){
      $case=SifiIssueCaseDraft::query()->create($fields+['id'=>(string)Str::uuid(),
        'candidate_id'=>$locked->id,'owner_user_id'=>(int)$request->user()->id,'revision'=>1]);
      $action='ISSUE_CASE_DRAFT_CREATED';
    }else{
      $case->fill($fields);
      if(!$case->isDirty()) return;
      $case->revision++;$case->save();$action='ISSUE_CASE_DRAFT_UPDATED';
    }
    DB::table('sifi_issue_case_audit')->insert(['case_id'=>$case->id,
      'actor_user_id'=>(int)$request->user()->id,'revision'=>$case->revision,
      'action'=>$action,'snapshot_json'=>json_encode($this->snapshot($case),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
      'created_at'=>now()]);
  });
  return redirect()->route('sifi.issue-case.show',$candidate->id);
 }
 public function assess(SaveIssueCaseRequest $request,SifiIssueCandidate $candidate,Aq01IssueScopeBridge $bridge):RedirectResponse {
  $v=$request->validated();$case=$this->find($candidate);
  if(!$case || !isset($v['case_revision']) || (int)$case->revision!==(int)$v['case_revision'] ||
      (int)$candidate->revision!==(int)$v['candidate_revision'])
    throw ValidationException::withMessages(['case_revision'=>'Simpan dan muat ulang Issue Case.']);
  $input=$this->input($candidate,$case,(int)$request->user()->id);
  $digest=$this->digest($input);
  if(DB::table('sifi_issue_case_advisories')->where('case_id',$case->id)->where('input_digest',$digest)->exists())
    return redirect()->route('sifi.issue-case.show',$candidate->id);
  try {$result=$bridge->assess($input);}
  catch(RuntimeException $e){throw ValidationException::withMessages(['analysis'=>'Python offline/respons tidak sesuai kontrak; hasil tidak disimpan.']);}
  DB::transaction(function()use($candidate,$case,$input,$digest,$request,$result):void {
    $locked=SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
    abort_unless((int)$locked->user_id===(int)$request->user()->id,403);
    $latest=SifiIssueCaseDraft::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
    abort_unless((int)$latest->owner_user_id===(int)$request->user()->id,403);
    if($this->input($locked,$latest,(int)$request->user()->id)!==$input ||
       $this->digest($this->input($locked,$latest,(int)$request->user()->id))!==$digest)
      throw ValidationException::withMessages(['analysis'=>'Input berubah selama pemeriksaan. Hasil tidak disimpan.']);
    if(DB::table('sifi_issue_case_advisories')->where('case_id',$case->id)->where('input_digest',$digest)->exists()) return;
    DB::table('sifi_issue_case_advisories')->insert(['case_id'=>$case->id,
      'actor_user_id'=>(int)$request->user()->id,'case_revision'=>(int)$case->revision,
      'input_digest'=>$digest,'status'=>'ADVISORY_ONLY',
      'result_json'=>json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
      'created_at'=>now()]);
  });
  return redirect()->route('sifi.issue-case.show',$candidate->id);
 }
}
