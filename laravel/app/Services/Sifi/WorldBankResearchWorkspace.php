<?php
namespace App\Services\Sifi;
use App\Models\{User,SifiMonitoringObject,SifiSourceDraft};
use Illuminate\Support\Facades\{DB,Gate};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WorldBankResearchWorkspace
{
 private function fail(string $message):never{throw ValidationException::withMessages(['research'=>$message]);}
 public function pack():array{$p=json_decode(file_get_contents(resource_path('sifi/ae14-worldbank-pack.json')),true,16,JSON_THROW_ON_ERROR);if(($p['endpoint']??null)!==WorldBankGdpSourceAdapter::ENDPOINT||($p['max_bytes']??null)!==65536||($p['total_seconds']??null)!==5||($p['method']??null)!=='GET')$this->fail('Source pack berubah.');return $p;}
 public function context(User $u,SifiMonitoringObject $o,string $session):array{
  $s=app(ResearchRetrievalWorkspace::class)->session($u,$o,$session);$pack=$this->pack();
  return ['owner_id'=>(int)$u->id,'object_id'=>$o->id,'object_revision'=>$o->revision,'session_id'=>$s->id,'session_revision'=>(int)$s->revision,'plan_sha256'=>hash('sha256',$s->plan_json),'pack_id'=>$pack['id'],'pack_sha256'=>ResearchWorldBankAcquisition::digest($pack),'rights_sha256'=>hash_file('sha256',resource_path('sifi/ae09-wb-rights.json')),'endpoint'=>WorldBankGdpSourceAdapter::ENDPOINT,'method'=>'GET','max_bytes'=>65536,'total_seconds'=>5,'attempts'=>1,'retry'=>0,'redirect'=>0,'fallback'=>0,'cost'=>0];
 }
 public function transport(array $context):ResearchWorldBankAcquisition{return app()->bound(ResearchWorldBankAcquisition::class)?app()->make(ResearchWorldBankAcquisition::class,['context'=>$context]):new ResearchWorldBankAcquisition($context);}
 public function status(User $u,SifiMonitoringObject $o,string $session):array{$context=$this->context($u,$o,$session);$t=$this->transport($context);return ['state'=>$t->authorityState($u),'context'=>$context,'context_sha256'=>ResearchWorldBankAcquisition::digest($context),'pack'=>$this->pack(),'live_enabled'=>$t->authorityState($u)==='READY'&&!app()->environment('testing'),'mode'=>app()->environment('testing')?'SYNTHETIC_SIMULATOR_ONLY':'REAL_GET_NOT_PERFORMED'];}
 public function decide(User $u,SifiMonitoringObject $o,array $v):void{
  DB::transaction(function()use($u,$o,$v){$o=SifiMonitoringObject::whereKey($o->id)->lockForUpdate()->firstOrFail();Gate::forUser($u)->authorize('update',$o);if($o->state!=='CONFIGURED'||$o->revision!==(int)$v['object_revision'])$this->fail('Objek berubah atau dijeda.');$context=$this->context($u,$o,$v['session_id']);if(!hash_equals(ResearchWorldBankAcquisition::digest($context),$v['context_sha256']))$this->fail('Konteks rencana berubah.');
   if(DB::table('sifi_research_retrievals')->where('session_id',$v['session_id'])->where('result_key','worldbank-gdp-pack')->exists())return;
   if(DB::table('sifi_research_retrievals')->where('user_id',$u->id)->count()>=100)$this->fail('Batas keputusan akun.');
   $result=['title'=>'World Bank WDI · PDB Indonesia 2022–2024 (pack terkurasi)','url'=>WorldBankGdpSourceAdapter::ENDPOINT,'origin'=>'WORLD_BANK_WDI','research_context'=>$context];$id=(string)Str::uuid();
   DB::table('sifi_research_retrievals')->insert(['id'=>$id,'user_id'=>$u->id,'object_id'=>$o->id,'session_id'=>$v['session_id'],'result_key'=>'worldbank-gdp-pack','object_revision'=>$o->revision,'revision'=>1,'state'=>'WB_PACK_DECISION','result_json'=>json_encode($result,JSON_THROW_ON_ERROR),'result_sha256'=>ResearchWorldBankAcquisition::digest($result),'expires_at'=>now()->addDays(30),'created_at'=>now(),'updated_at'=>now()]);$this->audit($u,$id,1,'WB_PACK_DECISION',['context_sha256'=>ResearchWorldBankAcquisition::digest($context)]);
  });
 }
 private function current(User $u,SifiMonitoringObject $o,string $id,array $v):array{
  $r=app(ResearchRetrievalWorkspace::class)->locked($u,$o,$id,(int)$v['revision'],(int)$v['object_revision']);$result=json_decode($r->result_json,true,24,JSON_THROW_ON_ERROR);$context=$this->context($u,$o,$r->session_id);
  if(($result['research_context']??null)!==$context)$this->fail('Research authorization context berubah.');return [$r,$result,$context];
 }
 public function acquire(User $u,SifiMonitoringObject $o,string $id,array $v,bool $recover=false):void{
  DB::transaction(function()use($u,$o,$id,$v,$recover){[$r,$result,$context]=$this->current($u,$o,$id,$v);if(!in_array($r->state,['WB_PACK_DECISION','WB_ATTEMPT_FAILED'],true))$this->fail('Snapshot sudah ada.');$t=$this->transport($context);
   if($recover){$receipt=$t->expectedReceiptId();$p=$t->load($u,$receipt);}else{if($t->authorityState($u)!=='READY')$this->fail('Izin baru tidak READY; tidak ada request.');$p=$t->acquire($u);}
   $revision=(int)$r->revision+1;$ok=$p['status']==='READY_FOR_ANALYST_DRAFT';$result['acquisition_reason']=$p['reason']??null;$result['receipt_id']=$p['receipt_id']??$t->expectedReceiptId();$result['acquisition_mode']=$p['mode'];$doc=$ok?$p['document']:null;
   DB::table('sifi_research_retrievals')->where('id',$id)->update(['revision'=>$revision,'state'=>$ok?'WB_DOCUMENT_SNAPSHOT':'WB_ATTEMPT_FAILED','result_json'=>json_encode($result,JSON_THROW_ON_ERROR),'result_sha256'=>ResearchWorldBankAcquisition::digest($result),'document_json'=>$doc?json_encode($doc,JSON_THROW_ON_ERROR):null,'document_sha256'=>$doc?ResearchWorldBankAcquisition::digest($doc):null,'updated_at'=>now()]);
   $this->audit($u,$id,$revision,$recover?'WB_RECEIPT_RECOVERY':($ok?'WB_DOCUMENT_SNAPSHOT':'WB_ATTEMPT_FAILED'),['mode'=>$p['mode'],'reason'=>$p['reason']??null,'receipt_id'=>$result['receipt_id'],'document_sha256'=>$doc['body_sha256']??null,'attempts'=>$recover?0:($p['transport']['attempts']??0)]);
  });
 }
 public function pipelinePreview(User $u,string $id):array{
  $r=DB::table('sifi_research_retrievals')->where('id',$id)->where('user_id',$u->id)->first();abort_unless($r,404);$o=SifiMonitoringObject::whereKey($r->object_id)->firstOrFail();Gate::forUser($u)->authorize('update',$o);
  [$r,$result,$context]=$this->current($u,$o,$id,['revision'=>$r->revision,'object_revision'=>$o->revision]);if(!$r->document_json||!hash_equals($r->document_sha256,hash('sha256',$r->document_json)))$this->fail('Document integrity gagal.');
  $doc=json_decode($r->document_json,true,24,JSON_THROW_ON_ERROR);$q=$doc['quote'];if(!hash_equals($doc['body_sha256'],hash('sha256',$doc['body']))||substr($doc['body'],$q['byte_start'],$q['byte_end']-$q['byte_start'])!==$q['text'])$this->fail('Document span gagal.');
  $p=$this->transport($context)->load($u,$result['receipt_id']??null);if($p['status']!=='READY_FOR_ANALYST_DRAFT'||($p['document']??null)!==$doc)$this->fail('Receipt tidak valid atau kedaluwarsa.');return $p;
 }
 public function import(User $u,SifiMonitoringObject $o,string $id,array $v):void{
  DB::transaction(function()use($u,$o,$id,$v){[$r]=$this->current($u,$o,$id,$v);if($r->state!=='WB_DOCUMENT_SNAPSHOT'||!hash_equals((string)$r->document_sha256,$v['document_sha256']))$this->fail('Snapshot/digest berubah.');$p=$this->pipelinePreview($u,$id);$source=SifiSourceDraft::whereKey($v['source_id'])->where('user_id',$u->id)->firstOrFail();
   $candidate=app(SourceObservationPipeline::class)->createDraft($u,'wb_research_acquisition',$v['analyst_note'],$source,(int)$v['source_revision'],$id,$p['packet_hash']);$revision=(int)$r->revision+1;
   DB::table('sifi_research_retrievals')->where('id',$id)->update(['candidate_id'=>$candidate->id,'state'=>'WB_DRAFT_LINKED','revision'=>$revision,'updated_at'=>now()]);$this->audit($u,$id,$revision,'WB_DRAFT_LINKED',['candidate_id'=>$candidate->id,'document_sha256'=>$r->document_sha256]);
  });
 }
 private function audit(User $u,string $id,int $revision,string $action,array $data):void{DB::table('sifi_research_retrieval_audit')->insert(['retrieval_id'=>$id,'actor_user_id'=>$u->id,'revision'=>$revision,'action'=>$action,'snapshot_json'=>json_encode($data,JSON_THROW_ON_ERROR),'created_at'=>now()]);}
}
