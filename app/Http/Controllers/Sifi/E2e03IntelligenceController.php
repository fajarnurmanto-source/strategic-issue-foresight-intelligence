<?php
namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Models\SifiMonitoringObject;
use Illuminate\Http\{Request, RedirectResponse};
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Validation\ValidationException;

/** Real-source anchored ANALYST intelligence draft; NOT Issue/Evidence admission. */
final class E2e03IntelligenceController extends Controller
{
    private function fail(string $message):never {throw ValidationException::withMessages(['e2e03'=>$message]);}
    public function create(Request $request,SifiMonitoringObject $object):RedirectResponse
    {
        Gate::authorize('update',$object);
        $v=$request->validate([
          'session_id'=>['required','uuid'], 'object_revision'=>['required','integer'],
          'issue_label'=>['required','string','min:10','max:180'],
          'societal_impact'=>['required','string','min:20','max:1500'],
          'risk_level'=>['required','in:UNKNOWN,LOW,MEDIUM,HIGH'],
          'confidence'=>['required','in:INSUFFICIENT,PRELIMINARY'],
          'alternative_explanations'=>['required','string','min:20','max:1500'],
          'recommended_checks'=>['required','string','min:20','max:1500'],
          'analyst_consent'=>['required','accepted'],
        ]);
        if($object->state!=='CONFIGURED'||(int)$object->revision!==(int)$v['object_revision'])$this->fail('Objek berubah.');
        DB::transaction(function() use($request,$object,$v){
            $locked=SifiMonitoringObject::query()->whereKey($object->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($request->user())->authorize('update',$locked);
            if($locked->state!=='CONFIGURED'||(int)$locked->revision!==(int)$v['object_revision'])$this->fail('Revisi objek berubah.');
            $s=DB::table('sifi_research_sessions')->where('id',$v['session_id'])
              ->where('user_id',$request->user()->id)->where('object_id',$object->id)->lockForUpdate()->first();
            if(!$s || (int)$s->object_revision!==(int)$object->revision || now()->gte($s->expires_at))$this->fail('Research Session tidak aktif atau tidak sesuai.');
            $data=json_decode($s->results_json,true,32,JSON_THROW_ON_ERROR);
            if(isset($data['e2e03_intelligence']))$this->fail('Analisis sudah tersimpan. Tidak boleh ditimpa tanpa workflow revisi.');
            $p=$data['e2e01_pilot']??null;$packet=$data['e2e02_review_packet']??null;
            if(!is_array($p)||($p['state']??'')!=='RETRIEVED_EXTRACTED'||!isset($p['brief'],$p['extraction']['text']))$this->fail('Dokumen/brief nyata tidak ditemukan.');
            if(!is_array($packet)||($packet['state']??'')!=='CANDIDATE_REVIEW_REQUIRED')$this->fail('Simpan kandidat E2E-02 sebelum membuat analisis.');
            if(($p['body_sha256']??null)!==($packet['source_sha256']??null))$this->fail('SHA-256 sumber dan paket kandidat tidak sama.');
            $q=$packet['quote']??[];$off=(int)($q['character_offset']??-1);$len=(int)($q['character_length']??-1);
            if($off<0||$len<15||mb_substr($p['extraction']['text'],$off,$len)!==($q['text']??null))$this->fail('Kutipan tidak lagi cocok dengan snapshot asli.');
            $im=$data['e2e02_image']??null;
            if(!is_array($im)||($im['sha256']??null)!==($packet['image_sha256']??null))$this->fail('Gambar dan review packet tidak cocok.');
            $path=(string)($im['private_path']??'');$prefix='sifi/e2e02/'.$request->user()->id.'/'.$s->id.'/';
            if(!str_starts_with($path,$prefix)||!Storage::disk('local')->exists($path))$this->fail('Berkas gambar privat tidak ada.');
            if(!hash_equals((string)$im['sha256'],hash('sha256',Storage::disk('local')->get($path))))$this->fail('Hash gambar tidak cocok.');
            $clean=static fn($value)=>trim((string)$value);
            $brief="# SIFI — Intelligence Brief (ANALYST DRAFT / NOT VERIFIED)\n\n";
            $brief.="**Research Session:** {$s->id} | **Object revision:** {$s->object_revision}\n\n";
            $brief.="**Research question:** {$s->question}\n\n";
            $brief.="## Issue hypothesis\n\n".$clean($v['issue_label'])."\n\n";
            $brief.="## Source and bounded provenance\n\n".$p['source_url']."\n\n";
            $brief.="- Retrieved: ".$p['retrieved_at']."\n- Raw content SHA-256: ".$p['body_sha256']."\n- Image SHA-256: ".$im['sha256']."\n";
            $brief.="- Image URL: ".$im['source_url']."\n- Rights: UNASSESSED; evidence admission: NONE\n\n";
            $brief.="## Exact source excerpt (source-reported; not independently verified)\n\n> ".str_replace(["\r","\n"],' ',$q['text'])."\n\n";
            $brief.="## Claim hypothesis\n\n".$clean($packet['claim_hypothesis'])."\n\n";
            $brief.="## Visual analyst note (not verified)\n\n".$clean($packet['visual_notes'])."\n\n";
            $brief.="## Potential impact on society\n\n".$clean($v['societal_impact'])."\n\n";
            $brief.="## Provisional risk and evidence confidence\n\nRisk: {$v['risk_level']} (analyst judgement; not a model score). Confidence: {$v['confidence']} (self-reported; not evidence review).\n\n";
            $brief.="## Alternative explanations / opposing evidence needed\n\n".$clean($v['alternative_explanations'])."\n\n";
            $brief.="## Recommended verification and follow-up\n\n".$clean($v['recommended_checks'])."\n\n";
            $brief.="## Non-admission policy\n\nThis document remains ANALYST_DRAFT_NOT_EVIDENCE. No official claim, independent review, cross-source corroboration, evidence admission, or AI inference occurred. Issue Candidate/signal binding is not asserted.\n";
            $data['e2e03_intelligence']=[
              'schema'=>'sifi-e2e03-intelligence-draft-1','state'=>'ANALYST_DRAFT_NOT_EVIDENCE',
              'source_sha256'=>$p['body_sha256'],'image_sha256'=>$im['sha256'],
              'review_packet_state'=>$packet['state'],'risk_level'=>$v['risk_level'],
              'confidence'=>$v['confidence'],'issue_label'=>$clean($v['issue_label']),
              'societal_impact'=>$clean($v['societal_impact']),
              'alternative_explanations'=>$clean($v['alternative_explanations']),
              'recommended_checks'=>$clean($v['recommended_checks']),
              'brief'=>$brief,'brief_sha256'=>hash('sha256',$brief),
              'evidence_admission'=>'NONE','issue_candidate_id'=>null,
              'independent_review'=>'NOT_PERFORMED','ai'=>'OFF',
              'created_at'=>now()->toIso8601String(),
            ];
            DB::table('sifi_research_sessions')->where('id',$s->id)->update([
              'results_json'=>json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'updated_at'=>now(),
            ]);
            DB::table('sifi_research_session_audit')->insert([
              'session_id'=>$s->id,'actor_user_id'=>$request->user()->id,'action'=>'E2E03_INTELLIGENCE_DRAFTED',
              'snapshot_json'=>json_encode(['source_sha256'=>$p['body_sha256'],'image_sha256'=>$im['sha256'],
                 'brief_sha256'=>hash('sha256',$brief),'risk_level'=>$v['risk_level'],
                 'evidence_admission'=>'NONE','independent_review'=>'NOT_PERFORMED'],JSON_THROW_ON_ERROR),
              'created_at'=>now(),
            ]);
        });
        return back()->with('status','Analisis terstruktur disimpan dengan provenance dan tanpa evidence admission.');
    }
}
