<?php
namespace App\Http\Controllers\Sifi;
use App\Http\Controllers\Controller;
use App\Models\{SifiMonitoringObject,SifiSourceDraft};
use App\Services\Sifi\{WorldBankResearchWorkspace,ResearchSessionWorkspace,ResearchRetrievalWorkspace,WorldBankGdpSourceAdapter};
use Illuminate\Http\{Request,RedirectResponse};
use Illuminate\Support\Facades\{Gate,DB};
use Inertia\{Inertia,Response};

final class WorldBankResearchController extends Controller
{
 private function forbidden():array{return array_fill_keys(['user_id','endpoint','url','grant','permit','receipt','provider','body','rights','verified','evidence_admitted','cost','retry','scheduler'],['prohibited']);}
 public function index(Request $r,SifiMonitoringObject $object,WorldBankResearchWorkspace $w):Response{
  Gate::authorize('view',$object);$v=$r->validate(['session'=>['nullable','uuid']]);$sessions=array_values(array_filter(app(ResearchSessionWorkspace::class)->history($r->user(),$object),fn($s)=>!$s['expired']&&$s['object_revision']===$object->revision));$selected=$v['session']??($sessions[0]['id']??null);
  $rows=app(ResearchRetrievalWorkspace::class)->history($r->user(),$object);$rows=array_values(array_filter($rows,fn($s)=>str_starts_with($s['state'],'WB_')));foreach($rows as &$row)$row['document_digest']=DB::table('sifi_research_retrievals')->where('id',$row['id'])->value('document_sha256');unset($row);
  return Inertia::render('sifi/worldbank-research',['object'=>$object->only(['id','name','revision','state']),'sessions'=>array_map(fn($s)=>array_intersect_key($s,array_flip(['id','question'])),$sessions),'selected'=>$selected,'authorization'=>$selected?$w->status($r->user(),$object,$selected):null,'retrievals'=>$rows,'sources'=>SifiSourceDraft::where('user_id',$r->user()->id)->whereIn('homepage_url',[WorldBankGdpSourceAdapter::DOCUMENTATION,WorldBankGdpSourceAdapter::ENDPOINT])->where('source_type','OPEN_DATA')->where('collector_state','OFF')->limit(20)->get(['id','name','revision'])->toArray()]);
 }
 public function decide(Request $r,SifiMonitoringObject $object,WorldBankResearchWorkspace $w):RedirectResponse{Gate::authorize('update',$object);$v=$r->validate($this->forbidden()+['session_id'=>['required','uuid'],'object_revision'=>['required','integer','min:1'],'context_sha256'=>['required','regex:/\A[a-f0-9]{64}\z/']]);$w->decide($r->user(),$object,$v);return redirect()->route('sifi.worldbank-research.index',['object'=>$object->id,'session'=>$v['session_id']]);}
 public function acquire(Request $r,SifiMonitoringObject $object,string $retrieval,WorldBankResearchWorkspace $w):RedirectResponse{return $this->perform($r,$object,$retrieval,$w,false);}
 public function recover(Request $r,SifiMonitoringObject $object,string $retrieval,WorldBankResearchWorkspace $w):RedirectResponse{return $this->perform($r,$object,$retrieval,$w,true);}
 private function perform(Request $r,SifiMonitoringObject $o,string $id,WorldBankResearchWorkspace $w,bool $recover):RedirectResponse{Gate::authorize('update',$o);$v=$r->validate($this->forbidden()+['object_revision'=>['required','integer','min:1'],'revision'=>['required','integer','min:1'],'confirm_one_shot'=>['required','accepted']]);$w->acquire($r->user(),$o,$id,$v,$recover);return redirect()->route('sifi.worldbank-research.index',['object'=>$o->id]);}
 public function draft(Request $r,SifiMonitoringObject $object,string $retrieval,WorldBankResearchWorkspace $w):RedirectResponse{Gate::authorize('update',$object);$v=$r->validate($this->forbidden()+['object_revision'=>['required','integer','min:1'],'revision'=>['required','integer','min:1'],'document_sha256'=>['required','regex:/\A[a-f0-9]{64}\z/'],'source_id'=>['required','uuid'],'source_revision'=>['required','integer','min:1'],'analyst_note'=>['required','string','min:20','max:1000'],'confirm_one_shot'=>['required','accepted']]);$w->import($r->user(),$object,$retrieval,$v);return redirect()->route('sifi.worldbank-research.index',['object'=>$object->id]);}
}
