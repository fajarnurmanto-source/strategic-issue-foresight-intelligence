<?php
namespace App\Services\Sifi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
/** Strictly validated loopback-only Python advisory; never an authority to decide. */
final class Aq01ForesightBridge {
 public function assess(array $input):array {
  $base=(string)config('sifi_aq01.base_url');$token=(string)config('sifi_aq01.token');
  if(strlen($token)<32 || preg_match('#^http://127\.0\.0\.1:[0-9]{2,5}$#D',$base)!==1)
   throw new RuntimeException('Unsafe AQ01 config');
  $nonce=(string)Str::uuid();
  try {$response=Http::withToken($token)->asJson()->acceptJson()->withoutRedirecting()
      ->connectTimeout(1)->timeout(4)->post($base.'/v1/foresight/assess',$input+['request_nonce'=>$nonce]);
  }catch(\Throwable $error){throw new RuntimeException('Python offline',0,$error);}
  if(!$response->successful()||!is_array($out=$response->json()))throw new RuntimeException('Invalid response');
  $required=[
   'schema_version'=>'sifi.foresight.advisory.v1','mode'=>'OFFLINE_FIXTURE_ONLY',
   'kind'=>'SCENARIO_STRUCTURE_DRAFT_AID_ONLY',
   'candidate_id'=>$input['candidate_id'],'candidate_revision'=>$input['candidate_revision'],
   'case_id'=>$input['case']['id'],'case_revision'=>$input['case']['revision'],
   'trajectory_id'=>$input['trajectory']['id'],'trajectory_revision'=>$input['trajectory']['revision'],
   'draft_id'=>$input['draft']['id'],'draft_revision'=>$input['draft']['revision'],
   'request_nonce'=>$nonce,'observed_metadata'=>$input['observed_metadata'],
   'verification_state'=>'NOT_VERIFIED','evidence_admission'=>'NOT_EVALUATED',
   'forecast_available'=>false,'scenario_probability_available'=>false,
   'decision_authorized'=>false,'issue_object_created'=>false,
   'temporal_observations_verified'=>0,
   'foresight_marker'=>'SCENARIO_HYPOTHESES_ONLY_NO_FORECAST_BASIS',
   'scenario_labels'=>['BASELINE','ALTERNATIVE','STRESS']
  ];
  foreach($required as $key=>$value)if(!array_key_exists($key,$out)||$out[$key]!==$value)
   throw new RuntimeException('Python advisory contract violation: '.$key);
  $expected=array_merge(array_keys($required),['verification_checks']);
  $actual=array_keys($out);sort($actual);sort($expected);
  if($actual!==$expected || !is_array($out['verification_checks'])||!array_is_list($out['verification_checks'])||
      count($out['verification_checks'])<4||count($out['verification_checks'])>10)
   throw new RuntimeException('Unexpected advisory keys');
  foreach($out['verification_checks'] as $item)
   if(!is_string($item)||trim($item)===''||mb_strlen($item)>300)
    throw new RuntimeException('Invalid advisory check');
  unset($out['request_nonce']);return $out;
 }
}
