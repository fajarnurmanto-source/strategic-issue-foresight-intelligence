<?php
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Sifi\Ai\{UserAiSettings,ProviderEndpointPolicy,ProviderHttpTransport,ConfiguredHttpAiAdapter,SecureAiRuntime,AiTaskCapabilityRegistry,FreeAiCatalogue};
use App\Data\Sifi\AiInferenceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

final class AiModelController extends Controller
{
    public static function schema(): array {return ['type'=>'object','properties'=>['explanation'=>['type'=>'string'],'unknowns'=>['type'=>'string'],'next_checks'=>['type'=>'string']],'required'=>['explanation','unknowns','next_checks'],'additionalProperties'=>false];}
    public function edit(Request $r,UserAiSettings $store,ProviderEndpointPolicy $policy)
    {
        $private=$store->read((int)$r->user()->id);$settings=$store->publicView((int)$r->user()->id);
        foreach ($settings['providers'] as $id=>&$p) {
            $entry=FreeAiCatalogue::provider($p['protocol']);$p['allowed_models']=$entry['models']??[];$p['free_status']=[];
            $p['probe_available']=[];
            foreach ($p['allowed_models'] as $model) {
                $p['free_status'][$model]=$policy->freeStatus($private['providers'][$id],$model);
                $p['probe_available'][$model]=$policy->probeAvailable($private['providers'][$id],$model);
            }
        }
        return Inertia::render('settings/ai-models',['settings'=>$settings,'providers'=>[],'catalogue'=>FreeAiCatalogue::entries(),'notice'=>$r->session()->get('ai_notice')]);
    }
    public function save(Request $r,UserAiSettings $store,ProviderEndpointPolicy $policy)
    {
        $credential=$r->input('credential')??'';$r->replace($r->except('credential'));
        if (!is_string($credential)||strlen($credential)>500) return back()->with('ai_notice','Credential tidak valid.');
        $v=$r->validate(['protocol'=>'required|in:gemini,groq,ollama','endpoint'=>'required|string|max:200','label'=>'required|string|max:80']);
        try { $v['endpoint']=$policy->validate($v['endpoint'],$v['protocol']); } catch (\Throwable) { return back()->with('ai_notice','Endpoint tidak diizinkan.'); }
        if ($v['protocol']!=='ollama' && empty($credential)) return back()->with('ai_notice','Credential diperlukan.');
        $id=(string)Str::uuid();$data=$store->read((int)$r->user()->id);
        $data['providers'][$id]=['id'=>$id,'owner_id'=>(int)$r->user()->id,'label'=>$v['label'],'protocol'=>$v['protocol'],'endpoint'=>$v['endpoint'],'key'=>$credential,'status'=>'DISCONNECTED','models'=>[],'verified_models'=>[]];
        $store->write((int)$r->user()->id,$data);
        return back()->with('ai_notice','Konfigurasi terenkripsi disimpan. Gratis untuk akun ini belum diverifikasi; tidak ada koneksi.');
    }
    public function probe(Request $r,UserAiSettings $store,ProviderHttpTransport $http)
    {
        $v=$r->validate(['provider'=>'required|uuid','model'=>'required|string|max:150']);$data=$store->read((int)$r->user()->id);
        abort_unless(isset($data['providers'][$v['provider']]),404);$p=&$data['providers'][$v['provider']];
        $p['verified_models']=[];$p['status']='UNAVAILABLE';$data['enabled']=false;$p['last_code']='PROVIDER_REQUEST_FAILED';
        try {
            $adapter=new ConfiguredHttpAiAdapter($http,$p,$v['model'],FreeAiCatalogue::SYNTHETIC);$p['models']=$adapter->catalogue();
            if (!in_array($v['model'],$p['models'],true)) throw new \RuntimeException('MODEL_NOT_ALLOWED');
            $p['verified_models'][$v['model']]=true;
            $adapter=new ConfiguredHttpAiAdapter($http,$p,$v['model'],FreeAiCatalogue::SYNTHETIC);
            $runtime=new SecureAiRuntime($adapter,new AiTaskCapabilityRegistry);
            $result=$runtime->invoke($r->user(),(int)$r->user()->id,['enabled'=>true,'provider'=>$p['id'],'model'=>$v['model']],new AiInferenceRequest('summary_draft',[],self::schema(),10000,'PUBLIC',(string)Str::uuid()));
            $p['last_code']=$result->warnings[0]??'PROVIDER_REQUEST_FAILED';
            if ($result->status==='AVAILABLE') {
                $p['status']='CONNECTED';$p['last_code']='SYNTHETIC_PROBE_PASSED';$p['last_usage']=['known'=>$result->provenance['usage_known']??false,'units'=>$result->usageUnits];
            } else $p['verified_models']=[];
        } catch (\Throwable $e) { $p['verified_models']=[];$p['last_code']=$e->getMessage()==='MODEL_NOT_ALLOWED'?'MODEL_NOT_ALLOWED':'PROVIDER_REQUEST_FAILED'; }
        if ($p['last_code']==='QUOTA_EXHAUSTED') $p['status']='QUOTA_EXHAUSTED';
        if ($p['last_code']==='BILLING_REQUIRED') $p['status']='BILLING_REQUIRED';
        $store->write((int)$r->user()->id,$data);
        return back()->with('ai_notice',self::notice($p['last_code']));
    }
    private static function notice(string $code): string
    {
        return match($code){
            'SYNTHETIC_PROBE_PASSED'=>'Transport dan schema teruji pada teks sintetis publik. Catatan isu nyata belum diizinkan.',
            'QUOTA_EXHAUSTED'=>'Kuota gratis habis atau batas laju tercapai. Tidak ada retry atau fallback berbayar.',
            'BILLING_REQUIRED'=>'Penyedia meminta billing. Koneksi dihentikan; jangan aktifkan billing.',
            'FREE_ELIGIBILITY_UNKNOWN'=>'Gratis untuk akun/proyek ini belum terbukti. Tidak ada request dikirim.',
            'GRANT_EXHAUSTED'=>'Izin satu panggilan telah terpakai. Tidak ada request tambahan.',
            'PAID_MODEL_REJECTED'=>'Model berbayar ditolak oleh kebijakan Rp0.',
            'MODEL_NOT_ALLOWED'=>'Model di luar daftar gratis yang diperiksa.',
            default=>'Pengujian tidak berhasil. AI tetap nonaktif; tidak ada fallback.',
        };
    }
    public function assign(Request $r,UserAiSettings $store,ProviderEndpointPolicy $policy)
    {
        $v=$r->validate(['provider'=>'required|uuid','model'=>'required|string|max:150']);$data=$store->read((int)$r->user()->id);$p=$data['providers'][$v['provider']]??null;
        abort_unless($p && $p['status']==='CONNECTED' && ($p['verified_models'][$v['model']]??false) && $policy->freeStatus($p,$v['model'])==='FREE VERIFIED',422);
        $data['assignments']['summary_draft']=['provider'=>$v['provider'],'model'=>$v['model']];$data['enabled']=true;$store->write((int)$r->user()->id,$data);
        return back()->with('ai_notice','Pilihan model disimpan. Pengiriman catatan isu nyata tetap diblokir.');
    }
    public function disable(Request $r,UserAiSettings $store){$data=$store->read((int)$r->user()->id);$data['enabled']=false;$store->write((int)$r->user()->id,$data);$r->session()->put('sifi_ai_selection',['enabled'=>false]);return back()->with('ai_notice','AI dinonaktifkan.');}
    public function revoke(Request $r,UserAiSettings $store){$store->revoke((int)$r->user()->id);return back()->with('ai_notice','Credential dan konfigurasi lokal dicabut. Cabut juga key pada penyedia.');}
}
