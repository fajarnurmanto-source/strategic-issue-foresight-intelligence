<?php
namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Models\SifiMonitoringObject;
use Illuminate\Http\{Request,RedirectResponse};
use Illuminate\Support\Facades\{DB,Gate,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Promotes one real, source-bound analyst packet into LOCAL triage entities only.
 * No verified evidence, official issue admission, Python ingest or network calls.
 */
final class E2e04RealSignalController extends Controller
{
    private function fail(string $why):never {throw ValidationException::withMessages(['e2e04'=>$why]);}

    public function create(Request $request,SifiMonitoringObject $object):RedirectResponse
    {
        Gate::authorize('update',$object);
        $v=$request->validate([
          'session_id'=>['required','uuid'],
          'object_revision'=>['required','integer','min:1'],
          'consent'=>['required','accepted'],
        ]);
        $uid=(int)$request->user()->id;
        $destination=DB::transaction(function()use($uid,$object,$v){
            $locked=SifiMonitoringObject::query()->whereKey($object->id)->lockForUpdate()->firstOrFail();
            if((int)$locked->user_id!==$uid || $locked->state!=='CONFIGURED' || (int)$locked->revision!==(int)$v['object_revision'])$this->fail('Objek tidak aktif, bukan milik Anda, atau revisinya berubah.');
            $s=DB::table('sifi_research_sessions')->where('id',$v['session_id'])->where('user_id',$uid)->where('object_id',$object->id)->lockForUpdate()->first();
            if(!$s || (int)$s->object_revision!==(int)$locked->revision || now()->gte($s->expires_at))$this->fail('Research Session tidak sesuai atau kedaluwarsa.');
            $data=json_decode($s->results_json,true,32,JSON_THROW_ON_ERROR);
            $p=$data['e2e01_pilot']??null;$image=$data['e2e02_image']??null;$packet=$data['e2e02_review_packet']??null;$intel=$data['e2e03_intelligence']??null;
            if(!is_array($p)||($p['state']??'')!=='RETRIEVED_EXTRACTED'||!is_array($image)||!is_array($packet)||!is_array($intel))$this->fail('E2E-01 sampai E2E-03 belum lengkap.');
            if(($packet['state']??'')!=='CANDIDATE_REVIEW_REQUIRED'||($intel['state']??'')!=='ANALYST_DRAFT_NOT_EVIDENCE')$this->fail('Review kandidat atau draft intelligence tidak memenuhi syarat.');
            $rawHash=$p['body_sha256']??null;$imgHash=$image['sha256']??null;$briefHash=$intel['brief_sha256']??null;
            foreach([$rawHash,$imgHash,$briefHash] as $h)if(!is_string($h)||preg_match('/^[a-f0-9]{64}$/D',$h)!==1)$this->fail('Hash sumber atau brief tidak valid.');
            if(!hash_equals($rawHash,(string)($packet['source_sha256']??''))||!hash_equals($rawHash,(string)($intel['source_sha256']??''))||!hash_equals($imgHash,(string)($packet['image_sha256']??''))||!hash_equals($imgHash,(string)($intel['image_sha256']??'')))$this->fail('Hash antara dokumen, image, paket kandidat dan brief tidak konsisten.');
            if(!hash_equals($briefHash,hash('sha256',(string)($intel['brief']??''))))$this->fail('Brief sudah berubah.');
            $text=$p['extraction']['text']??null;$quote=$packet['quote']??null;
            if(!is_string($text)||!is_array($quote))$this->fail('Snapshot/kutipan tidak tersedia.');
            $off=$quote['character_offset']??null;$len=$quote['character_length']??null;
            if(!is_int($off)||!is_int($len)||$off<0||$len<15||mb_substr($text,$off,$len)!==($quote['text']??null))$this->fail('Kutipan tidak cocok dengan teks yang diekstraksi.');
            $privatePath=$image['private_path']??null;$prefix='sifi/e2e02/'.$uid.'/'.$s->id.'/';
            if(!is_string($privatePath)||!str_starts_with($privatePath,$prefix)||!Storage::disk('local')->exists($privatePath)||!hash_equals($imgHash,hash('sha256',Storage::disk('local')->get($privatePath))))$this->fail('Gambar privat tidak ada atau hash berubah.');
            $sourceUrl=$p['source_url']??null;
            if(!is_string($sourceUrl)||strlen($sourceUrl)>1000||!str_starts_with($sourceUrl,'https://'))$this->fail('Asal sumber tidak valid.');
            $signalId=hash('sha256','sifi-real-source-v1|'.$s->id.'|'.$rawHash);
            $existing=DB::table('sifi_real_source_signals')->where('session_id',$s->id)->first();
            if($existing){
                if(!hash_equals($existing->id,$signalId))$this->fail('Sudah ada signal berbeda pada sesi ini.');
                $candidate=DB::table('sifi_issue_candidates')->join('sifi_investigations','sifi_issue_candidates.investigation_id','=','sifi_investigations.id')
                  ->join('sifi_investigation_signals','sifi_investigation_signals.investigation_id','=','sifi_investigations.id')
                  ->where('sifi_investigation_signals.signal_id',$signalId)->where('sifi_issue_candidates.user_id',$uid)
                  ->select('sifi_issue_candidates.id')->first();
                if(!$candidate)$this->fail('Registry ada tanpa kandidat terkait. Perlu pemeriksaan integritas.');
                return $candidate->id;
            }
            $issueLabel=trim((string)($intel['issue_label']??''));$claim=trim((string)($packet['claim_hypothesis']??''));
            if(mb_strlen($issueLabel)<10||mb_strlen($claim)<20)$this->fail('Judul isu atau claim tidak memadai.');
            $now=now();$investigationId=(string)Str::uuid();$candidateId=(string)Str::uuid();$evidenceId=(string)Str::uuid();
            DB::table('sifi_real_source_signals')->insert([
                'id'=>$signalId,'session_id'=>$s->id,'owner_user_id'=>$uid,'object_id'=>$locked->id,
                'object_revision'=>$locked->revision,'source_url'=>$sourceUrl,'source_sha256'=>$rawHash,
                'image_sha256'=>$imgHash,'brief_sha256'=>$briefHash,'provenance_mode'=>'REAL_SOURCE_SNAPSHOT',
                'review_state'=>'NOT_EVALUATED','created_at'=>$now]);
            DB::table('sifi_investigations')->insert([
                'id'=>$investigationId,'user_id'=>$uid,'title'=>mb_substr('Investigasi: '.$issueLabel,0,180),
                'claim_hypothesis'=>$claim,'evidence_gaps'=>mb_substr((string)($packet['visual_notes']??''),0,4000),
                'next_steps'=>mb_substr((string)($intel['recommended_checks']??''),0,4000),
                'review_state'=>'DRAFT','revision'=>1,'created_at'=>$now,'updated_at'=>$now]);
            DB::table('sifi_investigation_signals')->insert([
                'investigation_id'=>$investigationId,'signal_id'=>$signalId,'added_by_user_id'=>$uid,'created_at'=>$now]);
            DB::table('sifi_investigation_audit')->insert([
                'investigation_id'=>$investigationId,'actor_user_id'=>$uid,'revision'=>1,'action'=>'CREATED',
                'snapshot_json'=>json_encode(['signal_id'=>$signalId,'provenance_mode'=>'REAL_SOURCE_SNAPSHOT','source_sha256'=>$rawHash,'admission'=>'NONE'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'created_at'=>$now]);
            DB::table('sifi_issue_candidates')->insert([
                'id'=>$candidateId,'investigation_id'=>$investigationId,'user_id'=>$uid,'title'=>mb_substr($issueLabel,0,180),
                'issue_hypothesis'=>$claim,'exposure_notes'=>mb_substr((string)($intel['societal_impact']??''),0,4000),
                'evidence_gaps'=>mb_substr((string)($packet['visual_notes']??''),0,4000),
                'next_checks'=>mb_substr((string)($intel['recommended_checks']??''),0,4000),
                'review_state'=>'DRAFT','revision'=>1,'created_at'=>$now,'updated_at'=>$now]);
            DB::table('sifi_issue_candidate_audit')->insert([
                'candidate_id'=>$candidateId,'actor_user_id'=>$uid,'revision'=>1,'action'=>'CREATED',
                'snapshot_json'=>json_encode(['from_investigation'=>$investigationId,'source_signal_id'=>$signalId,'origin_provenance'=>'REAL_SOURCE_SNAPSHOT','admission'=>'NONE'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'created_at'=>$now]);
            DB::table('sifi_evidence_drafts')->insert([
                'id'=>$evidenceId,'candidate_id'=>$candidateId,'owner_user_id'=>$uid,'signal_id'=>$signalId,
                'claim_text'=>$claim,'rationale'=>'Kutipan asli: '.$quote['text'].' | sumber: '.$sourceUrl.' | content SHA-256: '.$rawHash.' | excerpt offset: '.$off,
                'known_gaps'=>mb_substr((string)($packet['visual_notes']??''),0,4000),'relationship'=>'UNKNOWN',
                'workflow_state'=>'DRAFT','revision'=>1,'created_at'=>$now,'updated_at'=>$now]);
            DB::table('sifi_evidence_draft_audit')->insert([
                'evidence_id'=>$evidenceId,'actor_user_id'=>$uid,'revision'=>1,'action'=>'CREATED',
                'snapshot_json'=>json_encode(['id'=>$evidenceId,'candidate_id'=>$candidateId,'signal_id'=>$signalId,
                    'source_sha256'=>$rawHash,'quote_offset'=>$off,'quote_length'=>$len,'review_state'=>'DRAFT','evidence_admission'=>'NONE'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'created_at'=>$now]);
            $data['e2e04_handoff']=['schema'=>'sifi-e2e04-handoff-1','state'=>'LOCAL_TRIAGE_DRAFT_NOT_ADMITTED',
                'signal_id'=>$signalId,'investigation_id'=>$investigationId,'issue_candidate_id'=>$candidateId,
                'evidence_draft_id'=>$evidenceId,'source_sha256'=>$rawHash,'created_at'=>$now->toIso8601String()];
            DB::table('sifi_research_sessions')->where('id',$s->id)->update([
                'results_json'=>json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'updated_at'=>$now]);
            DB::table('sifi_research_session_audit')->insert([
                'session_id'=>$s->id,'actor_user_id'=>$uid,'action'=>'E2E04_REAL_SIGNAL_HANDOFF',
                'snapshot_json'=>json_encode(['signal_id'=>$signalId,'candidate_id'=>$candidateId,'evidence_draft_id'=>$evidenceId,'admission'=>'NONE'],JSON_THROW_ON_ERROR),'created_at'=>$now]);
            return $candidateId;
        },3);
        return redirect()->route('sifi.evidence.index',$destination)->with('status','Real-source signal → Investigation → Issue Candidate → Evidence Draft tersimpan. Review independen masih diperlukan.');
    }
}
