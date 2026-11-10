<?php
namespace App\Services\Sifi;
use App\Models\{User,SifiMonitoringObject,SifiSourceDraft};
use Illuminate\Support\Facades\{DB,Gate};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
/** Owner-locked metadata selections. Separate from AE12 eligible source associations and evidence. */
final class SourceDiscoveryWorkspace {
 private function fail(string $field,string $message):never {throw ValidationException::withMessages([$field=>$message]);}
 private function object(User $u,string $id,int $revision):SifiMonitoringObject {
  $o=SifiMonitoringObject::whereKey($id)->lockForUpdate()->firstOrFail();Gate::forUser($u)->authorize('update',$o);
  if($o->revision!==$revision)$this->fail('object_revision','Objek berubah. Muat ulang hasil discovery.');
  if($o->state!=='CONFIGURED')$this->fail('object_revision','Objek dijeda/diarsipkan. Aktifkan konfigurasi sebelum menyimpan kandidat.');return $o;
 }
 /** @param array<string,mixed> $filters
  * @return array<string,mixed>
  */
 private function context(SifiMonitoringObject $o,array $filters):array {
  return ['object_id'=>$o->id,'object_revision'=>$o->revision,'name'=>$o->name,'aliases'=>$o->aliases_json,'keywords'=>$o->keywords_json,'exclusions'=>$o->exclusions_json,'objective'=>$o->objective,'geography'=>$o->geography,'filters'=>$filters,'provider'=>!empty($filters['candidate_url'])?'USER_SUBMITTED_URL':'CURATED_PUBLIC_DIRECTORY','catalog_sha256'=>hash('sha256',hash_file('sha256',resource_path('sifi/source-discovery-catalog.json')).hash_file('sha256',resource_path('sifi/ae16-public-source-directory.json'))),'recorded_at'=>now()->toIso8601String(),'network'=>'OFF','admission'=>'NONE'];
 }
 /** @param array<string,mixed> $snapshot */
 private function audit(\stdClass $s,User $u,string $action,array $snapshot):void {
  DB::table('sifi_discovery_audit')->insert(['selection_id'=>$s->id,'actor_user_id'=>$u->id,'revision'=>$s->revision,'action'=>$action,'snapshot_json'=>json_encode($snapshot,JSON_THROW_ON_ERROR),'created_at'=>now()]);
 }
 /** @param array<string,mixed> $filters */
 public function save(User $u,string $objectId,int $objectRevision,string $key,array $filters):void {
  DB::transaction(function()use($u,$objectId,$objectRevision,$key,$filters){
   $o=$this->object($u,$objectId,$objectRevision);$catalog=app(DiscoveryCatalog::class);
   $results=$catalog->discover($o,$filters);$e=collect($results['entries'])->firstWhere('key',$key);
   if(!$e)$this->fail('candidate','Kandidat tidak terdapat pada hasil discovery saat ini.');
   unset($e['match_points'],$e['dimensions']);$url=$catalog->safeUrl($e['url'],($e['synthetic']??false)===true);$identity=hash('sha256',$url);$now=now();
   $c=DB::table('sifi_discovery_candidates')->where('user_id',$u->id)->where('identity_sha256',$identity)->lockForUpdate()->first();
   $stored=$e;unset($stored['reasons']);$descriptor=json_encode($stored,JSON_THROW_ON_ERROR);$hash=hash('sha256',$descriptor);
   if(!$c){if(DB::table('sifi_discovery_candidates')->where('user_id',$u->id)->count()>=200)$this->fail('candidate','Batas 200 kandidat akun tercapai.');$id=(string)Str::uuid();DB::table('sifi_discovery_candidates')->insert(['id'=>$id,'user_id'=>$u->id,'identity_sha256'=>$identity,'descriptor_json'=>$descriptor,'descriptor_sha256'=>$hash,'created_at'=>$now,'updated_at'=>$now]);$c=DB::table('sifi_discovery_candidates')->where('id',$id)->first();}
   // Relevance reasons belong to each object context, not shared descriptor identity.
   $old=json_decode($c->descriptor_json,true,32,JSON_THROW_ON_ERROR);unset($old['reasons']);$check=$e;unset($check['reasons']);
   if($old!==$check)$this->fail('candidate','Metadata kandidat berubah/ambigu. Jangan menimpa snapshot tersimpan.');
   $s=DB::table('sifi_discovery_selections')->where('object_id',$o->id)->where('candidate_id',$c->id)->first();
   if($s){if(now()->gte($s->expires_at))$this->fail('candidate','Retensi konteks kedaluwarsa. Tidak diperpanjang diam-diam.');return;}
   if(DB::table('sifi_discovery_selections')->where('object_id',$o->id)->count()>=50)$this->fail('candidate','Batas 50 pilihan objek tercapai.');$context=$this->context($o,$filters)+['reasons'=>$e['reasons']];$id=(string)Str::uuid();
   DB::table('sifi_discovery_selections')->insert(['id'=>$id,'object_id'=>$o->id,'candidate_id'=>$c->id,'revision'=>1,'object_revision'=>$o->revision,'context_json'=>json_encode($context,JSON_THROW_ON_ERROR),'expires_at'=>$now->copy()->addDays(30),'created_at'=>$now,'updated_at'=>$now]);
   $this->audit(DB::table('sifi_discovery_selections')->where('id',$id)->firstOrFail(),$u,'SAVED',['candidate_id'=>$c->id,'descriptor_sha256'=>$c->descriptor_sha256,'context'=>$context,'rights'=>'UNASSESSED']);
  });
 }
 public function change(User $u,string $objectId,int $objectRevision,string $selectionId,int $revision,bool $register):void {
  DB::transaction(function()use($u,$objectId,$objectRevision,$selectionId,$revision,$register){
   $o=$this->object($u,$objectId,$objectRevision);
   $s=DB::table('sifi_discovery_selections')->where('id',$selectionId)->where('object_id',$o->id)->lockForUpdate()->first();abort_unless($s!==null,404);
   $c=DB::table('sifi_discovery_candidates')->where('id',$s->candidate_id)->where('user_id',$u->id)->lockForUpdate()->first();abort_unless($c!==null,404);
   if((int)$s->revision!==$revision)$this->fail('revision','Revisi kandidat berubah. Muat ulang.');
   if(now()->gte($s->expires_at))$this->fail('revision','Retensi konteks telah kedaluwarsa.');
   $e=json_decode($c->descriptor_json,true,32,JSON_THROW_ON_ERROR);app(DiscoveryCatalog::class)->assertDescriptor($e);
   if(hash('sha256',$c->descriptor_json)!==$c->descriptor_sha256)$this->fail('candidate','Integritas descriptor gagal.');
   $old=json_decode($s->context_json,true,32,JSON_THROW_ON_ERROR);$filters=$old['filters'];$found=collect(app(DiscoveryCatalog::class)->discover($o,$filters,true)['entries'])->firstWhere('key',$e['key']);
   if(!$found)$this->fail('candidate','Kandidat tidak lagi relevan dengan konfigurasi objek.');
   $current=$found;unset($current['reasons'],$current['dimensions'],$current['match_points']);if($current!==$e)$this->fail('candidate','Versi metadata katalog berubah. Registrasi snapshot lama diblokir.');
   if($register&&(int)$s->object_revision!==$o->revision)$this->fail('object_revision','Simpan konteks revisi objek terbaru sebelum pendaftaran.');
   if($register&&$s->registered_source_id){$source=SifiSourceDraft::whereKey($c->source_draft_id)->where('user_id',$u->id)->first();abort_unless($source!==null,404);if($source->rights_state!=='UNASSESSED'||$source->collector_state!=='OFF'||$source->homepage_url!==$e['url'])$this->fail('candidate','Registrasi existing berubah. Tinjau Source & Coverage.');return;}
   $context=$this->context($o,$filters)+['reasons'=>$found['reasons']];
   if($register){
    $url=app(DiscoveryCatalog::class)->safeUrl($e['url'],($e['synthetic']??false)===true);
    // Reuse only one unambiguous same-owner exact URL registration; never steal or silently overwrite.
    $matches=SifiSourceDraft::where('user_id',$u->id)->where('homepage_url',$url)->lockForUpdate()->get();
    if($matches->count()>1)$this->fail('candidate','Identitas sumber ambigu. Tinjau duplikasi di Source & Coverage.');
    $source=$matches->first();
    if($source&&($source->name!==$e['title']||$source->rights_state!=='UNASSESSED'||$source->collector_state!=='OFF'))$this->fail('candidate','URL telah digunakan sumber berbeda. Tidak ditimpa.');
    if(!$source)$source=app(SourceDraftRegistration::class)->create($u,['name'=>$e['title'],'source_type'=>match($e['type']){'OPEN_DATA','API'=>'OPEN_DATA','NEWS','RSS','ATOM'=>'MEDIA','RESEARCH'=>'RESEARCH',default=>'OTHER'},'homepage_url'=>$url,'source_geography'=>$e['geography'],'notes'=>(($e['origin']??'')==='USER_SUBMITTED_URL'?'[USER SUBMITTED URL / NOT FETCHED] ':(($e['synthetic']??false)?'[SYNTHETIC / LOCAL CATALOG] ':'[CURATED PUBLIC SOURCE / NOT CONTACTED] ')).'candidate '.$c->id.'; '.$e['attribution'].' '.$e['coverage'].' UNASSESSED; COLLECTOR OFF; tidak ada grant/admission.']);
    DB::table('sifi_discovery_candidates')->where('id',$c->id)->update(['source_draft_id'=>$source->id,'updated_at'=>now()]);$context['source_draft_id']=$source->id;DB::table('sifi_discovery_selections')->where('id',$s->id)->update(['registered_source_id'=>$source->id]);
   }elseif((int)$s->object_revision===$o->revision)return;
   DB::table('sifi_discovery_selections')->where('id',$s->id)->update(['revision'=>$s->revision+1,'object_revision'=>$o->revision,'context_json'=>json_encode($context,JSON_THROW_ON_ERROR),'updated_at'=>now()]);$s->revision++;
   $this->audit($s,$u,$register?'REGISTERED_UNASSESSED':'CONTEXT_REFRESHED',$context+['rights'=>'UNASSESSED','collector'=>'OFF']);
  });
 }
 /** @return list<array<string,mixed>> */
 public function selections(User $u,SifiMonitoringObject $o):array {
  Gate::forUser($u)->authorize('view',$o);
  return DB::table('sifi_discovery_selections as s')->join('sifi_discovery_candidates as c','c.id','=','s.candidate_id')->where('s.object_id',$o->id)->where('c.user_id',$u->id)->orderByDesc('s.updated_at')->limit(50)->get(['s.*','c.descriptor_json'])->map(function($s){
   $expired=now()->gte($s->expires_at);
   return ['id'=>$s->id,'candidate_id'=>$s->candidate_id,'revision'=>(int)$s->revision,'object_revision'=>(int)$s->object_revision,'expires_at'=>$s->expires_at,'expired'=>$expired,'descriptor'=>$expired?null:json_decode($s->descriptor_json,true,32,JSON_THROW_ON_ERROR),'context'=>$expired?null:json_decode($s->context_json,true,32,JSON_THROW_ON_ERROR),'source_draft_id'=>$s->registered_source_id,'state'=>$expired?'EXPIRED':($s->registered_source_id?'REGISTERED_UNASSESSED':'SAVED_CANDIDATE'),'audit'=>DB::table('sifi_discovery_audit')->where('selection_id',$s->id)->orderByDesc('revision')->limit(10)->get(['revision','action','created_at'])->all()];
  })->all();
 }
}
