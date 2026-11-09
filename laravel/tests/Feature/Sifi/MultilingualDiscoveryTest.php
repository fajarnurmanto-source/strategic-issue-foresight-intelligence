<?php
namespace Tests\Feature\Sifi;
use App\Models\{User,SifiMonitoringObject};
use App\Services\Sifi\{MultilingualQueryPlan,MultiSourceResearchWorkspace};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

final class MultilingualDiscoveryTest extends TestCase
{
 use RefreshDatabase;private User $owner;private SifiMonitoringObject $object;
 protected function setUp():void{parent::setUp();$this->withoutVite();Http::preventStrayRequests();$this->owner=User::factory()->create(['email_verified_at'=>now()]);$this->actingAs($this->owner);$this->object=$this->makeObject($this->owner);}
 private function makeObject(User $u,string $name='[SYNTHETIC] Kota Yogyakarta'):SifiMonitoringObject{return SifiMonitoringObject::create(['id'=>(string)Str::uuid(),'user_id'=>$u->id,'name'=>$name,'description'=>'SYNTHETIC, bukan peristiwa atau pemberitaan nyata.','category'=>'Wilayah','objective'=>'Penelitian layanan dan konteks publik, bukan kesimpulan faktual.','horizon'=>'Tahun2025','geography'=>'Kota Yogyakarta','aliases_json'=>['Jogjakarta','Yogyakarta City'],'keywords_json'=>[],'exclusions_json'=>[],'state'=>'CONFIGURED','revision'=>1]);}
 private function url(?SifiMonitoringObject $o=null):string{return '/sifi/monitoring-objects/'.($o??$this->object)->id.'/research-multi';}
 private function data(array $opts=[],array $extra=[]):array{return array_replace(['question'=>'Citra Negatif Apa Saja yang Menyerang Kota Yogyakarta sepanjang Tahun 2025','object_revision'=>1,'scenario'=>'multilingual','query_limit'=>2,'result_limit'=>10,'submission_key'=>(string)Str::uuid(),'multilingual_options'=>$opts+['language_mode'=>'AUTO','languages'=>['id','en','es','fr'],'result_language'=>'ALL','region'=>'EXACT_OBJECT','period'=>'ALL_CONTEXT','source_type'=>'ALL','provider_state'=>'normal']],$extra);}
 private function create(array $opts=[],array $extra=[]):object{$this->post($this->url(),$this->data($opts,$extra))->assertSessionHasNoErrors()->assertRedirect();return DB::table('sifi_research_sessions')->where('user_id',$this->owner->id)->orderByDesc('rowid')->first();}
 private function multi(object $row):array{return json_decode($row->results_json,true,40,JSON_THROW_ON_ERROR)['multi_source'];}
 public function test_auto_cross_language_queries_results_originals_and_provenance():void{
  $row=$this->create();$m=$this->multi($row);$e=$m['envelope'];$p=$e['multilingual_plan'];$this->assertSame(['id','en','es','fr'],$p['requested_languages']);$this->assertCount(6,$p['queries']);$this->assertCount(12,$e['calls']);$this->assertCount(12,$e['entries']);$this->assertSame('2025-01-01',$p['window']['start']);$this->assertSame('2026-01-01',$p['window']['end_exclusive']);$this->assertContains('es',$e['coverage_languages']);$this->assertContains('fr',$e['coverage_languages']);$this->assertSame(0,$e['usage']['network_requests']);
  foreach($e['entries'] as $item){$this->assertSame($item['title'],$item['original_title']);$this->assertSame($item['snippet'],$item['original_snippet']);$this->assertNull($item['derived_translation']);$this->assertNull($item['retrieved_at']);$this->assertStringContainsString('.sources.invalid',$item['canonical_url']);$this->assertFalse($item['retrieval_allowed']);$this->assertSame('NONE',$item['evidence_admission']);$this->assertGreaterThan(0,$item['language_detection']['confidence']);$this->assertNotEmpty($item['lineage'][0]['query_sha256']);}
  $this->assertNotEmpty(array_filter($e['comparisons'],fn($x)=>$x['translated_republication_candidate']));foreach($e['comparisons'] as $pair)$this->assertSame('INDEPENDENCE_NOT_VERIFIED',$pair['independence']);Http::assertNothingSent();
 }
 public function test_owner_declared_aliases_are_bounded_not_guessed_and_not_entire_region():void{
  $p=$this->multi($this->create())['envelope']['multilingual_plan'];$this->assertSame(['[SYNTHETIC] Kota Yogyakarta','Jogjakarta','Yogyakarta City'],array_column($p['entities'],'text'));$this->assertSame('OWNER_DECLARED_ALIAS_NOT_INDEPENDENTLY_VERIFIED',$p['entities'][1]['authority']);$this->assertStringNotContainsString('Daerah Istimewa',json_encode($p['queries']));$this->assertLessThanOrEqual(6,count($p['queries']));
  $e=$this->multi($this->create())['envelope'];$this->assertSame(['EXACT_OBJECT'],array_values(array_unique(array_column($e['entries'],'region'))));$this->assertGreaterThan(0,$e['filtered_counts']['region']);
 }
 public function test_published_event_later_and_unknown_periods_are_distinct():void{
  foreach(['PUBLISHED'=>'PUBLISHED_IN_WINDOW','LATER'=>'LATER_REPORTING_ABOUT_WINDOW','UNKNOWN'=>'PUBLICATION_UNKNOWN','EVENT'=>'LATER_REPORTING_ABOUT_WINDOW'] as $filter=>$expected){$e=$this->multi($this->create(['period'=>$filter]))['envelope'];$this->assertNotEmpty($e['entries']);$this->assertSame([$expected],array_values(array_unique(array_column($e['entries'],'time_classification'))));}
 }
 public function test_language_type_region_filters_and_no_result_are_honest():void{
  $e=$this->multi($this->create(['result_language'=>'es','source_type'=>'NEWS']))['envelope'];$this->assertSame(['es'],array_values(array_unique(array_column($e['entries'],'source_language'))));$this->assertSame(['NEWS'],array_values(array_unique(array_column($e['entries'],'source_type'))));
  $e=$this->multi($this->create(['region'=>'WIDER_REGION']))['envelope'];$this->assertSame(['WIDER_REGION'],array_values(array_unique(array_column($e['entries'],'region'))));
  $e=$this->multi($this->create(['source_type'=>'REGULATORY']))['envelope'];$this->assertSame([],$e['entries']);$this->assertSame('NO_RESULTS',$e['stopping_reason']);$this->assertStringContainsString('bukan internet',$e['coverage']);
 }
 public function test_japanese_manual_and_unknown_language_confidence():void{
  $e=$this->multi($this->create(['language_mode'=>'MANUAL','languages'=>['ja'],'result_language'=>'ja']))['envelope'];$this->assertSame(['ja'],$e['coverage_languages']);$this->assertStringContainsString('調査',$e['entries'][0]['original_title']);$this->assertCount(4,$e['calls']);
  $d=app(MultilingualQueryPlan::class)->detect('1234 ZXQ');$this->assertSame('UNKNOWN',$d['code']);$this->assertSame(0.0,$d['confidence']);
 }
 public function test_exclusions_apply_to_original_text_without_claiming_translation():void{
  $this->object->update(['exclusions_json'=>['sintético']]);$e=$this->multi($this->create())['envelope'];$this->assertNotContains('es',$e['coverage_languages']);$this->assertGreaterThan(0,$e['filtered_counts']['exclusion']);foreach($e['entries'] as $item)$this->assertStringNotContainsString('sintético',$item['original_snippet']);
 }
 public function test_partial_quota_timeout_and_zero_result_are_distinct():void{
  foreach(['partial','quota','timeout','no_result'] as $state){$e=$this->multi($this->create(['provider_state'=>$state]))['envelope'];$this->assertSame(0,$e['usage']['network_requests']);if($state==='partial'){$this->assertNotEmpty($e['entries']);$this->assertContains('UNAVAILABLE',array_column($e['calls'],'state'));}else$this->assertSame([],$e['entries']);if($state==='no_result')$this->assertSame('NO_RESULTS',$e['stopping_reason']);}
 }
 public function test_save_export_reopen_and_replay_do_not_create_another_session():void{
  $v=$this->data();$this->post($this->url(),$v)->assertSessionHasNoErrors();$row=DB::table('sifi_research_sessions')->first();$m=$this->multi($row);$this->post($this->url(),$v)->assertSessionHasNoErrors();$this->assertDatabaseCount('sifi_research_sessions',1);
  $ids=array_slice(array_column($m['envelope']['entries'],'id'),0,2);$this->post($this->url().'/'.$row->id.'/save',['revision'=>1,'object_revision'=>1,'envelope_sha256'=>$m['envelope_sha256'],'selected_ids'=>$ids,'analyst_note'=>'SYNTHETIC lintas bahasa, bukan fakta atau terjemahan AI.'])->assertSessionHasNoErrors();
  $this->get($this->url())->assertInertia(fn(Assert $p)=>$p->where('history.0.multi.state','SAVED')->where('history.0.multi.envelope.mode','SYNTHETIC_MULTILINGUAL_METADATA_ONLY'));$this->get($this->url().'/'.$row->id.'/references')->assertOk()->assertJsonPath('references.0.derived_translation',null)->assertJsonPath('query_plan.version','sifi-multilingual-plan-1')->assertJsonCount(12,'provider_calls');$this->assertDatabaseCount('sifi_research_retrievals',0);$this->assertDatabaseCount('sifi_evidence_drafts',0);Http::assertNothingSent();
 }
 public function test_foreign_owner_and_overposted_nested_url_rights_limits_are_rejected():void{
  $foreign=$this->makeObject(User::factory()->create());$this->post($this->url($foreign),$this->data())->assertForbidden();
  foreach([['endpoint'=>'https://127.0.0.1'],['rights'=>'APPROVED'],['request_limit'=>100],['languages'=>['zz']],['region'=>'CUSTOM'],['start_date'=>'2025-12-31','end_date'=>'2025-01-01']] as $opts)$this->post($this->url(),$this->data($opts))->assertSessionHasErrors();$this->assertDatabaseCount('sifi_research_sessions',0);Http::assertNothingSent();
 }
 public function test_paused_revision_expiry_and_foreign_selection_do_not_leak():void{
  $row=$this->create();$m=$this->multi($row);$this->post($this->url().'/'.$row->id.'/save',['revision'=>1,'object_revision'=>1,'envelope_sha256'=>$m['envelope_sha256'],'selected_ids'=>[str_repeat('f',64)],'analyst_note'=>''])->assertSessionHasErrors('research');$this->object->update(['state'=>'PAUSED']);$this->post($this->url(),$this->data())->assertSessionHasErrors('research');$this->object->update(['state'=>'CONFIGURED','revision'=>2]);$this->post($this->url(),$this->data())->assertSessionHasErrors('research');DB::table('sifi_research_sessions')->where('id',$row->id)->update(['expires_at'=>now()->subSecond()]);$this->get($this->url())->assertInertia(fn(Assert $p)=>$p->where('history.0.question',null)->where('history.0.multi',null));
 }
 public function test_any_object_uses_same_pipeline_and_unknown_topics_stay_partial():void{
  foreach(['[SYNTHETIC] NusaVolt brand','[SYNTHETIC] Electric vehicle industry'] as $name){$this->object=$this->makeObject($this->owner,$name);$e=$this->multi($this->create([],['question'=>'What public information is available about this object in 2024?']))['envelope'];$this->assertNotEmpty($e['entries']);$this->assertSame('2024-01-01',$e['multilingual_plan']['window']['start']);$this->assertStringContainsString($name,$e['entries'][0]['original_title']);$this->assertSame('NOT_PERFORMED',$e['multilingual_plan']['question_translation']);}
 }
 public function test_five_requested_languages_share_bounded_results_without_dropping_last_language():void{
  $e=$this->multi($this->create(['language_mode'=>'MANUAL','languages'=>['id','en','es','fr','ja']]))['envelope'];$this->assertCount(12,$e['entries']);$this->assertEqualsCanonicalizing(['id','en','es','fr','ja'],$e['coverage_languages']);$this->assertGreaterThan(0,$e['omitted_unique_count']);$this->assertGreaterThan(1,count(array_unique(array_map(fn($x)=>$x['origins'][0],array_slice($e['entries'],0,5)))));foreach($e['entries'] as $entry)$this->assertNotContains('synthetic',$entry['relevance']['context_hits']);
 }
 public function test_diagnostic_classification_never_retains_exception_messages_or_invents_timeout():void{
  $transport=new \App\Services\Sifi\OneShotSearchTransport();$secret='SYNTHETIC_SECRET_DO_NOT_LOG';$d=$transport->failureDiagnostics(new \Illuminate\Http\Client\ConnectionException($secret),'HTTP_DISPATCH',null);$this->assertSame('CONNECTION_FAILURE',$d['failure_category']);$this->assertFalse($d['raw_exception_message_retained']);$this->assertStringNotContainsString($secret,json_encode($d));$this->assertSame('PROVIDER_QUOTA',$transport->failureDiagnostics(new \RuntimeException($secret),'HTTP_DISPATCH',429)['failure_category']);$this->assertSame('UNKNOWN',$transport->failureDiagnostics(new \RuntimeException($secret),'RESPONSE_VALIDATION',null)['failure_category']);Http::assertNothingSent();
 }
 public function test_unrecognized_question_intent_is_not_replaced_by_object_objective():void{
  $e=$this->multi($this->create([],['question'=>'Bagaimana fenomena quasar berkaitan dengan Yogyakarta pada tahun 2025?']))['envelope'];$p=$e['multilingual_plan'];$this->assertSame([],$p['topic_concepts']);$this->assertContains('quasar',$p['untranslated_terms']);foreach($p['queries'] as $q)$this->assertStringContainsString('quasar',$q['query']);$this->assertSame('NOT_PERFORMED',$p['question_translation']);
 }
}
