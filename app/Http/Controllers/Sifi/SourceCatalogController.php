<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sifi\CoverageDraftRequest;
use App\Http\Requests\Sifi\SourceDraftRequest;
use App\Models\SifiCoverageProfile;
use App\Models\SifiSourceDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Local proposal workspace. Never mutates Python registry, evidence or collector switches. */
final class SourceCatalogController extends Controller
{
    public function index(Request $request, \App\Services\Sifi\SourceObservationPipeline $pipeline): Response
    {
        Gate::authorize('viewAny', SifiSourceDraft::class);
        Gate::authorize('viewAny', SifiCoverageProfile::class);
        $uid = (int) $request->user()->id;
        $sources = SifiSourceDraft::query()->where('user_id',$uid)->orderBy('name')->limit(150)
            ->get(['id','name','source_type','homepage_url','source_geography','notes','rights_state','collector_state','revision'])->toArray();
        $profiles = SifiCoverageProfile::query()->where('user_id',$uid)->orderBy('name')->limit(75)
            ->get(['id','name','level','region_name','domains_json','profile_state','collector_state','revision'])
            ->map(function ($p) { return [
                'id'=>$p->id, 'name'=>$p->name, 'level'=>$p->level, 'region_name'=>$p->region_name,
                'domains'=>$p->domains_json, 'profile_state'=>$p->profile_state, 'collector_state'=>$p->collector_state,
                'revision'=>$p->revision, 'source_ids'=>DB::table('sifi_coverage_source_drafts')->where('profile_id',$p->id)->pluck('source_id')->all()
            ]; })->all();
        $request->validate(['receipt'=>['sometimes','uuid']]);
        $selected=$request->validate(['scenario'=>['sometimes','string',\Illuminate\Validation\Rule::in(array_keys(\App\Services\Sifi\SourceObservationPipeline::SCENARIOS))]])['scenario']??'synthetic';
        $saved=$request->session()->get('ae08_pad_preview');
        $importJson=is_array($saved) && ($saved['owner_id']??null)===$uid ? ($saved['payload']??null) : null;
        return Inertia::render('sifi/source-catalog', [
            'sources'=>$sources, 'profiles'=>$profiles, 'scope'=>'LOCAL_CANDIDATE_ONLY',
            'sourceModules'=>app(\App\Services\Sifi\SourceModuleCatalog::class)->forE4(now('Asia/Jakarta')->toDateString()),
            'acquisitionWorkspace'=>$selected==='wb_acquisition'?app(\App\Services\Sifi\WorldBankAcquisitionWorkspace::class)->view($request->user()):null,
            'pipeline'=>$pipeline->preview($selected,$selected==='wb_acquisition'?($request->query('receipt')??$request->session()->get('ae10_acquisition.'.$uid)):($selected==='pad_import'?$importJson:null),$uid),
        ]);
    }

    public function acquireWorldBank(Request $request, \App\Services\Sifi\WorldBankOneShotAcquisition $service): RedirectResponse
    {
        Gate::authorize('viewAny',SifiSourceDraft::class);
        $request->validate(['confirm_one_shot'=>['accepted'],'endpoint'=>['prohibited'],'url'=>['prohibited'],'raw'=>['prohibited'],'rights'=>['prohibited'],'permit'=>['prohibited'],'user_id'=>['prohibited']]);
        $p=app(\App\Services\Sifi\WorldBankAcquisitionWorkspace::class)->run($request->user());
        if($p['status']!=='READY_FOR_ANALYST_DRAFT') return redirect()->route('sifi.source-catalog.index',['scenario'=>'wb_acquisition'])->withErrors(['acquisition'=>'Pengambilan diblokir: '.$p['reason'].'. Tidak ada retry; periksa receipt percobaan.']);
        $request->session()->put('ae10_acquisition.'.(int)$request->user()->id,$p['receipt_id']);
        return redirect()->route('sifi.source-catalog.index',['scenario'=>'wb_acquisition']);
    }

