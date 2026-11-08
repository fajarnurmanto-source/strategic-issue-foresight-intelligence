<?php
namespace App\Services\Sifi;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Process\Process;

/** AE10 transport, decoder and receipts; fresh research-context authority namespace. */
class ResearchWorldBankAcquisition extends WorldBankOneShotAcquisition
{
 public function __construct(public readonly array $context=[]){}
 public static function digest(array $v):string{return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));}
 public function permitPath():string{return storage_path('app/private/sifi-research-worldbank/'.self::digest($this->context).'.permit.enc');}
 protected function receiptPath(string $id):string{return storage_path('app/private/sifi-research-worldbank-receipts/'.$id.'.enc');}
 private function authority():?array{ $f=$this->permitPath();if(!is_file($f)||is_link($f)||filesize($f)>16384)return null;try{$p=json_decode(Crypt::decryptString(file_get_contents($f)),true,24,JSON_THROW_ON_ERROR);}catch(\Throwable){return null;}return is_array($p)?$p:null; }
 private function receiptId(array $p):string{$h=hash('sha256',self::digest($this->context).'|'.$p['nonce']);return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-a'.substr($h,17,3).'-'.substr($h,20,12);}
 public function expectedReceiptId():?string{$p=$this->authority();return $p&&$this->contextValid($p)?$this->receiptId($p):null;}
 private function contextValid(array $p):bool{return ($p['research_authorization_version']??null)===2&&($p['research_context']??null)===$this->context&&($p['method']??null)==='GET'&&is_string($p['nonce']??null)&&(bool)preg_match('/\A[a-f0-9]{64}\z/',$p['nonce']);}
 public function authorityState(User $u):string{if(!is_file($this->permitPath()))return 'MISSING';$p=$this->authority();if(!$p||!$this->contextValid($p)||($p['owner_id']??null)!==(int)$u->id)return 'BLOCKED';if(($p['consumed']??false)===true)return 'CONSUMED';if(($p['expires_at']??0)<=now()->timestamp)return 'EXPIRED';return parent::canAcquire($u)?'READY':'BLOCKED';}
 protected function permitMatchesContext(array $permit):bool{return $this->contextValid($permit);}
 protected function newReceiptId(array $permit):string{return $this->receiptId($permit);}
 protected function decorateRecord(array $record,array $permit):array{return $record+['research_context'=>$this->context,'research_nonce_sha256'=>hash('sha256',$permit['nonce'])];}

 public function publicAddress(array $addresses):string{if(!$addresses||count($addresses)>20)throw new \RuntimeException('AE10_DNS_POLICY');foreach($addresses as $ip)if(!is_string($ip)||!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new \RuntimeException('AE10_DNS_POLICY');foreach($addresses as $ip){$number=ip2long($ip);foreach([['100.64.0.0',10],['192.0.0.0',24],['192.0.2.0',24],['198.18.0.0',15],['198.51.100.0',24],['203.0.113.0',24],['224.0.0.0',4],['240.0.0.0',4]] as [$network,$bits]){if(($number&(-1<<(32-$bits)))===(ip2long($network)&(-1<<(32-$bits))))throw new \RuntimeException('AE10_DNS_POLICY');}}sort($addresses,SORT_STRING);return $addresses[0];}
 protected function resolveAddress():string{$p=new Process(['/usr/bin/getent','ahostsv4','api.worldbank.org'],null,['PATH'=>'/usr/bin:/bin']);$p->setTimeout(1);$p->run();if(!$p->isSuccessful())throw new \RuntimeException('AE10_DNS_POLICY');$ips=[];foreach(explode("\n",trim($p->getOutput())) as $line){$parts=preg_split('/\s+/',$line);if($parts[0]??'')$ips[]=$parts[0];}return $this->publicAddress(array_values(array_unique($ips)));}
 protected function request(string $endpoint,array $options):\Illuminate\Http\Client\Response{if($endpoint!==WorldBankGdpSourceAdapter::ENDPOINT)throw new \RuntimeException('AE10_DNS_POLICY');$ip=$this->resolveAddress();$options['curl'][CURLOPT_RESOLVE]=['api.worldbank.org:443:'.$ip];return \Illuminate\Support\Facades\Http::withOptions($options)->connectTimeout(2)->timeout(4)->get($endpoint);}

 protected function decode(array $record):array{
  if(($record['research_context']??null)!==$this->context)return $this->emptyPreview('RESEARCH_RECEIPT_CONTEXT_MISMATCH');$p=parent::decode($record);if($p['status']!=='READY_FOR_ANALYST_DRAFT')return $p;
  $raw=$record['raw'];$offset=strpos($raw,'"value"');if($offset===false)return $this->emptyPreview('DOCUMENT_SPAN_UNAVAILABLE');$text=mb_strcut($raw,$offset,80,'UTF-8');$end=$offset+strlen($text);
  $doc=['body'=>$raw,'body_sha256'=>hash('sha256',$raw),'bytes'=>strlen($raw),'parser_version'=>'existing-wb-v2-json/ae14-net02-2','retrieved_at'=>$record['retrieval']['retrieved_at'],'published_at'=>null,'dataset_updated_on'=>$p['source']['source_updated_at'],'mode'=>$p['mode'],'quote'=>['byte_start'=>$offset,'byte_end'=>$end,'text'=>$text],'rights'=>$p['source']['rights_state'],'admission'=>'NOT_EVALUATED'];
  $p['research_context']=$this->context;$p['document']=$doc;foreach($p['observations'] as &$o)$o['provenance']+=['research_context'=>$this->context,'document'=>$doc];unset($o);return $p;
 }
}
