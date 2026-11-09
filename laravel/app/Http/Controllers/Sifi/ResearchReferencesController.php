<?php
namespace App\Http\Controllers\Sifi;
use App\Http\Controllers\Controller;
use App\Models\SifiMonitoringObject;
use App\Services\Sifi\{MultiSourceResearchWorkspace,ResearchRetrievalWorkspace};
use Illuminate\Http\Request;

final class ResearchReferencesController extends Controller
{
 public function download(Request $request,SifiMonitoringObject $object,string $session):\Illuminate\Http\JsonResponse
 {
  $row=app(ResearchRetrievalWorkspace::class)->session($request->user(),$object,$session);$m=json_decode($row->results_json,true,32,JSON_THROW_ON_ERROR)['multi_source']??null;
  abort_unless($m&&$m['state']==='SAVED',409,'Simpan penelitian sebelum mengunduh referensi.');abort_unless(hash_equals($m['envelope_sha256'],app(MultiSourceResearchWorkspace::class)->digest($m['envelope'])),409,'Integritas hasil gagal.');
  $selected=array_flip($m['selected_ids']);$entries=array_values(array_filter($m['envelope']['entries'],fn($e)=>isset($selected[$e['id']])));
  return response()->json(['format'=>'sifi-references-1','question'=>$row->question,'object_id'=>$object->id,'session_id'=>$row->id,'mode'=>$m['envelope']['mode'],'envelope_sha256'=>$m['envelope_sha256'],'saved_at'=>$m['saved_at'],'analyst_note'=>$m['analyst_note'],'query_plan'=>$m['envelope']['multilingual_plan']??null,'collector'=>$m['envelope']['collector']??null,'feed_plan'=>$m['envelope']['feed']??null,'provider_calls'=>$m['envelope']['calls'],'references'=>$entries,'limits'=>$m['envelope']['budget'],'coverage'=>$m['envelope']['coverage'],'evidence_admission'=>'NONE','notice'=>'Daftar referensi metadata; bukan dokumen, kutipan terverifikasi, atau evidence.'],200,['Content-Disposition'=>'attachment; filename="sifi-references-'.$row->id.'.json"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
 }
}
