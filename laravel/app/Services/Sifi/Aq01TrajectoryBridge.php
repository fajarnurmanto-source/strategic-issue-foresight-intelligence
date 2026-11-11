<?php
namespace App\Services\Sifi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
/** No scoring/admission; strict deterministic local Python contract. */
final class Aq01TrajectoryBridge {
 /**
  * @param array<string, mixed> $input
  * @return array<string, mixed>
  */
 public function assess(array $input):array {
  $base=(string)config('sifi_aq01.base_url');$token=(string)config('sifi_aq01.token');
  if(strlen($token)<32 || preg_match('#^http://127\.0\.0\.1:[0-9]{2,5}$#D',$base)!==1)
   throw new RuntimeException('Unsafe AQ01 endpoint');
  $nonce=(string)Str::uuid();$payload=$input+['request_nonce'=>$nonce];
  try {$res=Http::withToken($token)->asJson()->acceptJson()->withoutRedirecting()
    ->connectTimeout(1)->timeout(4)->post($base.'/v1/trajectory/assess',$payload);
  }catch(\Throwable $e){throw new RuntimeException('Python unavailable',0,$e);}
  if(!$res->successful()||!is_array($out=$res->json()))throw new RuntimeException('Invalid Python response');
  $required=[
    'schema_version'=>'sifi.trajectory.advisory.v1','mode'=>'OFFLINE_FIXTURE_ONLY',
    'kind'=>'TRAJECTORY_RISK_OPPORTUNITY_DRAFT_AID_ONLY',
    'verification_state'=>'NOT_VERIFIED','evidence_admission'=>'NOT_EVALUATED',
    'issue_object_created'=>false,'trajectory_verified'=>false,'risk_score_available'=>false,
    'risk_evaluated'=>false,'opportunity_evaluated'=>false,
    'candidate_id'=>$input['candidate_id'],'candidate_revision'=>$input['candidate_revision'],
    'case_id'=>$input['case']['id'],'case_revision'=>$input['case']['revision'],
    'draft_id'=>$input['draft']['id'],'draft_revision'=>$input['draft']['revision'],
    'request_nonce'=>$nonce,'observed_metadata'=>$input['observed_metadata'],
    'temporal_observations_verified'=>0,
    'trajectory_marker'=>'NO_VERIFIED_TEMPORAL_SERIES',
    'risk_marker'=>'HYPOTHESIS_ONLY','opportunity_marker'=>'HYPOTHESIS_ONLY',
    'watch_marker'=>'CONDITION_DEFINED_NOT_MONITORED'];
  foreach($required as $key=>$value)if(!array_key_exists($key,$out)||$out[$key]!==$value)
    throw new RuntimeException('Python advisory trust violation: '.$key);
  $keys=array_merge(array_keys($required),['verification_checks']);
  $got=array_keys($out);sort($got);sort($keys);
  if($got!==$keys || !is_array($out['verification_checks']) || !array_is_list($out['verification_checks']) ||
     count($out['verification_checks'])<4||count($out['verification_checks'])>10)
    throw new RuntimeException('Bad advisory schema');
  foreach($out['verification_checks'] as $item)
   if(!is_string($item)||trim($item)===''||mb_strlen($item)>300)
    throw new RuntimeException('Unsafe check');
  unset($out['request_nonce']);return $out;
 }
}