    public function previewConnector(Request $request, \App\Services\Sifi\YogyakartaPadSourceAdapter $adapter): RedirectResponse
    {
        Gate::authorize('viewAny',SifiSourceDraft::class);
        $v=$request->validate(['import_json'=>['required','string','max:65536']]);
        $preview=$adapter->importJson($v['import_json']);
        if ($preview['status']!=='READY_FOR_ANALYST_DRAFT') throw ValidationException::withMessages(['import_json'=>'Impor diblokir: '.$preview['reason']]);
        $request->session()->put('ae08_pad_preview',['owner_id'=>(int)$request->user()->id,'payload'=>$v['import_json']]);
        return redirect()->route('sifi.source-catalog.index',['scenario'=>'pad_import']);
    }

    public function createPipelineDraft(Request $request, \App\Services\Sifi\SourceObservationPipeline $pipeline): RedirectResponse
    {
        Gate::authorize('viewAny', SifiSourceDraft::class);
        $v = $request->validate([
            'scenario'=>['required','string',\Illuminate\Validation\Rule::in(array_keys(\App\Services\Sifi\SourceObservationPipeline::SCENARIOS))],
            'analyst_note'=>['required','string','min:15','max:2000'], 'confirm_synthetic'=>['exclude_if:scenario,wb_snapshot,wb_acquisition','accepted'],
            'confirm_archive'=>['exclude_unless:scenario,wb_snapshot,wb_acquisition','accepted'],
            'user_id'=>['prohibited'],'source_url'=>['prohibited'],'records'=>['prohibited'],
            'verified'=>['prohibited'],'evidence_admitted'=>['prohibited'],'collector_state'=>['prohibited'],
            'receipt_id'=>['nullable','uuid'],'source_draft_id'=>['nullable','uuid'],'source_revision'=>['nullable','integer','min:1'],'input_digest'=>['nullable','string','regex:/\A[0-9a-f]{64}\z/'],
        ]);
        $source=isset($v['source_draft_id'])?SifiSourceDraft::findOrFail($v['source_draft_id']):null;
        if ($source!==null) Gate::authorize('view',$source);
        $saved=$request->session()->get('ae08_pad_preview');
        $json=is_array($saved) && ($saved['owner_id']??null)===(int)$request->user()->id ? ($saved['payload']??null) : null;
        $candidate = $pipeline->createDraft($request->user(), $v['scenario'], trim($v['analyst_note']),$source,$v['source_revision']??null,$v['scenario']==='wb_acquisition'?($v['receipt_id']??$request->session()->get('ae10_acquisition.'.(int)$request->user()->id)):($v['scenario']==='pad_import'?$json:null),$v['input_digest']??null);
        return redirect()->route('sifi.executive-brief.show', $candidate->id);
    }

    public function storeSource(SourceDraftRequest $request): RedirectResponse
    {
        Gate::authorize('create', SifiSourceDraft::class);
        app(\App\Services\Sifi\SourceDraftRegistration::class)->create($request->user(),$request->validated());
        return redirect()->route('sifi.source-catalog.index');
    }

    public function updateSource(SourceDraftRequest $request, SifiSourceDraft $source): RedirectResponse
    {
        Gate::authorize('update', $source);
        $data=$request->validated();
        DB::transaction(function () use ($source,$request,$data) {
            $row=SifiSourceDraft::whereKey($source->id)->lockForUpdate()->firstOrFail();
            abort_unless((int)$row->user_id===(int)$request->user()->id,403);
            if ($row->revision!== (int)$data['revision']) {
                throw ValidationException::withMessages(['revision'=>'Revisi berubah. Muat ulang sebelum menyimpan.']);
            }
            if (str_starts_with($row->notes??'', '[SYNTHETIC / LOCAL CATALOG]') && (!str_starts_with(trim($data['name']),'[SYNTHETIC]') || ($data['homepage_url']??null)!==$row->homepage_url || !str_starts_with($data['notes']??'', '[SYNTHETIC / LOCAL CATALOG]'))) {
                throw ValidationException::withMessages(['notes'=>'Label SYNTHETIC dan URL fixture discovery harus tetap dipertahankan. Buat sumber nyata secara terpisah.']);
            }
            $fields=['name'=>trim($data['name']),'source_type'=>$data['source_type'],
                'homepage_url'=>$data['homepage_url']??null,'source_geography'=>$data['source_geography']??null,
                'notes'=>$data['notes']??null];
            if (!collect($fields)->contains(fn($v,$k)=>$row->{$k}!==$v)) { return; }
            $row->fill($fields);$row->revision++;$row->save();
            $this->audit('SOURCE',$row->id,(int)$request->user()->id,$row->revision,'EDITED',array_merge($fields,['rights_state'=>'UNASSESSED','collector_state'=>'OFF']));
        });
        return redirect()->route('sifi.source-catalog.index');
    }

