<?php
namespace App\Services\Sifi;

/** Parses supplied bytes only. Never resolves entities, follows links, or loads URLs. */
final class BoundedFeedParser
{
 public const ATOM='http://www.w3.org/2005/Atom';
 public const META='urn:sifi:synthetic-feed:1';
 /** @return array{version:string,format:string,publisher:string,items:list<array<string,mixed>>,next:?string,body_sha256:string} */
 public function parse(string $body,string $format):array
 {
  if(strlen($body)>32768||!mb_check_encoding($body,'UTF-8')||str_contains($body,"\0"))throw new \RuntimeException('FEED_BYTE_OR_ENCODING_LIMIT');
  if($format==='GDELT_JSON')return $this->news($body);
  if(!in_array($format,['RSS','ATOM'],true))throw new \RuntimeException('UNSUPPORTED_FORMAT');
  if(preg_match('/<!\s*(?:DOCTYPE|ENTITY)|<\?xml-stylesheet|<[^>]*\b(?:xml:base|xi:include)\s*=/i',$body)||preg_match('/encoding\s*=\s*[\'\"](?!UTF-8[\'\"])/i',$body))throw new \RuntimeException('XML_EXTERNAL_OR_ENCODING_REJECTED');
  $old=libxml_use_internal_errors(true);libxml_clear_errors();
  try{
   $reader=new \XMLReader();if(!$reader->XML($body,null,LIBXML_NONET|LIBXML_COMPACT))throw new \RuntimeException('MALFORMED_XML');$nodes=0;
   while($reader->read()){if(++$nodes>3000||$reader->depth>16||$reader->attributeCount>20||$reader->nodeType===\XMLReader::DOC_TYPE||($reader->namespaceURI==='http://www.w3.org/2001/XInclude'))throw new \RuntimeException('XML_RESOURCE_OR_INCLUDE_REJECTED');}
   $reader->close();if(libxml_get_errors())throw new \RuntimeException('MALFORMED_XML');
   $doc=new \DOMDocument();$doc->resolveExternals=false;$doc->substituteEntities=false;if(!$doc->loadXML($body,LIBXML_NONET|LIBXML_COMPACT)||libxml_get_errors())throw new \RuntimeException('MALFORMED_XML');
   $xp=new \DOMXPath($doc);$xp->registerNamespace('a',self::ATOM);$xp->registerNamespace('s',self::META);$xp->registerNamespace('dc','http://purl.org/dc/elements/1.1/');
   if($format==='RSS'&&($doc->documentElement?->tagName!=='rss'||$doc->documentElement->getAttribute('version')!=='2.0'))throw new \RuntimeException('RSS_VERSION_REQUIRED');
   if($format==='ATOM'&&($doc->documentElement?->localName!=='feed'||$doc->documentElement->namespaceURI!==self::ATOM))throw new \RuntimeException('ATOM_NAMESPACE_REQUIRED');
   $get=fn(string $q,?\DOMNode $n=null)=>trim((string)$xp->evaluate('string('.$q.')',$n));
   $feed=$format==='RSS'?'/rss/channel':'/a:feed';$publisher=$this->text($get($feed.($format==='RSS'?'/title':'/a:title')),200);$lang=$get($feed.($format==='RSS'?'/language':'/@xml:lang'));
   $items=$xp->query($feed.($format==='RSS'?'/item':'/a:entry'));if($items===false)throw new \RuntimeException('MALFORMED_XML');if($items->length>20)throw new \RuntimeException('FEED_ITEM_LIMIT');$out=[];
   foreach($items as $i=>$node){
    if(!$node instanceof \DOMElement)throw new \RuntimeException('FEED_ITEM_SCHEMA');
    $serialized=$doc->saveXML($node);if($serialized===false)throw new \RuntimeException('MALFORMED_XML');
    $rss=$format==='RSS';$title=$this->text($get($rss?'title':'a:title',$node),300);$description=$this->text($get($rss?'description':'a:summary',$node),1500);
    $url=$get($rss?'link':'a:link[not(@rel) or @rel="alternate"]/@href',$node);
    if($title===''||$url==='')throw new \RuntimeException('ITEM_TITLE_PERMALINK_REQUIRED');
    $origin=$get($rss?'source':'a:source/a:title',$node)?:$publisher;
    $published=$this->date($get($rss?'pubDate':'a:published',$node));$updated=$this->date($get($rss?'s:updated':'a:updated',$node));
    $out[]=['key'=>$get($rss?'guid':'a:id',$node)?:$url,'title'=>$title,'snippet'=>$description,'url'=>$url,'publisher'=>$publisher,'origin'=>$this->text($origin,200),'origin_url'=>$get($rss?'source/@url':'a:source/a:link/@href',$node)?:null,'declared_language'=>$get('@xml:lang',$node)?:($get('dc:language',$node)?:($lang?:'UNKNOWN')),'published_at'=>$published,'updated_at'=>$updated,'event_date'=>null,'region'=>$get('s:region',$node)?:'UNKNOWN','family'=>$get('s:family',$node)?:null,'raw_record_sha256'=>hash('sha256',$serialized),'pointer'=>($rss?'/rss/channel/item/':'/feed/entry/').$i,'observation_time'=>null];
   }
   $next=$get($feed.'/a:link[@rel="next"]/@href');
   return ['version'=>'sifi-feed-parser-1','format'=>$format,'publisher'=>$publisher,'items'=>$out,'next'=>$next?:null,'body_sha256'=>hash('sha256',$body)];
  }finally{libxml_clear_errors();libxml_use_internal_errors($old);}
 }
 private function text(string $v,int $max):string{if(mb_strlen($v)>$max)throw new \RuntimeException('ITEM_TEXT_LIMIT');return $v;}
 public function date(string $value):?string
 {
  if($value==='')return null;
  foreach(['!Y-m-d\TH:i:sP','!Y-m-d\TH:i:s\Z','!D, d M Y H:i:s O','!D, d M Y H:i:s T'] as $f){$d=\DateTimeImmutable::createFromFormat($f,$value,new \DateTimeZone('UTC'));$err=\DateTimeImmutable::getLastErrors();if($d&&(!$err||($err['warning_count']===0&&$err['error_count']===0)))return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');}return null;
 }
 /** @return array{version:string,format:string,publisher:string,items:list<array<string,mixed>>,next:null,body_sha256:string} */
 private function news(string $body):array
 {
  $v=json_decode($body,true,16,JSON_THROW_ON_ERROR);if(!is_array($v['articles']??null)||!array_is_list($v['articles'])||count($v['articles'])>20)throw new \RuntimeException('NEWS_SCHEMA_LIMIT');$out=[];
  $languages=['Indonesian'=>'id','English'=>'en','Spanish'=>'es','French'=>'fr','Japanese'=>'ja'];
  foreach($v['articles'] as $i=>$r){if(!is_array($r))throw new \RuntimeException('NEWS_ITEM_SCHEMA');foreach(['title','url','domain'] as $k)if(!is_string($r[$k]??null))throw new \RuntimeException('NEWS_ITEM_SCHEMA');$seen=$r['seendate']??'';$seenDate=null;if(is_string($seen)&&preg_match('/\A\d{8}T\d{6}Z\z/',$seen)){$d=\DateTimeImmutable::createFromFormat('!Ymd\THis\Z',$seen,new \DateTimeZone('UTC'));$errors=\DateTimeImmutable::getLastErrors();if($d&&(!$errors||!($errors['warning_count']+$errors['error_count'])))$seenDate=$d->format('Y-m-d\TH:i:s\Z');}
   $out[]=['key'=>$r['url'],'title'=>$this->text($r['title'],300),'snippet'=>'','url'=>$r['url'],'publisher'=>$this->text($r['domain'],200),'origin'=>$r['domain'],'origin_url'=>null,'declared_language'=>$languages[$r['language']??'']??'UNKNOWN','published_at'=>null,'updated_at'=>null,'event_date'=>null,'region'=>'UNKNOWN','family'=>null,'raw_record_sha256'=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR)),'pointer'=>'/articles/'.$i,'observation_time'=>$seenDate];
  }
  return ['version'=>'sifi-feed-parser-1','format'=>'GDELT_JSON','publisher'=>'Aggregator metadata — not original publisher','items'=>$out,'next'=>null,'body_sha256'=>hash('sha256',$body)];
 }
}
