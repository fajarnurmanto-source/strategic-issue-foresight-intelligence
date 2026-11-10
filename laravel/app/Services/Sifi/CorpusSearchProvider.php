<?php
namespace App\Services\Sifi;
use App\Contracts\SifiResearchProvider;

final class CorpusSearchProvider implements SifiResearchProvider
{
 public function __construct(private readonly string $id){}
 private function text(mixed $value,int $max):string
 {
  if(!is_string($value))throw new \RuntimeException('INVALID_TEXT');
  return mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags(html_entity_decode($value,ENT_QUOTES|ENT_HTML5,'UTF-8')))),0,$max);
 }
 public function search(array $context):array
 {
  return $this->normalize(app(($context['mode']??null)==='LIVE'?OneShotSearchTransport::class:BoundedSearchTransport::class)->get($this->id,$context),$context);
 }
 public function recover(array $context):array{return $this->normalize(app(OneShotSearchTransport::class)->recover($this->id,$context),$context);}
 private function normalize(array $packet,array $context):array
 {
  $body=$packet['body'];unset($packet['body']);
  if($packet['state']!=='OK')return $packet;
  try{
   $p=app(SearchProviderRegistry::class)->get($this->id);
   $items=match($this->id){'WIKIPEDIA_ID'=>$body['pages']??null,'GDELT_DOC'=>$body['articles']??null,default=>$body['message']['items']??null};
   if(!is_array($items)||!array_is_list($items)||count($items)>(int)$context['limit'])throw new \RuntimeException('RESULT_BOUND');
   foreach($items as $item){
    if(!is_array($item))throw new \RuntimeException('INVALID_RECORD');$published=null;$precision='UNKNOWN';$firstSeen=null;
    if($this->id==='WIKIPEDIA_ID'){
     $key=$this->text($item['key']??null,250);if($key===''||preg_match('/[\x00-\x20?#%\\\\]/u',$key))throw new \RuntimeException('INVALID_KEY');
     $url=$context['mode']==='HTTP_FAKE'?'https://wikipedia.sources.invalid/wiki/'.rawurlencode($key):'https://id.wikipedia.org/wiki/'.rawurlencode($key);
     $title=$this->text($item['title']??null,300);$snippet=$this->text($item['excerpt']??'',1500);$snippetKind='SEARCH_EXCERPT';$origin='WIKIPEDIA_ID';
    }elseif($this->id==='GDELT_DOC'){
     // GDELT provides indexed *article links*, not an article license or proof.
     if(!is_string($item['url']??null)||!is_string($item['title']??null))continue;
     try{$url=TavilySearchProvider::safeMetadataUrl($item['url']);}catch(\Throwable){continue;}
     $key=hash('sha256',$url);$title=$this->text($item['title'],300);
     if($title==='')continue;
     $snippet='';$snippetKind='WITHHELD_PENDING_RIGHTS';
     $origin=(string)parse_url($url,PHP_URL_HOST);
     $seen=$item['seendate']??null;
     if(is_string($seen)&&preg_match('/\A(\d{4})(\d{2})(\d{2})T\d{6}Z\z/D',$seen,$m)&&checkdate((int)$m[2],(int)$m[3],(int)$m[1])){
      $firstSeen=$m[1].'-'.$m[2].'-'.$m[3];$precision='UNKNOWN_PROVIDER_FIRST_SEEN_ONLY';
     }
    }else{
     // Deliberately neither request nor retain abstracts, full-text links or author contacts.
     $doi=$this->text($item['DOI']??null,200);if(!preg_match('~\A10\.\d{4,9}/[a-zA-Z0-9._;()/:+-]+\z~D',$doi))throw new \RuntimeException('INVALID_DOI');
     $key=strtolower($doi);$url=$context['mode']==='HTTP_FAKE'?'https://doi.sources.invalid/'.str_replace('%2F','/',rawurlencode($key)):'https://doi.org/'.str_replace('%2F','/',rawurlencode($key));
     $title=$this->text($item['title'][0]??null,300);$origin=$this->text($item['publisher']??'UNKNOWN_PUBLISHER',100);
     $snippet=$title.' · '.$origin.' · '.$this->text($item['type']??'UNKNOWN_TYPE',100);$snippetKind='BIBLIOGRAPHIC_LABEL_NO_ABSTRACT';
     $parts=$item['published']['date-parts'][0]??null;
     if(is_array($parts)&&array_is_list($parts)&&count($parts)>=1&&count($parts)<=3&&count(array_filter($parts,'is_int'))===count($parts)&&$parts[0]>=1000&&$parts[0]<=2200){
      $precision=match(count($parts)){1=>'YEAR',2=>'MONTH',3=>'DAY'};$valid=count($parts)===1||(count($parts)===2&&$parts[1]>=1&&$parts[1]<=12)||(count($parts)===3&&checkdate($parts[1],$parts[2],$parts[0]));
      if($valid)$published=implode('-',array_map(fn($n,$i)=>str_pad((string)$n,$i===0?4:2,'0',STR_PAD_LEFT),$parts,array_keys($parts)));else$precision='UNKNOWN';
     }
    }
    if($title==='')throw new \RuntimeException('EMPTY_TITLE');
    $packet['results'][]=['key'=>$key,'url'=>$url,'title'=>($context['mode']==='HTTP_FAKE'?'[SYNTHETIC HTTP FIXTURE] ':'').$title,'snippet'=>$snippet,'snippet_kind'=>$snippetKind,'origin'=>$origin,'published_at'=>$published,'first_seen_at'=>$firstSeen,'publication_precision'=>$precision,'retrieved_at'=>$packet['retrieved_at'],'fixture_loaded_at'=>$packet['fixture_loaded_at'],'rights_state'=>$p['rights'],'attribution'=>['provider'=>$p['corpus'],'source_url'=>$url,'license_url'=>$p['license'],'terms_url'=>$p['terms'],'notice'=>$this->id==='GDELT_DOC'?'GDELT DOC article-list link; seen date is NOT publication date. Article rights and source reliability unverified. No article body is retained.':($context['mode']==='HTTP_FAKE'?'Metadata simulasi; bukan sumber nyata atau izin pengambilan dokumen.':'Metadata hasil pencarian; atribusi sumber wajib dipertahankan. Pengambilan dokumen dan admission belum diizinkan.')],'retrieval_allowed'=>false,'evidence_admission'=>'NONE'];
   }
  }catch(\Throwable){$packet['state']='INVALID_RESPONSE';$packet['results']=[];}
  return $packet;
 }
}
