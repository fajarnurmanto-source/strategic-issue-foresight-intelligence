<?php
namespace App\Http\Controllers\Sifi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sifi\MonitoringObjectRequest;
use App\Models\SifiMonitoringObject;
use App\Services\Sifi\MonitoringObjectSources;
use Illuminate\Http\{Request,RedirectResponse};
use Illuminate\Support\Facades\{DB,Gate};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\{Inertia,Response};
final class MonitoringObjectController extends Controller {
 public function index(Request $r,MonitoringObjectSources $sources):Response {
  Gate::authorize('viewAny',SifiMonitoringObject::class);
  $query=SifiMonitoringObject::where('user_id',$r->user()->id);
  $total=(clone $query)->count();
  $objects=$query->orderByDesc('updated_at')->limit(100)->get()->map(fn($o)=>$this->view($o))->all();
  return Inertia::render('sifi/monitoring-objects',['objects'=>$objects,'sourceChoices'=>$sources->choices($r->user()),'limit'=>100,'total'=>$total,'hasMore'=>$total>100,'mode'=>'CONFIGURATION_ONLY_COLLECTOR_OFF']);
 }
 public function store(MonitoringObjectRequest $r,MonitoringObjectSources $sources):RedirectResponse {Gate::authorize('create',SifiMonitoringObject::class);return $this->save($r,$sources,null);}
 public function update(MonitoringObjectRequest $r,SifiMonitoringObject $object,MonitoringObjectSources $sources):RedirectResponse {Gate::authorize('update',$object);return $this->save($r,$sources,$object);}
 private function save(MonitoringObjectRequest $r,MonitoringObjectSources $sources,?SifiMonitoringObject $existing):RedirectResponse {
  $v=$r->validated();DB::transaction(function()use($r,$sources,$existing,$v){
   $o=$existing?SifiMonitoringObject::whereKey($existing->id)->lockForUpdate()->firstOrFail():new SifiMonitoringObject(['id'=>(string)Str::uuid(),'user_id'=>$r->user()->id,'revision'=>1]);
   if($existing){Gate::authorize('update',$o);if($o->revision!==(int)$v['revision'])throw ValidationException::withMessages(['revision'=>'Revisi berubah; muat ulang sebelum menyimpan.']);}
   $ids=$sources->validate($r->user(),$v['source_ids']);sort($ids);
   $old=$existing?DB::table('sifi_monitoring_object_sources')->where('object_id',$o->id)->orderBy('source_id')->pluck('source_id')->all():[];
   $previousFilter=$existing?$this->savedFilter($o):null;
   $incomingFilter=array_key_exists('ae16_filter',$v)?$v['ae16_filter']:$previousFilter;
   $normalizedFilter=null;
   if($incomingFilter!==null){
    try{$normalizedFilter=app(\App\Services\Sifi\Ae16UnifiedFilter::class)->normalize($incomingFilter);}
    catch(\Throwable $e){throw ValidationException::withMessages(['ae16_filter'=>'Konfigurasi filter AE-16 tidak valid: '.$e->getMessage()]);}
   }
   $fields=array_intersect_key($v,array_flip(['name','description','category','geography','objective','horizon','state']));
   foreach(['aliases','keywords','exclusions'] as $f)$fields[$f.'_json']=$v[$f];
   $o->fill($fields);if($existing&&!$o->isDirty()&&$ids===$old&&$normalizedFilter===$this->normalizeExisting($previousFilter))return;
   if($existing)$o->revision++;$o->save();
   DB::table('sifi_monitoring_object_sources')->where('object_id',$o->id)->delete();foreach($ids as $id)DB::table('sifi_monitoring_object_sources')->insert(['object_id'=>$o->id,'source_id'=>$id]);
   DB::table('sifi_monitoring_object_audit')->insert(['object_id'=>$o->id,'actor_user_id'=>$r->user()->id,'revision'=>$o->revision,'action'=>$existing?'UPDATED':'CREATED','snapshot_json'=>json_encode($o->toArray()+['source_ids'=>$ids,'collector'=>'OFF','ae16_filter'=>$incomingFilter,'ae16_filter_normalized'=>$normalizedFilter],JSON_THROW_ON_ERROR),'created_at'=>now()]);
  });return redirect()->route('sifi.monitoring-objects.index');
 }
 private function view(SifiMonitoringObject $o):array {
  return $o->only(['id','name','description','category','geography','objective','horizon','state','revision','aliases_json','keywords_json','exclusions_json'])+[
   'ae16_filter'=>$this->savedFilter($o),
  'source_ids'=>DB::table('sifi_monitoring_object_sources')->where('object_id',$o->id)->pluck('source_id')->all(),
   'audit'=>DB::table('sifi_monitoring_object_audit')->where('object_id',$o->id)->orderByDesc('revision')->limit(15)->get(['revision','action','created_at'])->toArray()];
 }
 private function savedFilter(SifiMonitoringObject $o):?array {
  $record=DB::table('sifi_monitoring_object_audit')->where('object_id',$o->id)->orderByDesc('revision')->first(['snapshot_json']);
  if(!$record)return null;
  $snapshot=json_decode((string)$record->snapshot_json,true);
  return is_array($snapshot['ae16_filter']??null)?$snapshot['ae16_filter']:null;
 }
 private function normalizeExisting(?array $filter):?array {
  if($filter===null)return null;
  try{return app(\App\Services\Sifi\Ae16UnifiedFilter::class)->normalize($filter);}
  catch(\Throwable){return null;}
 }

}
