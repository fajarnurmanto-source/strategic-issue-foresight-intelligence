<?php
namespace App\Services\Sifi;
use App\Models\SifiMonitoringObject;
use Illuminate\Validation\ValidationException;
/** Bounded local descriptor matching only. No HTTP/DNS, access decision or evidence admission. */
final class DiscoveryCatalog {
 public const TYPES=['API','RSS','ATOM','SITEMAP','OPEN_DATA','NEWS','RESEARCH','REGULATORY','USER_SUBMITTED'];
 /** User-supplied source URL intake: classification of URL only, no network or content fetch. */
 /** @return array<string,mixed> */
 public function submittedDescriptor(string $input):array {
  $url=$this->safeUrl(trim($input),false);
  $host=strtolower((string)parse_url($url,PHP_URL_HOST));
  $platform=match(true){
   preg_match('/(?:^|\.)instagram\.com$/',$host)===1=>'INSTAGRAM',
   preg_match('/(?:^|\.)tiktok\.com$/',$host)===1=>'TIKTOK',
   preg_match('/(?:^|\.)(?:youtube\.com|youtu\.be)$/',$host)===1=>'YOUTUBE',
   in_array($host,['x.com','www.x.com','twitter.com','www.twitter.com'],true)=>'X',
   preg_match('/(?:^|\.)facebook\.com$/',$host)===1=>'FACEBOOK',
   preg_match('/(?:^|\.)linkedin\.com$/',$host)===1=>'LINKEDIN',
   preg_match('/(?:^|\.)reddit\.com$/',$host)===1=>'REDDIT',
   default=>'WEB_OR_UNKNOWN'
  };
  $path=(string)parse_url($url,PHP_URL_PATH);
  $media=preg_match('/\.pdf$/i',$path)?'DOCUMENT':($platform==='YOUTUBE'||$platform==='TIKTOK'?'VIDEO':($platform==='INSTAGRAM'?'SOCIAL_MIXED':'UNKNOWN'));
  $key='submitted-'.substr(hash('sha256',$url),0,24);
  return ['key'=>$key,'title'=>'[URL SUBMISSION] '.$host,'url'=>$url,'type'=>'USER_SUBMITTED','language'=>'unknown','geography'=>null,'origin'=>'USER_SUBMITTED_URL','snippet'=>'URL diajukan pengguna; identitas penerbit, konten, waktu publikasi dan hak akses belum diperiksa.','topics'=>[],'rights'=>'UNASSESSED','attribution'=>'URL submitted by account owner; source attribution requires review.','coverage'=>'URL sahaja; BELUM dihubungi, bukan hasil crawling atau bukti peristiwa.','published_at'=>null,'synthetic'=>false,'platform'=>$platform,'media_hint'=>$media];
 }
 /** @return list<array<string,mixed>> */
 private function syntheticEntries():array {
  $contents=file_get_contents(resource_path('sifi/source-discovery-catalog.json'));
  if($contents===false)throw ValidationException::withMessages(['catalog'=>'Katalog tidak tersedia.']);
  $data=json_decode($contents,true,32,JSON_THROW_ON_ERROR);
  if(!is_array($data)||($data['version']??null)!=='ae13-catalog-1'||!is_array($data['entries']??null)||!array_is_list($data['entries'])||count($data['entries'])>100)throw ValidationException::withMessages(['catalog'=>'Katalog tidak valid.']);
  foreach($data['entries'] as $entry)if(!is_array($entry))throw ValidationException::withMessages(['catalog'=>'Katalog memuat entri tidak valid.']);
  return $data['entries'];
 }
 /** @return list<array<string,mixed>> */
 public function entries():array {
  $contents=file_get_contents(resource_path('sifi/ae16-public-source-directory.json'));
  if($contents===false)throw ValidationException::withMessages(['catalog'=>'Direktori sumber publik tidak tersedia.']);
  $curated=json_decode($contents,true,32,JSON_THROW_ON_ERROR);
  if(!is_array($curated)||($curated['version']??null)!=='ae16-curated-source-directory-1'||!is_array($curated['entries']??null)||!array_is_list($curated['entries'])||count($curated['entries'])>40)throw ValidationException::withMessages(['catalog'=>'Direktori sumber publik tidak valid.']);
  foreach($curated['entries'] as $e){
   if(!is_array($e))throw ValidationException::withMessages(['catalog'=>'Direktori memuat entri tidak valid.']);
   $this->assertDescriptor($e);
  }
  return array_merge($curated['entries'],$this->syntheticEntries());
 }
 /** @return array<string,mixed> */
 public function descriptor(string $key):array {
  foreach($this->entries() as $e)if($e['key']===$key){$this->assertDescriptor($e);return $e;}
  throw ValidationException::withMessages(['candidate'=>'Kandidat tidak tersedia.']);
 }
 public function safeUrl(string $url,bool $synthetic=false):string {
  $p=parse_url($url);$h=strtolower($p['host']??'');
  $bad=strlen($url)>500||preg_match('/[\x00-\x20\x7f\\\\%]/',$url)||!$p||($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['fragment'])||(isset($p['port'])&&$p['port']!==443);
  $bad=$bad||!preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z/',$h)||!str_contains($h,'.')||str_contains($h,'..')||filter_var($h,FILTER_VALIDATE_IP)||preg_match('/(?:\A|\.)(?:localhost|local|internal|lan|home|test)\z/',$h)||preg_match('/\A[0-9.]+\z/',$h)||str_starts_with($h,'0x');
  $bad=$bad||(!$synthetic&&preg_match('/\.(?:invalid|example)\z/',$h));
  if($bad)throw ValidationException::withMessages(['url'=>'Gunakan URL HTTPS publik tanpa kredensial, port khusus, fragment atau alamat internal. URL belum dihubungi atau diperiksa DNS.']);
  return 'https://'.$h.($p['path']??'/').(isset($p['query'])?'?'.$p['query']:'');
 }
 /** @param array<string,mixed> $e */
 public function assertDescriptor(array $e):void {
  if(($e['rights']??null)!=='UNASSESSED'||!in_array($e['type']??null,self::TYPES,true))throw ValidationException::withMessages(['candidate'=>'Descriptor tidak memenuhi kontrak hak/kategori.']);
  if(($e['origin']??null)==='USER_SUBMITTED_URL'){
   if(($e['synthetic']??null)!==false||$e['type']!=='USER_SUBMITTED')throw ValidationException::withMessages(['candidate'=>'Descriptor URL pengguna tidak valid.']);
   if($this->submittedDescriptor((string)($e['url']??''))!==$e)throw ValidationException::withMessages(['candidate'=>'Descriptor URL pengguna berubah.']);
   return;
  }
  $synthetic=($e['synthetic']??null)===true;
  if($synthetic){
   if(!str_starts_with($e['title']??'','[SYNTHETIC]'))throw ValidationException::withMessages(['candidate'=>'Label synthetic tidak sah.']);
  }elseif(($e['synthetic']??null)!==false||($e['origin']??null)!=='CURATED_PUBLIC_DIRECTORY'||str_starts_with($e['title']??'','[SYNTHETIC]')){
   throw ValidationException::withMessages(['candidate'=>'Descriptor sumber publik tidak sah.']);
  }
  $this->safeUrl($e['url'],$synthetic);
 }
 /** @return list<string> */
 private function tokens(string $s):array {
  $stop=['synthetic','fiktif','merek','perusahaan','industri','publik','tentang','untuk','dengan','yang','dan','atau','bukan','informasi','memantau','perkembangan','keterbatasan','secara','objek','pemantauan','analisis','kategori','terbuka','mengenali','mengenai','sumber','menelusuri','contoh','di','ke','ini','itu'];
  return array_slice(array_values(array_unique(array_filter(preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($s))?:[],fn($x)=>mb_strlen($x)>=2&&!in_array($x,$stop,true)))),0,80);
 }
 private function matches(string $hay,string $term):bool {return (bool)preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term,'/').'(?![\p{L}\p{N}])/u',$hay);}
 /** Internal fixture lookup. Shares ranking with curated discovery and reads only the pinned fixture catalog. */
 /**
  * @param array<string,mixed> $filters
  * @return list<array<string,mixed>>
  */
 public function discoverSynthetic(SifiMonitoringObject $object,array $filters):array {
  $entries=$this->matchEntries($object,$filters,$this->syntheticEntries(),true);
  return array_slice($entries,0,(int)($filters['limit']??12));
 }
 /**
  * @param array<string,mixed> $filters
  * @param list<array<string,mixed>> $entries
  * @return list<array<string,mixed>>
  */
 private function matchEntries(SifiMonitoringObject $o,array $filters,array $entries,bool $requireSynthetic=false):array {
  $q=$filters['q']??'';$out=[];
  foreach($entries as $e){
   $this->assertDescriptor($e);
   if($requireSynthetic&&($e['synthetic']??null)!==true)throw ValidationException::withMessages(['catalog'=>'Katalog fixture memuat descriptor non-synthetic.']);
   if(isset($filters['type'])&&$filters['type']!==''&&$e['type']!==$filters['type'])continue;
   if(($filters['language']??'')!==''&&$e['language']!==$filters['language'])continue;
   if(($filters['geography']??'')!==''&&!str_contains(mb_strtolower($e['geography']??''),mb_strtolower($filters['geography'])))continue;
   $hay=mb_strtolower(implode(' ',[$e['title'],implode(' ',$e['topics']),$e['geography']??'']));
   foreach($o->exclusions_json as $x)if($x!==''&&str_contains($hay,mb_strtolower($x)))continue 2;
   $reasons=[];$score=0;
   foreach(['alias'=>implode(' ',$o->aliases_json),'keyword'=>implode(' ',$o->keywords_json),'nama'=>$o->name,'tujuan'=>$o->objective,'kategori'=>$o->category,'query'=>$q] as $kind=>$text){
    $hits=array_values(array_filter($this->tokens($text),fn($t)=>$this->matches($hay,$t)));
    if($hits){$reasons[]=$kind.': '.implode(', ',array_slice($hits,0,8));$score+=count($hits)*($kind==='alias'?4:2);}
   }
   if($q!==''&&!array_filter($this->tokens($q),fn($t)=>$this->matches($hay,$t)))continue;
   if(!$score)continue;
   if($o->geography&&str_contains($hay,mb_strtolower($o->geography))){$reasons[]='geografi: '.$o->geography;$score+=2;}
   $out[]=$e+['reasons'=>$reasons,'match_points'=>$score,'dimensions'=>['relevance'=>'DETERMINISTIC_TERM_MATCH','credibility'=>'UNKNOWN','freshness'=>'UNKNOWN','availability'=>'NOT_CONTACTED','cost'=>'UNKNOWN','rights'=>'UNASSESSED','origin'=>$e['origin']]];
  }
  usort($out,fn($a,$b)=>($b['match_points']<=>$a['match_points'])?:strcmp($a['key'],$b['key']));
  return $out;
 }
 /**
  * @param array<string,mixed> $filters
  * @return array{entries:list<array<string,mixed>>,total_matches:int,catalog_size:int,provider:string,version:string,catalog_sha256:string,retrieved_at:null,rights:string,network:string,coverage:string}
  */
 public function discover(SifiMonitoringObject $o,array $filters,bool $includeSynthetic=false):array {
  $entries=$this->entries();
  $visible=$includeSynthetic?$entries:array_values(array_filter($entries,static fn ($entry)=>($entry['synthetic']??false)!==true));
  $out=$this->matchEntries($o,$filters,$visible);

  if(!empty($filters['candidate_url'])){
   $submitted=$this->submittedDescriptor((string)$filters['candidate_url']);
   $submitted['reasons']=['URL sumber disediakan pengguna; belum dihubungi'];
   $submitted['match_points']=10000;
   $submitted['dimensions']=['platform'=>$submitted['platform'],'media_hint'=>$submitted['media_hint'],'rights'=>'UNASSESSED','availability'=>'NOT_CONTACTED','credibility'=>'UNKNOWN'];
   array_unshift($out,$submitted);
  }
  usort($out,fn($a,$b)=>($b['match_points']<=>$a['match_points'])?:strcmp($a['key'],$b['key']));
  return ['entries'=>array_slice($out,0,(int)($filters['limit']??12)),'total_matches'=>count($out),'catalog_size'=>count($entries),'provider'=>!empty($filters['candidate_url'])?'USER_SUBMITTED_URL+CURATED_PUBLIC_DIRECTORY':'CURATED_PUBLIC_DIRECTORY','version'=>'ae16-curated-source-directory-1','catalog_sha256'=>hash('sha256',hash_file('sha256',resource_path('sifi/source-discovery-catalog.json')).hash_file('sha256',resource_path('sifi/ae16-public-source-directory.json'))),'retrieved_at'=>null,'rights'=>'UNASSESSED','network'=>'OFF','coverage'=>'Direktori publik dan URL yang dimasukkan pengguna; hanya metadata lokal, bukan pencarian/crawling live maupun jaminan arsip 2025. Tidak ada artikel, video, atau data sumber yang diambil. Akusisi memerlukan review hak dan izin terpisah.'];
 }
}
