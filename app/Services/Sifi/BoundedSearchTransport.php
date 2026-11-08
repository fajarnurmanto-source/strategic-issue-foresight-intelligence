<?php
namespace App\Services\Sifi;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use GuzzleHttp\Psr7\{StreamDecoratorTrait,Utils};
use Psr\Http\Message\StreamInterface;

/** Wire-compatible HTTP implementation. Live dispatch remains locked in this release. */
final class BoundedSearchTransport
{
 public function get(string $provider,array $context):array
 {
  $registry=app(SearchProviderRegistry::class);$p=$registry->get($provider);
  $binding=$context['binding']??[];
  foreach(['owner_id','object_id','session_id','object_revision','plan_sha256'] as $key)if(!isset($binding[$key]))throw new \RuntimeException('CONTEXT_REQUIRED');
  if(($context['query_sha256']??null)!==hash('sha256',$context['query']??''))throw new \RuntimeException('QUERY_BINDING');
  // No DNS, contact disclosure, socket, or native request can occur before this gate.
  if(($context['mode']??null)!=='HTTP_FAKE'||!app()->environment('testing')||config('sifi.real_search_fixture')!==true||!Http::preventingStrayRequests())throw new \RuntimeException('LIVE_SEARCH_LOCKED');
  $params=$registry->parameters($provider,$context['query'],(int)($context['limit']??5),$context);
  $sink=new class(Utils::streamFor(fopen('php://temp/maxmemory:32768','w+b'))) implements StreamInterface {
   use StreamDecoratorTrait; private StreamInterface $stream;
   public function write($string):int{if($this->stream->getSize()+strlen($string)>32768)throw new \RuntimeException('RESPONSE_BYTE_LIMIT');return $this->stream->write($string);}
  };
  $base=['provider'=>$provider,'query'=>$context['query'],'query_sha256'=>$context['query_sha256'],'binding_sha256'=>hash('sha256',json_encode($binding,JSON_THROW_ON_ERROR)),'endpoint'=>$p['endpoint'],'method'=>$p['method']??'GET','mode'=>'HTTP_FAKE','network_requests'=>0,'cost'=>0,'results'=>[],'retrieved_at'=>null,'fixture_loaded_at'=>now()->toIso8601String(),'response_sha256'=>null,'response_bytes'=>0,'retry_after_seconds'=>null];
  try{
   $request=Http::acceptJson()->withUserAgent('SIFI-Search-Fixture/1.0 (synthetic; no-network)')->connectTimeout(1)->timeout(3)->withOptions(['allow_redirects'=>false,'verify'=>true,'proxy'=>'','decode_content'=>false,'http_errors'=>false,'sink'=>$sink,'on_headers'=>static function($response){if((int)$response->getHeaderLine('Content-Length')>32768)throw new \RuntimeException('RESPONSE_BYTE_LIMIT');}])->setHandler(static fn()=>throw new \RuntimeException('NO_NATIVE_TRANSPORT_IN_FIXTURE'));if($provider==='TAVILY_KEYLESS')$request->withHeaders(['X-Tavily-Access-Mode'=>'keyless']);$response=($p['method']??'GET')==='POST'?$request->post($p['endpoint'],$params):$request->get($p['endpoint'],$params);
   $body=$response->body();$base['response_bytes']=strlen($body);if(strlen($body)>32768)return $base+['state'=>'BYTE_LIMIT','body'=>null];
   $base['response_sha256']=hash('sha256',$body);$status=$response->status();$retry=$response->header('Retry-After');$base['retry_after_seconds']=ctype_digit((string)$retry)?min(3600,(int)$retry):null;
   $state=match(true){$status===429=>'QUOTA',$status>=300&&$status<400=>'REDIRECT_BLOCKED',$status!==200=>'UNAVAILABLE',!str_starts_with(strtolower($response->header('Content-Type')??''),'application/json')=>'INVALID_RESPONSE',default=>'OK'};
   if($state!=='OK')return $base+['state'=>$state,'body'=>null];
   $json=json_decode($body,true,24,JSON_THROW_ON_ERROR);if(!is_array($json))throw new \RuntimeException('INVALID_RESPONSE');return $base+['state'=>'OK','body'=>$json];
  }catch(ConnectionException){return $base+['state'=>'TIMEOUT','body'=>null];}
  catch(\Throwable $e){return $base+['state'=>$e->getMessage()==='RESPONSE_BYTE_LIMIT'?'BYTE_LIMIT':'INVALID_RESPONSE','body'=>null];}
 }
}
