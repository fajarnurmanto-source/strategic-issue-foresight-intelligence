<?php
namespace App\Http\Controllers\Sifi;
use App\Http\Controllers\Controller;
use App\Models\SifiMonitoringObject;
use App\Services\Sifi\MultiSourceResearchWorkspace;
use Illuminate\Http\{Request,RedirectResponse};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\{Rule,ValidationException};
use Inertia\{Inertia,Response};

final class MultiSourceResearchController extends Controller
{
    public function choose(Request $r):Response{return $this->page($r,null);}
    public function index(Request $r,SifiMonitoringObject $object):Response{Gate::authorize('view',$object);return $this->page($r,$object);}
    private function page(Request $r,?SifiMonitoringObject $o):Response
    {
        return Inertia::render('sifi/multi-source-research',['object'=>$o?->only(['id','name','revision','state','keywords_json','aliases_json']),
            'objectChoices'=>SifiMonitoringObject::where('user_id',$r->user()->id)->orderByDesc('updated_at')->limit(100)->get(['id','name','state'])->toArray(),
            'history'=>$o?app(MultiSourceResearchWorkspace::class)->history($r->user(),$o):[],
            'collector'=>app(\App\Services\Sifi\RealCollectorWorkspace::class)->status($r->user(),$o),'providerIds'=>MultiSourceResearchWorkspace::PROVIDERS,'scenarios'=>($this->scenarios($r,$o)),'selectedSessionId'=>$r->query('research')]);
    }
    private function scenarios(Request $r,?SifiMonitoringObject $o):array
    {
        return array_values(array_filter(MultiSourceResearchWorkspace::SCENARIOS,fn($s)=>($s!=='crossref_live'||app(\App\Services\Sifi\RealCollectorWorkspace::class)->eligible($r->user(),$o))&&(!str_starts_with($s,'api_')||(app()->environment('testing')&&config('sifi.real_search_fixture')===true))));
    }
    private function only(Request $r,array $keys):void
    {
        if(array_diff(array_keys($r->all()),[...$keys,'_token']))throw ValidationException::withMessages(['research'=>'Field tambahan tidak diizinkan; URL, provider, rights dan grant ditentukan server.']);
    }
    public function search(Request $r,SifiMonitoringObject $object,MultiSourceResearchWorkspace $w):RedirectResponse
    {
        Gate::authorize('update',$object);$this->only($r,['question','object_revision','scenario','query_limit','result_limit','submission_key','multilingual_options','feed_options']);
        $v=$r->validate(['feed_options'=>['sometimes','array:sources,pages,language,region,period,provider_state,start_date,end_date'],'feed_options.sources'=>['required_if:scenario,feed','array','min:1','max:3'],'feed_options.sources.*'=>['string',Rule::in(\App\Services\Sifi\FeedSourcePack::IDS),'distinct'],'feed_options.pages'=>['sometimes','integer','min:1','max:2'],'feed_options.language'=>['sometimes',Rule::in(['ALL','UNKNOWN',...\App\Services\Sifi\MultilingualQueryPlan::LANGUAGES])],'feed_options.region'=>['sometimes',Rule::in(['ALL','EXACT_OBJECT','WIDER_REGION'])],'feed_options.period'=>['sometimes',Rule::in(['ALL','PUBLISHED','UNKNOWN'])],'feed_options.provider_state'=>['sometimes',Rule::in(['normal','partial','no_result','quota','timeout'])],'feed_options.start_date'=>['nullable','required_with:feed_options.end_date','date_format:Y-m-d','before:feed_options.end_date'],'feed_options.end_date'=>['nullable','required_with:feed_options.start_date','date_format:Y-m-d','after:feed_options.start_date'],'multilingual_options'=>['sometimes','array:language_mode,languages,result_language,region,period,source_type,start_date,end_date,provider_state'],'multilingual_options.language_mode'=>['sometimes',Rule::in(['AUTO','MANUAL'])],'multilingual_options.languages'=>['sometimes','array','min:1','max:5'],'multilingual_options.languages.*'=>['string',Rule::in(\App\Services\Sifi\MultilingualQueryPlan::LANGUAGES),'distinct'],'multilingual_options.result_language'=>['sometimes',Rule::in(['ALL','UNKNOWN',...\App\Services\Sifi\MultilingualQueryPlan::LANGUAGES])],'multilingual_options.region'=>['sometimes',Rule::in(['ALL','EXACT_OBJECT','WIDER_REGION'])],'multilingual_options.period'=>['sometimes',Rule::in(['ALL_CONTEXT','PUBLISHED','EVENT','LATER','UNKNOWN'])],'multilingual_options.source_type'=>['sometimes',Rule::in(['ALL','OFFICIAL','NEWS','ACADEMIC','OPEN_DATA','REGULATORY','RSS_ATOM','PUBLIC_API'])],'multilingual_options.provider_state'=>['sometimes',Rule::in(['normal','partial','no_result','quota','timeout'])],'multilingual_options.start_date'=>['nullable','required_with:multilingual_options.end_date','date_format:Y-m-d','before:multilingual_options.end_date'],'multilingual_options.end_date'=>['nullable','required_with:multilingual_options.start_date','date_format:Y-m-d','after:multilingual_options.start_date'],'question'=>['required','string','min:8','max:600'],'object_revision'=>['required','integer','min:1'],'scenario'=>['required',Rule::in($this->scenarios($r,$object))],'query_limit'=>['required','integer','min:1','max:2'],'result_limit'=>['required','integer','min:1','max:10'],'submission_key'=>['required','uuid']]);
        if($v['scenario']!=='multilingual'&&array_key_exists('multilingual_options',$v))throw ValidationException::withMessages(['research'=>'Pilihan bahasa hanya untuk mode multilingual.']);if($v['scenario']!=='feed'&&array_key_exists('feed_options',$v))throw ValidationException::withMessages(['research'=>'Pilihan sumber hanya untuk mode feed.']);$id=$w->create($r->user(),$object,$v);return redirect()->route('sifi.multi-research.index',['object'=>$object->id,'research'=>$id]);
    }
    public function save(Request $r,SifiMonitoringObject $object,string $session,MultiSourceResearchWorkspace $w):RedirectResponse
    {
        Gate::authorize('update',$object);$this->only($r,['revision','object_revision','envelope_sha256','selected_ids','analyst_note']);
        $v=$r->validate(['multilingual_options'=>['sometimes','array:language_mode,languages,result_language,region,period,source_type,start_date,end_date,provider_state'],'multilingual_options.language_mode'=>['sometimes',Rule::in(['AUTO','MANUAL'])],'multilingual_options.languages'=>['sometimes','array','min:1','max:5'],'multilingual_options.languages.*'=>['string',Rule::in(\App\Services\Sifi\MultilingualQueryPlan::LANGUAGES),'distinct'],'multilingual_options.result_language'=>['sometimes',Rule::in(['ALL','UNKNOWN',...\App\Services\Sifi\MultilingualQueryPlan::LANGUAGES])],'multilingual_options.region'=>['sometimes',Rule::in(['ALL','EXACT_OBJECT','WIDER_REGION'])],'multilingual_options.period'=>['sometimes',Rule::in(['ALL_CONTEXT','PUBLISHED','EVENT','LATER','UNKNOWN'])],'multilingual_options.source_type'=>['sometimes',Rule::in(['ALL','OFFICIAL','NEWS','ACADEMIC','OPEN_DATA','REGULATORY','RSS_ATOM','PUBLIC_API'])],'multilingual_options.provider_state'=>['sometimes',Rule::in(['normal','partial','no_result','quota','timeout'])],'multilingual_options.start_date'=>['nullable','required_with:multilingual_options.end_date','date_format:Y-m-d','before:multilingual_options.end_date'],'multilingual_options.end_date'=>['nullable','required_with:multilingual_options.start_date','date_format:Y-m-d','after:multilingual_options.start_date'],'revision'=>['required','integer','min:1'],'object_revision'=>['required','integer','min:1'],'envelope_sha256'=>['required','regex:/^[a-f0-9]{64}$/'],'selected_ids'=>['present','array','max:2'],'selected_ids.*'=>['required','string','regex:/^[a-f0-9]{64}$/','distinct'],'analyst_note'=>['present','nullable','string','max:2000']]);
        $w->save($r->user(),$object,$session,$v);return redirect()->route('sifi.multi-research.index',['object'=>$object->id,'research'=>$session]);
    }
    public function feedDraft(Request $r,SifiMonitoringObject $object,string $session):RedirectResponse
    {
        Gate::authorize('update',$object);$this->only($r,['revision','object_revision','envelope_sha256']);$v=$r->validate(['revision'=>['required','integer','min:1'],'object_revision'=>['required','integer','min:1'],'envelope_sha256'=>['required','regex:/^[a-f0-9]{64}$/']]);
        app(\App\Services\Sifi\FeedResearchBridge::class)->draft($r->user(),$object,$session,$v);return redirect()->route('sifi.multi-research.index',['object'=>$object->id,'research'=>$session]);
    }

}
