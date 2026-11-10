<?php
namespace App\Services\Sifi;
use App\Contracts\SifiResearchProvider;

/** Experimental keyless candidate. Only metadata links may persist pending snippet rights review. */
final class TavilySearchProvider implements SifiResearchProvider
{
 public function search(array $context):array{return $this->normalize(app(($context['mode']??null)==='LIVE'?OneShotSearchTransport::class:BoundedSearchTransport::class)->get('TAVILY_KEYLESS',$context),$context);}
 public function recover(array $context):array{return $this->normalize(app(OneShotSearchTransport::class)->recover('TAVILY_KEYLESS',$context),$context);}
 private function normalize(array $p,array $c):array
 {
  $body=$p['body'];unset($p['body']);if($p['state']!=='OK')return $p;$p['results']=[];
  try{
   if(!is_array($body['results']??null)||!array_is_list($body['results'])||count($body['results'])>$c['limit']||($body['query']??null)!==$c['query'])throw new \RuntimeException('RESPONSE_BINDING');
   foreach($body['results'] as $i=>$r){
    if(!is_array($r)||!is_string($r['title']??null)||mb_strlen($r['title'])>300||!is_string($r['url']??null))throw new \RuntimeException('RESULT_SHAPE');
    $fake=$c['mode']==='HTTP_FAKE';$url=$fake?app(MultiSourceResearchWorkspace::class)->canonical($r['url']):self::safeMetadataUrl($r['url']);$host=parse_url($url,PHP_URL_HOST);$date=$r['published_date']??null;$precision='UNKNOWN';
    if(is_string($date)&&preg_match('/\A\d{4}-\d{2}-\d{2}(?:T.*)?\z/D',$date)&&checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))){$date=substr($date,0,10);$precision='PROVIDER_ESTIMATED_PUBLISHED_OR_UPDATED_DAY';if($date<$c['date_window']['start']||$date>=$c['date_window']['end'])continue;}else continue;
    $snippet=$fake&&is_string($r['content']??null)?mb_substr(strip_tags($r['content']),0,1500):'Ringkasan tidak disimpan karena hak retensi snippet belum dikonfirmasi.';
    $p['results'][]=['key'=>hash('sha256',$url),'url'=>$url,'title'=>($fake?'[SYNTHETIC HTTP FIXTURE] ':'').strip_tags($r['title']),'snippet'=>$snippet,'snippet_kind'=>$fake?'SYNTHETIC_SEARCH_SNIPPET':'WITHHELD_PENDING_RIGHTS','origin'=>$host,'published_at'=>$date,'publication_precision'=>$precision,'retrieved_at'=>$p['retrieved_at'],'fixture_loaded_at'=>$p['fixture_loaded_at'],'rights_state'=>'UNASSESSED_METADATA_LINKS_ONLY_NO_SNIPPET_RETENTION','attribution'=>['provider'=>'Tavily web search','source_url'=>$url,'license_url'=>null,'terms_url'=>'https://www.tavily.com/terms','notice'=>'Tanggal adalah estimasi provider, bisa tanggal pembaruan. Hasil bukan bukti serangan atau fakta terverifikasi.'],'retrieval_allowed'=>false,'evidence_admission'=>'NONE'];
   }
   $p['coverage']='Sampel maksimal lima URL bertanggal terdeteksi; tanggal tidak dikenal/di luar jendela dikeluarkan. Tidak lengkap dan bukan kesimpulan citra negatif.';
  }catch(\Throwable){$p['state']='INVALID_RESPONSE';$p['results']=[];}
  return $p;
 }
 public static function safeMetadataUrl(string $url):string
 {
  if(strlen($url)>2048||preg_match('/[\x00-\x20\\\\]/',$url)||!filter_var($url,FILTER_VALIDATE_URL))throw new \RuntimeException('RESULT_URL_POLICY');$p=parse_url($url);$host=strtolower($p['host']??'');
  if(($p['scheme']??null)!=='https'||isset($p['port'])||isset($p['user'])||isset($p['pass'])||isset($p['fragment'])||filter_var($host,FILTER_VALIDATE_IP)||preg_match('/\\A[0-9.]+\\z/D',$host)||str_starts_with($host,'0x')||!str_contains($host,'.')||!preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z/D',$host)||preg_match('/\.(?:localhost|local|internal|test|invalid|example|arpa)\z/D',$host)||preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|7f|2e|2f|5c)/i',$url))throw new \RuntimeException('RESULT_URL_POLICY');
  $query=[];foreach(explode('&',$p['query']??'') as $part){if($part==='')continue;[$key,$v]=array_pad(explode('=',$part,2),2,'');if(str_starts_with(strtolower(rawurldecode($key)),'utm_'))continue;$query[]=$key.'='.$v;}sort($query);return 'https://'.$host.($p['path']??'/').($query?'?'.implode('&',$query):'');
 }
}
