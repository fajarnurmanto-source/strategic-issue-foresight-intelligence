<?php
namespace Tests\Feature\Sifi;
use App\Services\Sifi\{TavilySearchProvider,SearchWireFixture,SearchProviderRegistry,OneShotSearchTransport};
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TavilySearchTest extends TestCase
{
 protected function setUp():void{parent::setUp();config(['sifi.real_search_fixture'=>true]);Http::preventStrayRequests();}
 private function context():array{return ['mode'=>'HTTP_FAKE','query'=>'SYNTHETIC Kota Yogyakarta pemberitaan kritik 2025','query_sha256'=>hash('sha256','SYNTHETIC Kota Yogyakarta pemberitaan kritik 2025'),'limit'=>5,'date_window'=>['start'=>'2025-01-01','end'=>'2026-01-01'],'binding'=>['owner_id'=>999999,'object_id'=>'SYNTHETIC-OBJECT','session_id'=>'SYNTHETIC-SESSION','object_revision'=>1,'plan_sha256'=>str_repeat('b',64)]];}
 public function test_keyless_post_has_exact_year_window_without_ai_answer_raw_content_or_credentials():void{
  SearchWireFixture::install('api_web_2025');$p=(new TavilySearchProvider())->search($this->context());$this->assertSame('OK',$p['state']);$this->assertCount(2,$p['results']);Http::assertSentCount(1);
  Http::assertSent(fn($r)=>$r->method()==='POST'&&$r->url()==='https://api.tavily.com/search'&&$r->header('X-Tavily-Access-Mode')===['keyless']&&!$r->hasHeader('Authorization')&&$r['start_date']==='2025-01-01'&&$r['end_date']==='2026-01-01'&&$r['include_answer']===false&&$r['include_raw_content']===false&&$r['auto_parameters']===false&&$r['search_depth']==='basic');
  $this->assertSame('PROVIDER_ESTIMATED_PUBLISHED_OR_UPDATED_DAY',$p['results'][0]['publication_precision']);$this->assertSame(0,$p['network_requests']);$this->assertFalse($p['results'][0]['retrieval_allowed']);$this->assertSame('NONE',$p['results'][0]['evidence_admission']);
 }
 public function test_missing_or_bad_date_window_is_refused_before_http():void{
  foreach([[],['start'=>'2025-02-31','end'=>'2026-01-01'],['start'=>'2026-01-01','end'=>'2025-01-01']] as $window){$c=$this->context();$c['date_window']=$window;try{app(SearchProviderRegistry::class)->parameters('TAVILY_KEYLESS',$c['query'],5,$c);$this->fail('Invalid window accepted');}catch(\RuntimeException){$this->assertTrue(true);}}Http::assertNothingSent();
 }
 public function test_unknown_and_outside_year_dates_are_not_invented_into_2025():void{
  $c=$this->context();Http::fake(['api.tavily.com/search'=>Http::response(['query'=>$c['query'],'results'=>[['title'=>'SYNTHETIC unknown date','url'=>'https://news.sources.invalid/unknown','content'=>'SYNTHETIC','published_date'=>null],['title'=>'SYNTHETIC outside','url'=>'https://news.sources.invalid/outside','content'=>'SYNTHETIC','published_date'=>'2026-01-01']]],200,['Content-Type'=>'application/json'])]);$p=(new TavilySearchProvider())->search($c);$this->assertSame('OK',$p['state']);$this->assertSame([],$p['results']);
 }
 public function test_wrong_echoed_query_and_unsafe_result_do_not_become_citations():void{
  $c=$this->context();Http::fake(['api.tavily.com/search'=>Http::response(['query'=>'wrong','results'=>[['title'=>'SYNTHETIC','url'=>'https://127.0.0.1/private','published_date'=>'2025-06-12']]],200,['Content-Type'=>'application/json'])]);$p=(new TavilySearchProvider())->search($c);$this->assertSame('INVALID_RESPONSE',$p['state']);$this->assertSame([],$p['results']);
 }
 public function test_public_metadata_urls_are_never_fetched_and_private_urls_are_rejected():void{
  $this->assertSame('https://example.com/news?a=2',TavilySearchProvider::safeMetadataUrl('https://example.com/news?utm_source=x&a=2'));
  foreach(['http://example.com/news','https://127.0.0.1/x','https://127.1/x','https://0x7f.0.0.1/x','https://[::1]/','https://localhost/x','https://x.internal/x','https://user:pass@example.com/x','https://example.com:443/x','https://example.com/x#secret','https://example.com/%2e%2e','https://x.invalid/x'] as $url){try{TavilySearchProvider::safeMetadataUrl($url);$this->fail('Unsafe URL accepted');}catch(\RuntimeException){$this->assertTrue(true);}}Http::assertNothingSent();
 }
 public function test_native_normalization_withholds_unreviewed_snippets_and_preserves_date_uncertainty():void{
  $c=$this->context();$c['mode']='LIVE';$wire=\Mockery::mock(OneShotSearchTransport::class);$wire->shouldReceive('get')->once()->with('TAVILY_KEYLESS',$c)->andReturn(['state'=>'OK','body'=>['query'=>$c['query'],'results'=>[['title'=>'SYNTHETIC native-mock contract only','url'=>'https://example.com/synthetic','content'=>'DO_NOT_PERSIST','published_date'=>'2025-06-12']]],'results'=>[],'retrieved_at'=>'2026-10-29T00:00:00Z','fixture_loaded_at'=>null]);app()->instance(OneShotSearchTransport::class,$wire);$p=(new TavilySearchProvider())->search($c);$this->assertCount(1,$p['results']);$this->assertSame('WITHHELD_PENDING_RIGHTS',$p['results'][0]['snippet_kind']);$this->assertStringNotContainsString('DO_NOT_PERSIST',json_encode($p));$this->assertSame('PROVIDER_ESTIMATED_PUBLISHED_OR_UPDATED_DAY',$p['results'][0]['publication_precision']);Http::assertNothingSent();
 }
}
