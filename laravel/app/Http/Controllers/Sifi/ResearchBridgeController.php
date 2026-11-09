<?php
namespace App\Http\Controllers\Sifi;
use App\Http\Controllers\Controller;
use App\Models\SifiMonitoringObject;
use App\Services\Sifi\{ResearchRetrievalWorkspace,ResearchSessionWorkspace};
use Illuminate\Http\{Request,RedirectResponse};
use Illuminate\Support\Facades\Gate;
use Inertia\{Inertia,Response};

final class ResearchBridgeController extends Controller
{
 private function forbidden():array{return array_fill_keys(['user_id','provider','url','body','document','result_json','rights','grant','permit','receipt','evidence_admitted','verified','ai','schedule'],['prohibited']);}
 public function index(Request $r,SifiMonitoringObject $object,ResearchRetrievalWorkspace $w,ResearchSessionWorkspace $sessions):Response{
  Gate::authorize('view',$object);$v=$r->validate(['session'=>['nullable','uuid']]);$history=$sessions->history($r->user(),$object);$choices=array_values(array_filter($history,fn($s)=>!$s['expired']&&$s['object_revision']===$object->revision));$selected=$v['session']??($choices[0]['id']??null);
  return Inertia::render('sifi/research-bridge',['object'=>$object->only(['id','name','revision','state']),'sessions'=>array_map(fn($s)=>array_intersect_key($s,array_flip(['id','question'])),$choices),'selected'=>$selected,'search'=>$selected?$w->search($r->user(),$object,$selected):null,'retrievals'=>$w->history($r->user(),$object)]);
 }
 public function decide(Request $r,SifiMonitoringObject $object,ResearchRetrievalWorkspace $w):RedirectResponse{Gate::authorize('update',$object);$v=$r->validate($this->forbidden()+['object_revision'=>['required','integer','min:1'],'session_id'=>['required','uuid'],'result_key'=>['required','string','max:80'],'query_sha256'=>['required','regex:/\A[a-f0-9]{64}\z/']]);$w->decide($r->user(),$object,$v);return redirect()->route('sifi.research-bridge.index',['object'=>$object->id,'session'=>$v['session_id']]);}
 public function snapshot(Request $r,SifiMonitoringObject $object,string $retrieval,ResearchRetrievalWorkspace $w):RedirectResponse{Gate::authorize('update',$object);$v=$r->validate($this->forbidden()+['object_revision'=>['required','integer','min:1'],'revision'=>['required','integer','min:1'],'confirm_fixture'=>['required','accepted']]);$w->snapshot($r->user(),$object,$retrieval,$v);return redirect()->route('sifi.research-bridge.index',['object'=>$object->id]);}
 public function draft(Request $r,SifiMonitoringObject $object,string $retrieval,ResearchRetrievalWorkspace $w):RedirectResponse{Gate::authorize('update',$object);$v=$r->validate($this->forbidden()+['object_revision'=>['required','integer','min:1'],'revision'=>['required','integer','min:1'],'confirm_fixture'=>['required','accepted'],'analyst_note'=>['required','string','min:20','max:1000']]);$w->import($r->user(),$object,$retrieval,$v);return redirect()->route('sifi.research-bridge.index',['object'=>$object->id]);}
}
