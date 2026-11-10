<?php
namespace App\Services\Sifi;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/** Owner-only UI status/receipts. Never creates or renews permission, source rights or a scheduler. */
final class WorldBankAcquisitionWorkspace
{
 public function __construct(private WorldBankOneShotAcquisition $acquisition) {}
 /** @return array<string,mixed> */
 public function authorization(User $user):array {
  $ready=$this->acquisition->canAcquire($user); // Existing verification + policy + rights remain authoritative.
  $state='NOT_AVAILABLE';$expires=null;$scope=null;$mock=false;
  $path=$this->acquisition->permitPath();
  if(!file_exists($path))$state='MISSING';
  elseif(!is_link($path)&&is_file($path)&&filesize($path)<=16384){
   $f=fopen($path,'r');if($f){try{flock($f,LOCK_SH);$raw=stream_get_contents($f,16385);$p=json_decode(Crypt::decryptString($raw),true,16,JSON_THROW_ON_ERROR);
    if(($p['owner_id']??null)===(int)$user->id && ($p['version']??null)===1 && ($p['endpoint']??null)===WorldBankGdpSourceAdapter::ENDPOINT
     && in_array($p['kind']??null,['OWNER_APPROVED_EXTERNAL_GET','MOCK_TEST_ONLY'],true)
     && is_int($p['expires_at']??null)&&is_bool($p['consumed']??null)){
     $state=$p['consumed']?'CONSUMED':($p['expires_at']<now()->timestamp?'EXPIRED':($ready?'READY':'BLOCKED'));
     $expires=gmdate('c',$p['expires_at']);$scope=preg_match('/\A[0-9a-f]{64}\z/',$p['scope_sha256']??'')?$p['scope_sha256']:null;
     $mock=$p['kind']==='MOCK_TEST_ONLY';
    }
   }catch(\Throwable){}finally{flock($f,LOCK_UN);fclose($f);}}
  }
  return ['state'=>$state,'can_acquire'=>$ready,'expires_at'=>$expires,'scope_sha256'=>$scope,'mock'=>$mock,'issuance'=>'NO_SELF_GRANT','max_records'=>3,'max_bytes'=>65536,'attempts'=>1,'connect_seconds'=>2,'total_seconds'=>5,'retry'=>0,'scheduler'=>false];
 }
 private function directory(string $kind):string {return storage_path('app/private/sifi-worldbank-'.$kind);}
 /** Bounded scan; no foreign user metadata, payload, file path or approval reference is returned. */
 /** @return array{items:list<array<string,mixed>>,truncated:bool} */
 private function entries(string $kind,User $user):array {
  $rows=[];$count=0;$bytes=0;$truncated=false;$dir=$this->directory($kind);
  if(!is_dir($dir)||is_link($dir))return ['items'=>[],'truncated'=>false];
  foreach(new \FilesystemIterator($dir,\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::CURRENT_AS_FILEINFO) as $file){
   if(!$file instanceof \SplFileInfo)continue;
   if(++$count>200){$truncated=true;break;}
   if($file->isLink()||!$file->isFile()||$file->getExtension()!=='enc'||!Str::isUuid($file->getBasename('.enc')))continue;
   $size=$file->getSize();if($size>262144||($bytes+=$size)>8388608){$truncated=true;break;}
   $encrypted=file_get_contents($file->getPathname());if($encrypted===false)continue;
   try{$r=json_decode(Crypt::decryptString($encrypted),true,24,JSON_THROW_ON_ERROR);}catch(\Throwable){continue;}
   if(!is_array($r))continue;
   if(($r['owner_id']??null)!==(int)$user->id||($r['id']??null)!==$file->getBasename('.enc'))continue;
   if($kind==='acquisitions'){
    if(!in_array($r['permit_kind']??null,['OWNER_APPROVED_EXTERNAL_GET','MOCK_TEST_ONLY'],true))continue;
    if($r['permit_kind']==='MOCK_TEST_ONLY'&&!app()->environment('testing'))continue;
    $rows[]=['id'=>$r['id'],'at'=>$r['retrieval']['retrieved_at']??null,'retention_until'=>$r['retention_until']??null,'state'=>strtotime($r['retention_until']??'')<now()->timestamp?'RETENTION_EXPIRED':'AVAILABLE','mock'=>$r['permit_kind']==='MOCK_TEST_ONLY'];
   }else{
    $rows[]=['id'=>$r['id'],'at'=>$r['at']??null,'state'=>$r['state']??'BLOCKED','reason'=>$r['reason']??null,'receipt_id'=>$r['receipt_id']??null,'transport'=>$r['transport']??null];
   }
  }
  usort($rows,fn($a,$b)=>strcmp($b['at']??'',$a['at']??''));
  return ['items'=>array_slice($rows,0,20),'truncated'=>$truncated||count($rows)>20];
 }
 /** @return array<string,mixed> */
 public function view(User $user):array {return ['authorization'=>$this->authorization($user),'receipts'=>$this->entries('acquisitions',$user),'attempts'=>$this->entries('attempts',$user)];}
 /** @return array<string,mixed> */
 public function run(User $user):array {
  $p=$this->acquisition->acquire($user); // One call only; existing permission is exclusively consumed before network.
  $id=(string)Str::uuid();$record=['id'=>$id,'owner_id'=>(int)$user->id,'at'=>now()->utc()->toIso8601String(),'state'=>$p['status'],'reason'=>$p['reason']??null,'receipt_id'=>$p['receipt_id']??null,'transport'=>$p['transport']??null];
  $dir=$this->directory('attempts');if(is_link($dir))throw new \RuntimeException('ATTEMPT_STORAGE_UNAVAILABLE');
  if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new \RuntimeException('ATTEMPT_STORAGE_UNAVAILABLE');
  $file=$dir.'/'.$id.'.enc';$f=fopen($file,'xb');if(!$f)throw new \RuntimeException('ATTEMPT_STORAGE_UNAVAILABLE');
  try{chmod($file,0600);$encoded=Crypt::encryptString(json_encode($record,JSON_THROW_ON_ERROR));if(fwrite($f,$encoded)!==strlen($encoded)||!fflush($f))throw new \RuntimeException('ATTEMPT_STORAGE_UNAVAILABLE');}finally{fclose($f);}
  return $p+['attempt_id'=>$id];
 }
}
