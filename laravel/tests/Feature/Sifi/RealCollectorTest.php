<?php
namespace Tests\Feature\Sifi;
use App\Models\{User,SifiMonitoringObject};
use App\Services\Sifi\{RealCollectorWorkspace,SearchProviderRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,File};
use Illuminate\Support\Str;
use Tests\TestCase;
final class RealCollectorTest extends TestCase
{
 use RefreshDatabase;private User $u;private SifiMonitoringObject $o;private string $tmp;
 protected function setUp():void{parent::setUp();$this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-04 12:00:00','UTC'));$this->withoutVite();$this->tmp=sys_get_temp_dir().'/sifi-collector-test-'.Str::uuid();mkdir($this->tmp,0700);$this->app->useStoragePath($this->tmp);config(['sifi.collector_fixture'=>true,'sifi.real_search_fixture'=>true]);Http::preventStrayRequests();$this->u=User::factory()->create(['id'=>1,'email_verified_at'=>now()]);$this->actingAs($this->u);$this->o=SifiMonitoringObject::create(['id'=>RealCollectorWorkspace::OBJECT,'user_id'=>1,'name'=>'[SYNTHETIC] Kota Yogyakarta','description'=>'Fixture configuration only','category'=>'Wilayah','objective'=>'Metadata research','horizon'=>'2025','geography'=>'Kota Yogyakarta','aliases_json'=>[],'keywords_json'=>[],'exclusions_json'=>[],'state'=>'CONFIGURED','revision'=>1]);}
 protected function tearDown():void{File::deleteDirectory($this->tmp);parent::tearDown();}
 private function url():string{return '/sifi/monitoring-objects/'.$this->o->id.'/research-multi';}
 private function data():array{return ['scenario'=>'crossref_live','question'=>RealCollectorWorkspace::QUESTION,'object_revision'=>1,'query_limit'=>1,'result_limit'=>5,'submission_key'=>(string)Str::uuid()];}
 public function test_historical_filter_uses_inclusive_last_day_without_changing_original_question():void{$r=app(SearchProviderRegistry::class);$p=$r->parameters('CROSSREF','Yogyakarta',5,['date_window'=>['start'=>'2025-01-01','end'=>'2026-01-01']]);$this->assertSame('from-pub-date:2025-01-01,until-pub-date:2025-12-31',$p['filter']);$this->assertSame('DOI,title,publisher,published,URL,type',$p['select']);$this->assertArrayNotHasKey('filter',$r->parameters('CROSSREF','Yogyakarta',5));$this->expectException(\RuntimeException::class);$r->parameters('CROSSREF','Yogyakarta',5,['date_window'=>['start'=>'2025-02-31','end'=>'2026-01-01']]);}
 public function test_bounded_collector_reuses_history_save_and_does_not_admit_claims():void{
  $v=$this->data();$this->post($this->url(),$v)->assertSessionHasNoErrors()->assertRedirect();$s=DB::table('sifi_research_sessions')->first();$m=json_decode($s->results_json,true)['multi_source'];$e=$m['envelope'];$this->assertSame(RealCollectorWorkspace::QUESTION,$s->question);$this->assertSame(0,$e['usage']['network_requests']);$this->assertSame('SYNTHETIC_HTTP_FAKE_API_ADAPTER',$e['mode']);$this->assertCount(1,$e['entries']);$this->assertStringContainsString('.invalid',$e['entries'][0]['url']);$this->assertNull($e['entries'][0]['event_date']);$this->assertSame('NONE',$e['entries'][0]['evidence_admission']);
  $this->post($this->url(),$v)->assertSessionHasNoErrors();$this->assertDatabaseCount('sifi_research_sessions',1);$this->post($this->url().'/'.$s->id.'/save',['revision'=>1,'object_revision'=>1,'envelope_sha256'=>$m['envelope_sha256'],'selected_ids'=>[$e['entries'][0]['id']],'analyst_note'=>'SYNTHETIC metadata review'])->assertSessionHasNoErrors();$this->get($this->url().'/'.$s->id.'/references')->assertOk()->assertJsonPath('collector.request_query','Yogyakarta');$this->assertDatabaseCount('sifi_claim_drafts',0);$this->assertDatabaseCount('sifi_evidence_drafts',0);
 }
 public function test_collector_owner_object_pause_revision_and_question_constraints():void{
  $v=$this->data();$v['question']='Different research question is not authorized';$this->post($this->url(),$v)->assertSessionHasErrors('research');$v=$this->data();$v['endpoint']='https://127.0.0.1';$this->post($this->url(),$v)->assertSessionHasErrors('research');$this->o->update(['revision'=>2]);$this->post($this->url(),$this->data())->assertSessionHasErrors('research');$this->o->update(['revision'=>1,'state'=>'PAUSED']);$this->post($this->url(),$this->data())->assertSessionHasErrors();$this->actingAs(User::factory()->create(['email_verified_at'=>now()]));$this->post($this->url(),$this->data())->assertForbidden();$this->assertDatabaseCount('sifi_research_sessions',0);Http::assertNothingSent();
 }
 public function test_rate_budget_and_review_expiry_fail_before_request():void{
  $this->post($this->url(),$this->data())->assertSessionHasNoErrors();$this->post($this->url(),$this->data())->assertSessionHasErrors('research');$this->assertDatabaseCount('sifi_research_sessions',1);$this->travelTo(now()->setDate(2026,11,6));$this->post($this->url(),$this->data())->assertSessionHasErrors();$this->travelBack();
 }
 public function test_receipt_projection_deduplicates_doi_and_preserves_changed_metadata():void{
  $this->post($this->url(),$this->data())->assertSessionHasNoErrors();$s=DB::table('sifi_research_sessions')->first();$r=json_decode($s->results_json,true);$e=$r['multi_source']['envelope'];$item=array_intersect_key($e['entries'][0],array_flip(['key','url','title','snippet','origin','published_at','publication_precision','retrieved_at','fixture_loaded_at','rights_state','attribution','retrieval_allowed','evidence_admission']));
  $plan=json_decode($s->plan_json,true);$context=['mode'=>'HTTP_FAKE','query'=>'Yogyakarta','query_sha256'=>hash('sha256','Yogyakarta'),'binding'=>['owner_id'=>1,'object_id'=>$this->o->id],'date_window'=>['start'=>'2025-01-01','end'=>'2026-01-01']];
  $packet=['provider'=>'CROSSREF','query_sha256'=>$context['query_sha256'],'binding_sha256'=>hash('sha256',json_encode($context['binding'],JSON_THROW_ON_ERROR)),'state'=>'OK','network_requests'=>0,'response_bytes'=>120,'response_sha256'=>str_repeat('a',64),'retrieved_at'=>now()->toIso8601String(),'results'=>[$item,$item]];
  $out=app(RealCollectorWorkspace::class)->envelope($this->u,$this->o,$plan,$packet,$context);$this->assertCount(1,$out['entries']);$this->assertSame(1,$out['collapsed_hits']);$this->assertSame('UNCHANGED_METADATA',$out['entries'][0]['revision_state']);$this->assertCount(2,$out['entries'][0]['lineage']);
  $packet['results']=[$item];$packet['results'][0]['title'].=' corrected';$out=app(RealCollectorWorkspace::class)->envelope($this->u,$this->o,$plan,$packet,$context);$this->assertSame('CHANGED_METADATA',$out['entries'][0]['revision_state']);$this->assertSame($e['entries'][0]['id'],$out['entries'][0]['id']);
  $context['binding']['owner_id']=2;$this->expectException(\RuntimeException::class);app(RealCollectorWorkspace::class)->envelope($this->u,$this->o,$plan,$packet,$context);
 }
 public function test_workbench_projection_shows_only_owned_live_history_and_explicit_uncertainty():void{
  $this->post($this->url(),$this->data())->assertSessionHasNoErrors();$s=DB::table('sifi_research_sessions')->first();$r=json_decode($s->results_json,true);$workspace=app(\App\Services\Sifi\MultiSourceResearchWorkspace::class);$projection=app(\App\Services\Sifi\CollectorObservations::class);$this->assertSame([],$projection->forObject($this->u,$this->o));
  // Synthetic unit-test data only: exercise the live-history branch without network.
  $r['multi_source']['envelope']['mode']='APPROVED_LIVE_CORPUS_METADATA';$r['multi_source']['envelope_sha256']=$workspace->digest($r['multi_source']['envelope']);DB::table('sifi_research_sessions')->where('id',$s->id)->update(['results_json'=>json_encode($r)]);
  $items=$projection->forObject($this->u,$this->o);$this->assertCount(1,$items);$this->assertNull($items[0]['entries'][0]['event_date']);$this->assertStringContainsString('.invalid',$items[0]['entries'][0]['url']);
  $this->get('/sifi/workbench?object='.$this->o->id)->assertOk()->assertInertia(fn($page)=>$page->has('collectorObservations',1));$this->actingAs(User::factory()->create(['email_verified_at'=>now()]));$this->get('/sifi/workbench?object='.$this->o->id)->assertForbidden();$this->assertSame(0,$r['multi_source']['envelope']['usage']['network_requests']);
 }
}
