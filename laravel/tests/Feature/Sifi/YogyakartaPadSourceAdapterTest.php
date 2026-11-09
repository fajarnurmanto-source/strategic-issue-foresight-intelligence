<?php

namespace Tests\Feature\Sifi;

use App\Models\{User,SifiSourceDraft,SifiIssueCandidate};
use App\Services\Sifi\{YogyakartaPadSourceAdapter,SourceObservationPipeline,ExecutiveBriefSource};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Route};
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class YogyakartaPadSourceAdapterTest extends TestCase
{
    use RefreshDatabase;
    private User $owner;
    private YogyakartaPadSourceAdapter $adapter;
    protected function setUp():void
    {
        parent::setUp();$this->withoutVite();Http::preventStrayRequests();
        $this->owner=User::factory()->create(['email_verified_at'=>now()]);$this->adapter=app(YogyakartaPadSourceAdapter::class);
    }
    private function register(?User $user=null):SifiSourceDraft
    {
        $user??=$this->owner;
        $this->actingAs($user)->post('/sifi/source-catalog/sources',['name'=>'Open Data PAD synthetic connector','source_type'=>'OPEN_DATA',
            'homepage_url'=>YogyakartaPadSourceAdapter::DOCUMENTATION,'source_geography'=>'Kota Yogyakarta','notes'=>'Rights unconfirmed; synthetic import only; collector OFF'])->assertRedirect();
        return SifiSourceDraft::where('user_id',$user->id)->latest('created_at')->firstOrFail();
    }
    private function fields(SifiSourceDraft $source,string $scenario='pad_valid'):array
    {
        $saved=session('ae08_pad_preview');$json=is_array($saved)?($saved['payload']??null):null;
        $preview=$this->adapter->preview($scenario,$scenario==='pad_import'?$json:null);
        return ['scenario'=>$scenario,'source_draft_id'=>$source->id,'source_revision'=>$source->revision,
            'input_digest'=>$preview['packet_hash'],
            'analyst_note'=>'AUTOMATED SYNTHETIC FIXTURE ACCEPTANCE. Perlu data primer yang sah; bukan hasil telaah manusia.','confirm_synthetic'=>true];
    }
    private function create(SifiSourceDraft $source,string $scenario='pad_valid'):SifiIssueCandidate
    {
        $response=$this->actingAs($this->owner)->post('/sifi/source-catalog/pipeline-draft',$this->fields($source,$scenario));
        $response->assertRedirect();$id=basename(dirname(parse_url($response->headers->get('Location'),PHP_URL_PATH)));
        $c=SifiIssueCandidate::findOrFail($id);
        $response->assertRedirect('/sifi/issue-candidates/'.$c->id.'/executive-brief');return $c;
    }
    private function import(array $packet):void
    {
        $this->actingAs($this->owner)->post('/sifi/source-catalog/connector-preview',['import_json'=>json_encode($packet,JSON_THROW_ON_ERROR)])
            ->assertRedirect('/sifi/source-catalog?scenario=pad_import');
    }
    public function test_fixed_source_schema_timestamps_attribution_and_pagination_are_honest():void
    {
        $p=$this->adapter->preview('pad_valid');$this->assertSame('READY_FOR_ANALYST_DRAFT',$p['status']);
        $this->assertCount(2,$p['observations']);$this->assertSame(2,$p['pages_processed']);
        $this->assertSame('UNKNOWN',$p['source']['freshness']);$this->assertNull($p['source']['source_updated_at']);
        $this->assertSame('OFFICIAL_SCHEMA_UNCONFIRMED',$p['source']['schema_status']);
        $this->assertSame('COMMERCIAL_REUSE_UNCONFIRMED',$p['source']['rights_state']);
        foreach($p['observations'] as $o){
            $this->assertNull($o['issued_at']);$this->assertNull($o['valid_at']);$this->assertSame('Tahun 2025',$o['period_label']);
            $this->assertTrue($o['provenance']['synthetic']);$this->assertFalse($o['provenance']['independence_verified']);
            $this->assertSame(YogyakartaPadSourceAdapter::ENDPOINT,$o['provenance']['endpoint']);
            $this->assertSame($p['packet_hash'],$o['provenance']['packet_hash']);
            $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/',$o['record_key']);
        }
        Http::assertNothingSent();
    }
    public function test_fetch_time_does_not_change_identity_or_content_hash():void
    {
        $packet=$this->adapter->template();$a=$this->adapter->normalize($packet,1700000000);$b=$this->adapter->normalize($packet,1800000000);
        $this->assertSame(array_column($a['observations'],'id'),array_column($b['observations'],'id'));
        $this->assertSame(array_column($a['observations'],'content_hash'),array_column($b['observations'],'content_hash'));
        $this->assertSame($a['packet_hash'],$b['packet_hash']);$this->assertNotSame($a['source']['fetched_at'],$b['source']['fetched_at']);
    }
    public function test_duplicate_across_pages_and_updated_record_keep_exact_provenance():void
    {
        $d=$this->adapter->preview('pad_duplicate');$this->assertCount(2,$d['observations']);$this->assertCount(1,$d['duplicates']);
        $this->assertSame('/pages/1/records/1',$d['duplicates'][0]['pointer']);
        $p=$this->adapter->preview('pad_updated');$this->assertCount(3,$p['observations']);$this->assertCount(1,$p['changes']);
        $this->assertSame($p['observations'][0]['record_key'],$p['observations'][2]['record_key']);
        $this->assertNotSame($p['observations'][0]['id'],$p['observations'][2]['id']);
        $this->assertSame('SOURCE_RECORD_REVISION_NOT_REAL_WORLD_CHANGE',$p['changes'][0]['reason']);
    }
    public function test_same_version_different_content_is_conflict_without_truth_promotion():void
    {
        $p=$this->adapter->template();$p['pages'][1]['records'][]=array_replace($p['pages'][0]['records'][0],['amount_rupiah'=>240000000]);
        $r=$this->adapter->normalize($p);$this->assertCount(1,$r['conflicts']);$this->assertCount(0,$r['changes']);
        $this->assertFalse($r['claim_verified']);$this->assertSame('NOT_EVALUATED',$r['evidence_admission']);
    }
    public function test_stale_and_future_source_timestamps_fail_closed_unknown_never_means_fresh():void
    {
        $this->assertSame('STALE_SOURCE',$this->adapter->preview('pad_stale')['reason']);
        $p=$this->adapter->template();$p['source_updated_at']='2099-01-01T00:00:00Z';
        $this->assertSame('FUTURE_SOURCE_TIMESTAMP',$this->adapter->normalize($p)['reason']);
        $p['source_updated_at']=now()->utc()->subMinutes(10)->format('Y-m-d\TH:i:s\Z');
        $r=$this->adapter->normalize($p);$this->assertSame('WITHIN_SYNTHETIC_TEST_BUDGET',$r['source']['freshness']);
        $this->assertStringContainsString('NOT_OFFICIAL_SCHEDULE',$r['source']['freshness_policy']);
    }
    public function test_all_negative_scenarios_leave_no_partial_records():void
    {
        foreach(['pad_stale','pad_http_error','pad_rate_limited','pad_timeout','pad_missing','pad_denied','pad_malformed','pad_schema','pad_pagination'] as $s){
            $p=$this->adapter->preview($s);$this->assertSame('BLOCKED',$p['status']);$this->assertSame([],$p['observations']);
        }
        Http::assertNothingSent();
    }
    public function test_unauthorized_source_endpoint_marker_or_schema_is_not_self_attestable():void
    {
        foreach(['source_id'=>'SRC-BPS','endpoint'=>'http://169.254.169.254/latest','synthetic'=>false] as $key=>$value){
            $p=$this->adapter->template();$p[$key]=$value;$this->assertSame('USE_NOT_PERMITTED',$this->adapter->normalize($p)['reason']);
        }
        $p=$this->adapter->template();$p['rights_state']='CLEARED';$this->assertSame('SCHEMA_DRIFT',$this->adapter->normalize($p)['reason']);
        Http::assertNothingSent();
    }
    public function test_missing_fields_schema_additions_malformed_values_and_size_are_rejected():void
    {
        $p=$this->adapter->template();unset($p['attribution']);$this->assertSame('SOURCE_METADATA_INCOMPLETE',$this->adapter->normalize($p)['reason']);
        foreach(['tax_code'=>'REAL','tax_name'=>'Actual data','amount_rupiah'=>-1,'record_version'=>0,'updated_at'=>'2026-02-30T00:00:00Z','fiscal_year'=>'2025'] as $k=>$v){
            $p=$this->adapter->template();$p['pages'][0]['records'][0][$k]=$v;$this->assertSame('MALFORMED_RECORD',$this->adapter->normalize($p)['reason']);
        }
        $p=$this->adapter->template();$p['pages'][1]['records'][0]['secret']='should never persist';$this->assertSame([],$this->adapter->normalize($p)['observations']);
        $this->assertSame('PAYLOAD_TOO_LARGE',$this->adapter->importJson(str_repeat('x',65537))['reason']);
        foreach(['{}','[]','{"schema":'] as $json)$this->assertSame('BLOCKED',$this->adapter->importJson($json)['status']);
    }
    public function test_pagination_is_bounded_complete_sequential_and_does_not_follow_urls():void
    {
        foreach(['pad_pagination'] as $s)$this->assertSame('INCOMPLETE_PAGINATION',$this->adapter->preview($s)['reason']);
        $p=$this->adapter->template();$p['pages'][0]['next']='https://evil.example';$this->assertSame('INCOMPLETE_PAGINATION',$this->adapter->normalize($p)['reason']);
        $p=$this->adapter->template();$p['pages'][1]['page']=3;$this->assertSame('INCOMPLETE_PAGINATION',$this->adapter->normalize($p)['reason']);
        Http::assertNothingSent();
    }
    public function test_live_entry_point_is_always_blocked_even_if_http_would_succeed():void
    {
        Http::fake(['*'=>Http::response($this->adapter->template())]);
        $this->assertSame('LIVE_ACQUISITION_NOT_AUTHORIZED',$this->adapter->fetchLive(2025)['reason']);Http::assertNothingSent();
    }
    public function test_prepared_transport_obeys_fixed_endpoint_no_retry_or_schema_guessing():void
    {
        $transport=new PadTransportHarness;
        foreach([500=>'HTTP_500',429=>'RATE_LIMITED',302=>'HTTP_302',200=>'OFFICIAL_SCHEMA_UNCONFIRMED'] as $status=>$reason){
            Http::swap(new \Illuminate\Http\Client\Factory);Http::preventStrayRequests();
            Http::fake(['*'=>Http::response('{}',$status,['Retry-After'=>'120'])]);
            $r=$transport->inspect(2025);$this->assertSame($reason,$r['reason']);$this->assertFalse($r['automatic_retry']);
            if($status===429)$this->assertSame(120,$r['retry_after_seconds']);
            Http::assertSentCount(1);Http::assertSent(fn($request)=>$request->url()===YogyakartaPadSourceAdapter::ENDPOINT.'?tahun=2025');
        }
    }
    public function test_prepared_transport_timeout_oversize_and_invalid_period_fail_safely():void
    {
        Http::fake(['*'=>Http::failedConnection()]);$this->assertSame('SOURCE_TIMEOUT',(new PadTransportHarness)->inspect(2025)['reason']);
        Http::swap(new \Illuminate\Http\Client\Factory);Http::preventStrayRequests();
        Http::fake(['*'=>Http::response(str_repeat('x',65537))]);$this->assertSame('PAYLOAD_TOO_LARGE',(new PadTransportHarness)->inspect(2025)['reason']);
        Http::swap(new \Illuminate\Http\Client\Factory);Http::preventStrayRequests();Http::fake();$this->assertSame('INVALID_PERIOD',(new PadTransportHarness)->inspect(0)['reason']);Http::assertNothingSent();
    }
    public function test_registration_native_source_rights_and_collector_remain_unchanged():void
    {
        $s=$this->register();$this->assertSame('UNASSESSED',$s->rights_state);$this->assertSame('OFF',$s->collector_state);
        $this->create($s);$this->assertSame('UNASSESSED',$s->fresh()->rights_state);$this->assertDatabaseCount('sifi_catalog_audit',1);Http::assertNothingSent();
    }
    public function test_guest_unverified_and_missing_registration_cannot_import_drafts():void
    {
        $this->get('/sifi/source-catalog?scenario=pad_valid')->assertRedirect('/login');
        $this->post('/sifi/source-catalog/connector-preview',['import_json'=>'{}'])->assertRedirect('/login');
        $u=User::factory()->create(['email_verified_at'=>null]);$this->actingAs($u)->post('/sifi/source-catalog/connector-preview',['import_json'=>'{}'])->assertRedirect('/email/verify');
        $this->actingAs($this->owner)->post('/sifi/source-catalog/pipeline-draft',['scenario'=>'pad_valid','analyst_note'=>'Synthetic automated testing','confirm_synthetic'=>true,'input_digest'=>$this->adapter->preview('pad_valid')['packet_hash']])->assertSessionHasErrors('source_draft_id');
        $this->assertDatabaseCount('sifi_issue_candidates',0);
    }
    public function test_foreign_registration_stale_revision_and_endpoint_change_are_rejected():void
    {
        $other=User::factory()->create(['email_verified_at'=>now()]);$foreign=$this->register($other);
        $this->actingAs($this->owner)->post('/sifi/source-catalog/pipeline-draft',$this->fields($foreign))->assertForbidden();
        $s=$this->register();$this->post('/sifi/source-catalog/pipeline-draft',array_replace($this->fields($s),['source_revision'=>999]))->assertSessionHasErrors('source_revision');
        $s->update(['homepage_url'=>'https://example.org']);$this->post('/sifi/source-catalog/pipeline-draft',$this->fields($s))->assertSessionHasErrors('source_draft_id');
        $this->assertDatabaseCount('sifi_issue_candidates',0);
    }
    public function test_preview_uses_session_only_and_never_creates_domain_records():void
    {
        $this->import($this->adapter->template());$this->assertDatabaseCount('sifi_issue_candidates',0);$this->assertDatabaseCount('sifi_source_drafts',0);
        $this->get('/sifi/source-catalog?scenario=pad_import')->assertOk()->assertInertia(fn(AssertableInertia $p)=>$p->where('pipeline.connector','yk_pad')->where('pipeline.source.freshness','UNKNOWN')->has('pipeline.observations',2)->etc());
        Http::assertNothingSent();
    }
    public function test_preview_payload_is_not_reused_across_owners_even_in_same_testing_session():void
    {
        $p=$this->adapter->template();$p['pages'][0]['records'][0]['amount_rupiah']=987654321;$this->import($p);
        $other=User::factory()->create(['email_verified_at'=>now()]);$this->actingAs($other)->get('/sifi/source-catalog?scenario=pad_import')->assertOk()
            ->assertInertia(fn(AssertableInertia $page)=>$page->where('pipeline.observations.0.amount_rupiah',200000000)->etc());
    }
    public function test_custom_import_and_history_are_owner_scoped_and_idempotent_without_overwriting_edits():void
    {
        $s=$this->register();$p=$this->adapter->template();$p['pages'][0]['records'][0]['amount_rupiah']=250000000;$this->import($p);
        $c=$this->create($s,'pad_import');$c->update(['exposure_notes'=>'Owner edit must survive','revision'=>2]);$this->create($s,'pad_import');
        $this->assertDatabaseCount('sifi_issue_candidates',1);$this->assertDatabaseCount('sifi_evidence_drafts',2);
        $this->assertSame('Owner edit must survive',$c->fresh()->exposure_notes);
        $pipeline=app(SourceObservationPipeline::class);$h=$pipeline->preview('pad_import',json_encode($p),$this->owner->id)['history'];$this->assertSame(2,$h['already_seen']);
        $other=User::factory()->create(['email_verified_at'=>now()]);$h=$pipeline->preview('pad_import',json_encode($p),$other->id)['history'];$this->assertSame(2,$h['new']);$this->assertSame(0,$h['already_seen']);
    }
    public function test_updated_batch_retains_old_candidate_and_records_ordered_history():void
    {
        $s=$this->register();$old=$this->create($s);$pipeline=app(SourceObservationPipeline::class);
        $h=$pipeline->preview('pad_updated',null,$this->owner->id)['history'];$this->assertSame(1,$h['updated']);$this->assertSame(2,$h['already_seen']);
        $new=$this->create($s,'pad_updated');$this->assertNotSame($old->id,$new->id);$this->assertDatabaseCount('sifi_issue_candidates',2);
        $this->assertSame(1,$old->fresh()->revision);$this->assertDatabaseCount('sifi_evidence_drafts',5);
        $packet=$this->adapter->template();$packet['pages'][0]['records'][0]['amount_rupiah']=190000000;$this->import($packet);
        $this->post('/sifi/source-catalog/pipeline-draft',$this->fields($s,'pad_import'))->assertSessionHasErrors('scenario');
        $this->assertDatabaseCount('sifi_issue_candidates',2);
    }
    public function test_old_weather_pipeline_stays_usable_and_existing_brief_contract_unchanged():void
    {
        $c=app(SourceObservationPipeline::class)->createDraft($this->owner,'valid','Automated weather regression');
        $this->assertStringContainsString('AE-07',$c->title);$lineage=app(SourceObservationPipeline::class)->lineage($c);
        $this->assertSame('AE07_LOCAL_SYNTHETIC_NOT_AQ01_CUSTODY',$lineage['reference_namespace']);$this->assertArrayNotHasKey('connector',$lineage);
    }
    public function test_end_to_end_existing_composer_export_has_pad_trace_without_weather_misattribution():void
    {
        $s=$this->register();$c=$this->create($s,'pad_updated');$service=app(ExecutiveBriefSource::class);
        [$case,$tr,$fs]=$service->parents($c,$this->owner->id);$src=$service->assemble($c,$case,$tr,$fs);
        $url='/sifi/issue-candidates/'.$c->id.'/executive-brief';
        $this->post($url,['candidate_revision'=>1,'case_revision'=>1,'trajectory_revision'=>1,'foresight_revision'=>1,'input_digest'=>$service->digest($src),
            'title'=>'[SIMULASI] Brief PAD otomatis','audience'=>'Automated fixture acceptance',
            'executive_summary'=>'[SIMULASI] Revisi record agregat belum membuktikan perubahan ekonomi. Ini uji otomatis, bukan telaah manusia.',
            'decision_questions'=>'Data primer apa yang diperlukan sebelum mengambil keputusan?',
            'verification_priorities'=>'Periksa rights, periode, versi, schema dan angka primer yang sah.',
            'limitations'=>'SYNTHETIC FIXTURE ONLY. Hak komersial/schema API belum terbukti; bukan angka resmi.','review_state'=>'DRAFT'])->assertRedirect($url);
        $download=$this->get($url.'/download')->assertOk();
        foreach(['SYNTHETIC FIXTURE ONLY','SIFI_YK_PAD_IMPORT_V1','COMMERCIAL_REUSE_UNCONFIRMED','SRC-OPENDATA-YK-PAD','record_key','content_hash','record_version','raw_record','fetched_at','UNKNOWN','claim_id','evidence_id'] as $text)$download->assertSee($text,false);
        $this->assertStringNotContainsString('BMKG sebagai rujukan format',$download->getContent());
        $this->assertStringNotContainsString('Prakiraan dapat direvisi',$download->getContent());
        $this->assertDatabaseCount('sifi_evidence_review_notes',0);$this->assertDatabaseCount('sifi_event_hypotheses',0);
        $this->assertDatabaseHas('sifi_issue_case_drafts',['candidate_id'=>$c->id,'verification_state'=>'NOT_VERIFIED']);
        $dir=getenv('R17_RESULT_DIR');if($dir){file_put_contents($dir.'/evidence/SOURCE_TO_BRIEF_DEMO.md',$download->getContent());file_put_contents($dir.'/evidence/SOURCE_TO_BRIEF_TRACE.json',json_encode($src['source_lineage'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
        Http::assertNothingSent();
    }
    public function test_import_lineage_survives_session_loss_and_uses_stored_timestamp_not_wall_clock():void
    {
        $s=$this->register();$this->import($this->adapter->template());$c=$this->create($s,'pad_import');$pipeline=app(SourceObservationPipeline::class);$lineage=$pipeline->lineage($c);
        session()->forget('ae08_pad_preview');$this->travel(3)->days();$this->assertEquals($lineage,$pipeline->lineage($c));$this->travelBack();
    }
    public function test_later_write_failure_rolls_back_new_chain_but_not_existing_source():void
    {
        $s=$this->register();DB::listen(function($q){if(str_contains($q->sql,'insert into "sifi_foresight_drafts"'))throw new \RuntimeException('isolated failure');});
        try {app(SourceObservationPipeline::class)->createDraft($this->owner,'pad_valid','Automated failure test',$s,$s->revision,null,$this->adapter->preview('pad_valid')['packet_hash']);$this->fail('Failure required');}
        catch(\RuntimeException $e){$this->assertSame('isolated failure',$e->getMessage());}
        foreach(['sifi_issue_candidates','sifi_evidence_drafts','sifi_claim_drafts','sifi_investigations'] as $table)$this->assertDatabaseCount($table,0);
        $this->assertDatabaseCount('sifi_source_drafts',1);
    }
    public function test_native_csrf_boundary_rejects_unprotected_import_preview():void
    {
        Route::getRoutes()->getByName('sifi.source-catalog.connector-preview')->middleware(PadEnforcedCsrf::class);
        $this->actingAs($this->owner)->post('/sifi/source-catalog/connector-preview',['import_json'=>json_encode($this->adapter->template())])->assertStatus(419);
        $this->assertDatabaseCount('sifi_issue_candidates',0);
    }
    public function test_changed_session_preview_is_rejected_before_draft_creation():void
    {
        $s=$this->register();$packet=$this->adapter->template();$this->import($packet);$old=$this->fields($s,'pad_import');
        $packet['pages'][0]['records'][0]['amount_rupiah']=201000000;$this->import($packet);
        $this->post('/sifi/source-catalog/pipeline-draft',$old)->assertSessionHasErrors('input_digest');
        $this->assertDatabaseCount('sifi_issue_candidates',0);
    }
}
class PadTransportHarness extends YogyakartaPadSourceAdapter {public function inspect(int $year):array{return $this->readOfficialResponse($year);}}
class PadEnforcedCsrf extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {protected function runningUnitTests(){return false;}}
