<?php
namespace App\Services\Sifi;
use App\Models\{User,SifiSourceDraft};
use Illuminate\Validation\ValidationException;
/** Context references only. Reuses existing closed rights decoder; never grants acquisition/admission. */
final class MonitoringObjectSources {
 public function eligibility(SifiSourceDraft $s):array {
  if($s->source_type!=='OPEN_DATA'||!in_array($s->homepage_url,[WorldBankGdpSourceAdapter::DOCUMENTATION,WorldBankGdpSourceAdapter::ENDPOINT],true))return ['allowed'=>false,'reason'=>'NO_EXISTING_REVIEWED_RIGHTS_CONTRACT'];
  $p=app(WorldBankGdpSourceAdapter::class)->snapshot();
  return ['allowed'=>$p['status']==='READY_FOR_ANALYST_DRAFT','reason'=>$p['status']==='READY_FOR_ANALYST_DRAFT'?'REVIEWED_DATASET_CONTEXT_ONLY':$p['reason']];
 }
 public function choices(User $u):array {
  return SifiSourceDraft::where('user_id',$u->id)->orderBy('name')->limit(150)->get()->map(fn($s)=>['id'=>$s->id,'name'=>$s->name,'rights'=>$this->eligibility($s),'collector'=>'OFF'])->all();
 }
 public function validate(User $u,array $ids):array {
  $rows=SifiSourceDraft::where('user_id',$u->id)->whereIn('id',$ids)->lockForUpdate()->get();
  if($rows->count()!==count($ids))throw ValidationException::withMessages(['source_ids'=>'Sumber tidak tersedia atau bukan milik akun ini.']);
  foreach($rows as $s)if(!$this->eligibility($s)['allowed'])throw ValidationException::withMessages(['source_ids'=>'Hak penggunaan referensi sumber belum memenuhi kontrak existing. Tidak memberikan izin pengambilan.']);
  return $ids;
 }
}
