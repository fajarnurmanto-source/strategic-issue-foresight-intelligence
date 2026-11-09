<?php
namespace App\Services\Sifi;
use App\SifiStructured\Context;
/** Read-only promotion adapter for the verified explicit fixture rules. */
class ClaimComparisonPreview {
 private const CATALOG_SHA256='19aee4e35e88510decd9b8ff07e2c44065ab3ecc49435c70565e05818a5c1408';
 private function catalog():array {
  $path=resource_path('sifi/claim-comparison-fixtures.v1.json');
  if(!is_file($path)||!hash_equals(self::CATALOG_SHA256,hash_file('sha256',$path)))throw new \RuntimeException('FIXTURE_CATALOG_UNAVAILABLE');
  $c=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
  if(($c['schema_version']??null)!=='sifi.claim.preview.fixture.catalog.v1'||($c['not_verified']??null)!==true||($c['custody']??null)!=='NOT_ATTESTED')throw new \RuntimeException('FIXTURE_CATALOG_UNAVAILABLE');return $c;
 }
 public function pairs():array {try{$out=[];foreach($this->catalog()['pairs'] as $id=>$p)$out[]=['id'=>$id,'label'=>$p['label']];return $out;}catch(\Throwable){return [];}}
 public function evaluate(array $input,array $v):array {
  $out=['schema_version'=>'sifi.claim.product.preview.v1','kind'=>'NON_BINDING','verification'=>'NOT_VERIFIED','mode'=>$v['mode']==='fixture'?'OFFLINE_FIXTURE_BASED':'CANDIDATE_INPUT','status'=>'ABSTAIN','candidate_id'=>$input['candidate_id'],'candidate_revision'=>$input['candidate_revision'],'input_digest'=>$v['input_digest'],'request_nonce'=>$v['request_nonce'],'cutoff'=>$v['cutoff'],'fixture_pair'=>$v['fixture_pair']??null,'comparison'=>null,'snapshots'=>[],'claims_verified'=>false,'independence_verified'=>false,'review_authorized'=>false,'evidence_admission'=>'NOT_EVALUATED','historical_custody'=>'NOT_ATTESTED','reason_codes'=>[]];
  if($v['mode']==='candidate'){$out['reason_codes']=count($input['claims'])?['STRUCTURED_CONTEXT_NOT_ESTABLISHED','HISTORICAL_CUSTODY_NOT_ATTESTED']:['NO_CLAIM_DRAFTS','HISTORICAL_CUSTODY_NOT_ATTESTED'];return $out;}
  try{
   $c=$this->catalog();$pair=$c['pairs'][$v['fixture_pair']]??null;if(!$pair)throw new \RuntimeException('UNKNOWN_FIXTURE_PAIR');
   $rows=[];$cutoff=substr($v['cutoff'],0,-1).'.000000Z';
   foreach($pair['selection'] as $id){
    $entry=$c['contexts'][$id]??null;if(!$entry)throw new \RuntimeException('FIXTURE_CATALOG_UNAVAILABLE');
    $body=$entry['body'];$body['candidate_id']=$input['candidate_id'];$context=Context::from($body);$a=$context->data;
    if($entry['available_at_synthetic']>$cutoff||$a['observed_at']>$cutoff){$out['reason_codes']=['NO_SYNTHETIC_CONTEXT_AT_CUTOFF'];return $out;}
    if($a['provenance_mode']!=='SYNTHETIC_EXPLICIT'||$a['signal_ref']!==Context::from($c['contexts']['201']['body'])->data['signal_ref'])throw new \RuntimeException('FIXTURE_PROVENANCE_INVALID');
    $rows[]=$a;$out['snapshots'][]=['fixture_context_id'=>$id,'revision'=>1,'context_digest'=>$context->digest(),'availability'=>'SYNTHETIC_DECLARATION_NOT_HISTORICAL_RECEIPT'];
   }
   [$a,$b]=$rows;
   if($a['claim_id']===$b['claim_id']){$out['reason_codes']=['DUPLICATE_CLAIM_LINEAGE'];return $out;}
   foreach(['event_id','place_id','scope_id','time_start','time_end'] as $k)if($a[$k]!==$b[$k]){$out['reason_codes']=['CONTEXT_SCOPE_MISMATCH'];return $out;}
   if($a['modality']!=='ASSERTED'||$b['modality']!=='ASSERTED'){$out['reason_codes']=['MODALITY_NOT_ELIGIBLE'];return $out;}
   foreach(['subject_id','predicate_id','object_id'] as $k)if($a[$k]!==$b[$k]){$out['reason_codes']=['PROPOSITION_NOT_EXACTLY_ALIGNED'];return $out;}
   $out['status']='ADVISORY';$out['comparison']=['classification'=>$a['polarity']===$b['polarity']?'STRUCTURED_MATCH':'POTENTIAL_CONFLICT','explanation'=>$a['polarity']===$b['polarity']?'Proposisi, modality, dan konteks sintetis identik. Ini tidak membuktikan kebenaran.':'Polaritas eksplisit berlawanan dalam konteks sintetis yang sama. Ini potensi konflik, bukan kontradiksi faktual yang disahkan.'];
   $out['reason_codes']=['SYNTHETIC_STRUCTURED_CONTEXT_ONLY','SOURCE_COUNT_NOT_INDEPENDENCE','HISTORICAL_CUSTODY_NOT_ATTESTED'];return $out;
  }catch(\Throwable){$out['snapshots']=[];$out['reason_codes']=['FIXTURE_CATALOG_UNAVAILABLE'];return $out;}
 }
}
