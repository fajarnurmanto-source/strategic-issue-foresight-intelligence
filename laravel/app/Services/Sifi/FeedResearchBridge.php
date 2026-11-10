<?php
namespace App\Services\Sifi;
use App\Models\{User,SifiMonitoringObject};
use Illuminate\Support\Facades\{DB,Gate};
use Illuminate\Validation\ValidationException;

final class FeedResearchBridge
{
 private function fail(string $message):never{throw ValidationException::withMessages(['research'=>$message]);}
 /** @return array<string, mixed> */
 public function pipelinePreview(User $u,string $sessionId):array
 {
  $s=DB::table('sifi_research_sessions')->where('id',$sessionId)->where('user_id',$u->id)->first();abort_unless($s!==null,404);$o=SifiMonitoringObject::query()->where('id',$s->object_id)->firstOrFail();Gate::forUser($u)->authorize('update',$o);$s=app(ResearchRetrievalWorkspace::class)->session($u,$o,$sessionId);
  if($o->state!=='CONFIGURED'||$o->revision!==(int)$s->object_revision)$this->fail('Objek berubah atau dijeda.');
  $m=json_decode($s->results_json,true,32,JSON_THROW_ON_ERROR)['multi_source']??null;$plan=json_decode($s->plan_json,true,32,JSON_THROW_ON_ERROR);$w=app(MultiSourceResearchWorkspace::class);
  if(!$m||$m['state']!=='SAVED'||($m['envelope']['mode']??null)!=='SYNTHETIC_FEED_ACQUISITION_ONLY'||!hash_equals($m['envelope_sha256'],$w->digest($m['envelope']))||!hash_equals($m['envelope']['plan_sha256'],$w->digest($plan)))$this->fail('Simpan hasil feed yang integritasnya valid terlebih dahulu.');
  $ids=$m['selected_ids'];if(!$ids||count($ids)>2)$this->fail('Pilih satu atau dua item sebelum menyimpan penelitian.');$items=array_values(array_filter($m['envelope']['entries'],fn($e)=>in_array($e['id'],$ids,true)));if(count($items)!==count($ids))$this->fail('Pilihan hasil tidak cocok.');$observations=[];
  foreach($items as $item){if($item['fixture_use']!=='SELF_AUTHORED_SYNTHETIC_ONLY'||$item['rights_state']!=='UNASSESSED'||$item['collector_state']!=='OFF'||!str_contains($item['title'],'SYNTHETIC'))$this->fail('Hanya fixture self-authored boleh masuk draf simulasi.');
   app(MultiSourceResearchWorkspace::class)->canonical($item['canonical_url']);if(($item['binding']['owner_id']??null)!==$u->id||($item['binding']['object_id']??null)!==$o->id||($item['binding']['session_id']??null)!==$s->id)$this->fail('Binding hasil tidak cocok.');
   $observations[]=['id'=>hash('sha256','feed|'.$u->id.'|'.$o->id.'|'.$item['id']),'description'=>$item['title'].' — '.$item['snippet'],'valid_at'=>'UNKNOWN','issued_at'=>$item['published_at']??'UNKNOWN','provenance'=>['feed_item'=>$item,'research_session_id'=>$s->id,'object_id'=>$o->id,'object_revision'=>(int)$o->revision,'synthetic'=>true,'independence_verified'=>false]];
  }
  $duplicates=[];foreach($items as $item)foreach(array_slice($item['lineage'],1) as $hit)$duplicates[]=['item_id'=>$item['id'],'duplicate_lineage'=>$hit];
  return ['mode'=>'SYNTHETIC_FIXTURE_ONLY','connector'=>'research_fixture','feed_connector'=>true,'feed_envelope'=>$m['envelope'],'status'=>'READY_FOR_ANALYST_DRAFT','reason'=>null,'packet_hash'=>$m['envelope_sha256'],'source'=>['publisher'=>'SIFI self-authored SYNTHETIC feed fixtures','documentation_url'=>'https://rss.sources.invalid/feed','attribution'=>'SIFI SYNTHETIC RSS/Atom/open-news fixtures, not external publications','limitation'=>'SYNTHETIC / NOT VERIFIED. Source rights UNASSESSED; collector OFF. Feed summary is not article text; no real event, grant, independent corroboration or evidence admission.','rights_state'=>'UNASSESSED','collector_state'=>'OFF'],'observations'=>$observations,'duplicates'=>$duplicates,'republication_candidates'=>$m['envelope']['comparisons'],'conflicts'=>[],'changes'=>array_values(array_filter($items,fn($e)=>$e['revision_state']!=='FIRST_IN_RUN')),'candidate_reason'=>'Telaah '.count($items).' item feed sintetis terpilih; bukan kondisi aktual atau jumlah peristiwa.','evidence_admission'=>'NOT_EVALUATED','claim_verified'=>false];
 }
 /** @param array<string, mixed> $v */
 public function draft(User $u,SifiMonitoringObject $object,string $session,array $v):string
 {
  return DB::transaction(function()use($u,$object,$session,$v){User::whereKey($u->id)->lockForUpdate()->firstOrFail();$o=SifiMonitoringObject::whereKey($object->id)->lockForUpdate()->firstOrFail();Gate::forUser($u)->authorize('update',$o);$s=app(ResearchRetrievalWorkspace::class)->session($u,$o,$session);$s=DB::table('sifi_research_sessions')->where('id',$s->id)->lockForUpdate()->firstOrFail();$preview=$this->pipelinePreview($u,$s->id);$r=json_decode($s->results_json,true,32,JSON_THROW_ON_ERROR);$m=$r['multi_source'];
   if($o->revision!==(int)$v['object_revision']||!hash_equals($m['envelope_sha256'],$v['envelope_sha256']))$this->fail('Preview atau revisi objek berubah.');
   if(isset($m['feed_ingestion']))return $m['feed_ingestion']['candidate_id'];
   if((int)$s->revision!==(int)$v['revision'])$this->fail('Revisi sesi berubah.');
   $candidate=app(SourceObservationPipeline::class)->createDraft($u,'feed_fixture',$m['analyst_note']??'',null,null,$s->id);
   $m['feed_ingestion']=['candidate_id'=>$candidate->id,'created_at'=>now()->toIso8601String(),'evidence_admission'=>'NOT_EVALUATED','synthetic'=>true];$r['multi_source']=$m;DB::table('sifi_research_sessions')->where('id',$s->id)->update(['results_json'=>json_encode($r,JSON_THROW_ON_ERROR),'revision'=>(int)$s->revision+1,'updated_at'=>now()]);
   DB::table('sifi_research_session_audit')->insert(['session_id'=>$s->id,'actor_user_id'=>$u->id,'action'=>'FEED_SYNTHETIC_DRAFT_LINKED','snapshot_json'=>json_encode(['candidate_id'=>$candidate->id,'packet_hash'=>$preview['packet_hash'],'evidence_admission'=>'NOT_EVALUATED'],JSON_THROW_ON_ERROR),'created_at'=>now()]);return $candidate->id;
  });
 }
}