    public function storeProfile(CoverageDraftRequest $request): RedirectResponse
    {
        Gate::authorize('create', SifiCoverageProfile::class);
        $data=$request->validated();$uid=(int)$request->user()->id;
        DB::transaction(function () use ($data,$uid) {
            $ids=$this->ownedSourceIds($uid,$data['source_ids']??[]);
            $profile=SifiCoverageProfile::create([
                'id'=>(string) Str::uuid(),'user_id'=>$uid,'name'=>trim($data['name']),
                'level'=>$data['level'],'region_name'=>trim($data['region_name']),
                'domains_json'=>$data['domains'],'revision'=>1,
            ]);
            $this->replaceLinks($profile->id,$ids);
            $this->audit('PROFILE',$profile->id,$uid,1,'CREATED',[
                'name'=>$profile->name,'level'=>$profile->level,'region_name'=>$profile->region_name,
                'domains'=>$data['domains'],'source_ids'=>$ids,'collector_state'=>'OFF',
            ]);
        });
        return redirect()->route('sifi.source-catalog.index');
    }

    public function updateProfile(CoverageDraftRequest $request, SifiCoverageProfile $profile): RedirectResponse
    {
        Gate::authorize('update',$profile);
        $data=$request->validated();$uid=(int)$request->user()->id;
        DB::transaction(function () use ($profile,$data,$uid) {
            $row=SifiCoverageProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            abort_unless((int)$row->user_id===$uid,403);
            if ($row->revision!==(int)$data['revision']) {
                throw ValidationException::withMessages(['revision'=>'Revisi berubah. Muat ulang sebelum menyimpan.']);
            }
            $ids=$this->ownedSourceIds($uid,$data['source_ids']??[]);
            $fields=['name'=>trim($data['name']),'level'=>$data['level'],
                'region_name'=>trim($data['region_name']),'domains_json'=>$data['domains']];
            $oldIds=DB::table('sifi_coverage_source_drafts')->where('profile_id',$row->id)->orderBy('source_id')->pluck('source_id')->all();
            $newIds=$ids; sort($newIds);
            if (!collect($fields)->contains(fn($v,$k)=>$row->{$k}!==$v) && $oldIds===$newIds) { return; }
            $row->fill($fields);$row->revision++;$row->save();
            $this->replaceLinks($row->id,$newIds);
            $this->audit('PROFILE',$row->id,$uid,$row->revision,'EDITED',[
                'name'=>$row->name,'level'=>$row->level,'region_name'=>$row->region_name,
                'domains'=>$row->domains_json,'source_ids'=>$newIds,'collector_state'=>'OFF',
            ]);
        });
        return redirect()->route('sifi.source-catalog.index');
    }

    private function ownedSourceIds(int $userId,array $ids): array
    {
        $ids=array_values(array_unique($ids));
        $count=SifiSourceDraft::query()->where('user_id',$userId)->whereIn('id',$ids)->count();
        if ($count!==count($ids)) {
            throw ValidationException::withMessages(['source_ids'=>'Sumber tidak ditemukan atau bukan milik Anda.']);
        }
        return $ids;
    }

    private function replaceLinks(string $profileId,array $ids): void
    {
        DB::table('sifi_coverage_source_drafts')->where('profile_id',$profileId)->delete();
        foreach ($ids as $id) DB::table('sifi_coverage_source_drafts')->insert(['profile_id'=>$profileId,'source_id'=>$id]);
    }

    private function audit(string $type,string $id,int $actor,int $revision,string $action,array $snapshot): void
    {
        DB::table('sifi_catalog_audit')->insert([
            'subject_type'=>$type,'subject_id'=>$id,'actor_user_id'=>$actor,
            'revision'=>$revision,'action'=>$action,
            'snapshot_json'=>json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            'created_at'=>now(),
        ]);
    }
}
