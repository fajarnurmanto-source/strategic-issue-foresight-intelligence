<?php
namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\AiModelController;
use App\Models\SifiIssueCandidate;
use App\Services\Sifi\Ai\{UserAiSettings,ProviderHttpTransport,ConfiguredHttpAiAdapter,SecureAiRuntime,AiTaskCapabilityRegistry};
use App\Data\Sifi\AiInferenceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

final class AiIssueExplanationController extends Controller
{
    private function notes(SifiIssueCandidate $c): array {return ['title'=>mb_substr($c->title??'',0,300),'hypothesis'=>mb_substr($c->issue_hypothesis??'',0,3000),'gaps'=>mb_substr($c->evidence_gaps??'',0,2000),'next_checks'=>mb_substr($c->next_checks??'',0,2000)];}
    public function show(SifiIssueCandidate $candidate){Gate::authorize('view',$candidate);return Inertia::render('sifi/ai-issue-explanation',['candidate'=>['id'=>$candidate->id,'revision'=>$candidate->revision],'notes'=>$this->notes($candidate),'outcome'=>session('ai_explanation')]);}
    public function run(Request $r,SifiIssueCandidate $candidate,UserAiSettings $store,ProviderHttpTransport $http){
        Gate::authorize('view',$candidate);$r->validate(['rights_confirmed'=>'accepted','revision'=>'required|integer']);abort_unless((int)$candidate->revision===(int)$r->input('revision'),409);
        $notes=$this->notes($candidate);if(trim($notes['hypothesis'])==='')return back()->with('ai_explanation',['status'=>'ABSTAIN','reason'=>'Hipotesis belum cukup.']);
        $data=$store->read((int)$r->user()->id);$selection=$data['assignments']['summary_draft']??[];$provider=$data['providers'][$selection['provider']??'']??null;
        if(!$data['enabled']||!$provider||$provider['status']!=='CONNECTED')return back()->with('ai_explanation',['status'=>'UNAVAILABLE','reason'=>'Pilih model yang telah terhubung dan aktifkan AI melalui pengaturan.']);
        $adapter=new ConfiguredHttpAiAdapter($http,$provider,$selection['model'],$notes);$runtime=new SecureAiRuntime($adapter,new AiTaskCapabilityRegistry);
        $result=$runtime->invoke($r->user(),(int)$r->user()->id,['enabled'=>true,...$selection],new AiInferenceRequest('summary_draft',['candidate:'.$candidate->id.':revision:'.$candidate->revision],AiModelController::schema(),10000,'INTERNAL',(string)Str::uuid()));
        return back()->with('ai_explanation',['candidate_id'=>$candidate->id,'revision'=>$candidate->revision,'status'=>$result->status,'output'=>$result->structuredOutput,'reason'=>$result->status==='AVAILABLE'?null:'Provider gagal atau output tidak sah.','reference'=>'candidate:'.$candidate->id.':revision:'.$candidate->revision,'provider'=>$provider['label'],'model'=>$selection['model']]);
    }
}
