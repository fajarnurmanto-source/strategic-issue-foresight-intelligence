<?php
namespace App\Services\Sifi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
/** Loopback, read-derived, tightly verified offline scope-label review. */
final class Aq01IssueScopeBridge {
 /**
  * @param array<string, mixed> $input
  * @return array<string, mixed>
  */
 public function assess(array $input):array {
  $base=(string)config('sifi_aq01.base_url');$token=(string)config('sifi_aq01.token');
  if(strlen($token)<32||preg_match('#^http://127\.0\.0\.1:[0-9]{2,5}$#D',$base)!==1)
    throw new RuntimeException('Unsafe connection');
  $nonce=(string)Str::uuid();$payload=$input+['request_nonce'=>$nonce];
  try {$res=Http::withToken($token)->asJson()->acceptJson()->withoutRedirecting()
    ->connectTimeout(1)->timeout(4)->post($base.'/v1/issue-scope/assess',$payload);
  }catch(\Throwable $e){throw new RuntimeException('Python unavailable',0,$e);}
  if(!$res->successful()||!is_array($out=$res->json())) throw new RuntimeException('Bad Python response');
  $required=['schema_version'=>'sifi.issue_scope.advisory.v1','mode'=>'OFFLINE_FIXTURE_ONLY',
    'kind'=>'SCOPE_LABEL_REVIEW_AID_ONLY','status'=>'NOT_VERIFIED','relevance_verified'=>false,
    'issue_object_created'=>false,'evidence_admission'=>'NOT_EVALUATED',
    'candidate_id'=>$input['candidate_id'],'candidate_revision'=>$input['candidate_revision'],
    'case_id'=>$input['case']['id'],'case_revision'=>$input['case']['revision'],
    'request_nonce'=>$nonce,'claim_count'=>$input['claim_count'],'event_count'=>$input['event_count'],
    'link_count'=>$input['link_count']];
  foreach($required as $key=>$value) if(!array_key_exists($key,$out)||$out[$key]!==$value)
   throw new RuntimeException('Trust contract violation');
  $keys=array_merge(array_keys($required),['scope_marker','domain_label_match','region_label_match','verification_checks']);
  $actual=array_keys($out);sort($keys);sort($actual);
  if($keys!==$actual || !in_array($out['scope_marker'],['NO_PROFILE','EXACT_LABEL_OVERLAP_ONLY','NO_EXACT_LABEL_OVERLAP'],true)
     || !is_bool($out['domain_label_match']) || !is_bool($out['region_label_match']) ||
     !is_array($out['verification_checks']) || !array_is_list($out['verification_checks']) ||
     count($out['verification_checks'])<4 || count($out['verification_checks'])>10)
    throw new RuntimeException('Response schema mismatch');
  $coverage=$input['coverage'];
  $expectedDomain=$coverage!==null && in_array(mb_strtolower(trim($input['case']['issue_domain'])),
    array_map(fn($v)=>mb_strtolower(trim($v)),$coverage['domains']),true);
  $expectedRegion=$coverage!==null && mb_strtolower(trim($input['case']['geography_label']))===
    mb_strtolower(trim($coverage['region_name']));
  $expectedMarker=$coverage===null?'NO_PROFILE':(($expectedDomain||$expectedRegion)?
    'EXACT_LABEL_OVERLAP_ONLY':'NO_EXACT_LABEL_OVERLAP');
  if($out['domain_label_match']!==$expectedDomain || $out['region_label_match']!==$expectedRegion ||
      $out['scope_marker']!==$expectedMarker)
    throw new RuntimeException('False scope claims');
  foreach($out['verification_checks'] as $check)
   if(!is_string($check) || trim($check)==='' || mb_strlen($check)>300)
     throw new RuntimeException('Unsafe verification check');
  unset($out['request_nonce']);return $out;
 }
}
