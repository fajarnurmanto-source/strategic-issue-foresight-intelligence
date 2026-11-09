<?php
namespace Tests\Feature\Sifi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as Outbound;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class SifiClaimEventWorkflowTest extends TestCase {
 use RefreshDatabase;
 private User $owner;private string $candidate;private string $investigation;
 private string $evidence;private string $signal;private string $url;
 protected function setUp():void {
  parent::setUp();$this->withoutVite();Http::preventStrayRequests();
  config()->set('sifi_aq01.base_url','http://127.0.0.1:8766');
  config()->set('sifi_aq01.token',str_repeat('x',40));
  $this->owner=User::factory()->create(['email_verified_at'=>now()]);
  $this->candidate=(string)Str::uuid();$this->investigation=(string)Str::uuid();
  $this->evidence=(string)Str::uuid();$this->signal=str_repeat('a',64);
  $this->url='/sifi/issue-candidates/'.$this->candidate.'/claim-events';
  DB::table('sifi_investigations')->insert(['id'=>$this->investigation,'user_id'=>$this->owner->id,
   'title'=>'Test','review_state'=>'DRAFT','revision'=>1,'created_at'=>now(),'updated_at'=>now()]);
  DB::table('sifi_issue_candidates')->insert(['id'=>$this->candidate,'investigation_id'=>$this->investigation,
   'user_id'=>$this->owner->id,'title'=>'Test candidate','review_state'=>'DRAFT','revision'=>2,
   'created_at'=>now(),'updated_at'=>now()]);
  DB::table('sifi_investigation_signals')->insert(['investigation_id'=>$this->investigation,
   'signal_id'=>$this->signal,'added_by_user_id'=>$this->owner->id,'created_at'=>now()]);
  DB::table('sifi_evidence_drafts')->insert(['id'=>$this->evidence,'candidate_id'=>$this->candidate,
   'owner_user_id'=>$this->owner->id,'signal_id'=>$this->signal,'claim_text'=>'Kalimat dugaan',
   'relationship'=>'UNKNOWN','workflow_state'=>'DRAFT','revision'=>1,
   'created_at'=>now(),'updated_at'=>now()]);
 }
 private function claim():string {
  $this->actingAs($this->owner)->post($this->url.'/claims',[
   'candidate_revision'=>2,'evidence_id'=>$this->evidence,'statement'=>'Contoh klaim hipotetis',
   'attribution'=>'Belum diketahui','modality'=>'UNDETERMINED'])->assertRedirect($this->url);
  return (string)DB::table('sifi_claim_drafts')->where('candidate_id',$this->candidate)->value('id');
 }
 private function event():string {
  $this->actingAs($this->owner)->post($this->url.'/events',[
   'candidate_revision'=>2,'summary'=>'Dugaan kejadian belum diperiksa',
   'place_note'=>'','time_note'=>''])->assertRedirect($this->url);
  return (string)DB::table('sifi_event_hypotheses')->where('candidate_id',$this->candidate)->value('id');
 }
 private function fake(array $change=[]):void {
  Http::fake(function(Outbound $r) use($change){$d=$r->data();return Http::response(array_merge([
    'schema_version'=>'sifi.claim_event.advisory.v1','mode'=>'OFFLINE_FIXTURE_ONLY',
    'kind'=>'STRUCTURAL_REVIEW_AID_ONLY','status'=>'NOT_VERIFIED','claim_verified'=>false,
    'event_verified'=>false,'evidence_admission'=>'NOT_EVALUATED','python_issue_created'=>false,
    'candidate_id'=>$d['candidate_id'],'candidate_revision'=>$d['candidate_revision'],
    'request_nonce'=>$d['request_nonce'],'claim_count'=>count($d['claims']),
    'event_count'=>count($d['events']),'link_count'=>count($d['links']),
    'distinct_source_count'=>1,'signal_observations'=>[['signal_id'=>$this->signal,
     'source_id'=>'SRC-ALPHA','signal_version'=>1]],'verification_checks'=>['Periksa sumber primer'],
  ],$change));});
 }
 public function test_guest_rejected():void {
  $this->get($this->url)->assertRedirect('/login');
  $this->post($this->url.'/claims',[])->assertRedirect('/login');
 }
 public function test_outsider_denied_before_validation():void {
  $other=User::factory()->create(['email_verified_at'=>now()]);
  $this->actingAs($other)->get($this->url)->assertForbidden();
  $this->post($this->url.'/claims',['candidate_revision'=>'fake'])->assertForbidden();
  $this->post($this->url.'/assess',[])->assertForbidden();
  Http::assertNothingSent();
 }
 public function test_claim_is_from_local_evidence_and_never_verified():void {
  $id=$this->claim();
  $this->assertDatabaseHas('sifi_claim_drafts',['id'=>$id,'evidence_id'=>$this->evidence,
      'verification_state'=>'NOT_VERIFIED','modality'=>'UNDETERMINED']);
  $this->assertDatabaseCount('sifi_claim_event_audit',1);
  $this->actingAs($this->owner)->get($this->url)->assertOk()->assertInertia(
   fn(AssertableInertia $page)=>$page->component('sifi/claim-event-workspace')->has('claims',1)->etc());
 }
 public function test_forged_evidence_or_outsider_evidence_rejected():void {
  $this->actingAs($this->owner)->post($this->url.'/claims',[
    'candidate_revision'=>2,'evidence_id'=>(string)Str::uuid(),
    'statement'=>'Contoh klaim','modality'=>'REPORTED'])->assertSessionHasErrors('evidence_id');
  $this->assertDatabaseCount('sifi_claim_drafts',0);
 }
 public function test_event_is_only_a_hypothesis():void {
  $id=$this->event();$this->assertDatabaseHas('sifi_event_hypotheses',[
    'id'=>$id,'verification_state'=>'NOT_VERIFIED','place_note'=>'']);
 }
 public function test_claim_event_link_is_non_authoritative_and_idempotent():void {
  $claim=$this->claim();$event=$this->event();
  $payload=['candidate_revision'=>2,'claim_id'=>$claim,'event_id'=>$event,'relation'=>'POSSIBLE_ASSOCIATION'];
  $this->actingAs($this->owner)->post($this->url.'/links',$payload)->assertRedirect($this->url);
  $this->post($this->url.'/links',$payload)->assertRedirect($this->url);
  $this->assertDatabaseCount('sifi_claim_event_links',1);
  $this->assertDatabaseCount('sifi_claim_event_audit',3);
 }
 public function test_link_to_nonexistent_event_rejected():void {
  $claim=$this->claim();
  $this->actingAs($this->owner)->post($this->url.'/links',[
   'candidate_revision'=>2,'claim_id'=>$claim,'event_id'=>(string)Str::uuid(),
   'relation'=>'POSSIBLE_ASSOCIATION'])->assertSessionHasErrors('relation');
  $this->assertDatabaseCount('sifi_claim_event_links',0);
 }
 public function test_stale_revision_rejected_before_write():void {
  $this->actingAs($this->owner)->post($this->url.'/events',[
   'candidate_revision'=>1,'summary'=>'Dugaan peristiwa'])->assertSessionHasErrors('candidate_revision');
  $this->assertDatabaseCount('sifi_event_hypotheses',0);
 }
 public function test_python_advisory_snapshot_is_idempotent_and_safe():void {
  $this->claim();$this->fake();
  $this->actingAs($this->owner)->post($this->url.'/assess',['candidate_revision'=>2])->assertRedirect($this->url);
  $this->post($this->url.'/assess',['candidate_revision'=>2])->assertRedirect($this->url);
  $this->assertDatabaseCount('sifi_claim_event_assessments',1);Http::assertSentCount(1);
  $this->assertDatabaseHas('sifi_claim_event_assessments',['status'=>'ADVISORY_ONLY']);
  $this->assertDatabaseHas('sifi_claim_drafts',['verification_state'=>'NOT_VERIFIED']);
  $this->get($this->url)->assertOk()->assertInertia(fn(AssertableInertia $p)=>
   $p->component('sifi/claim-event-workspace')->has('assessments',1)
     ->where('assessments.0.result.event_verified',false)->etc());
 }
 public function test_forged_python_approval_fails_closed():void {
  $this->claim();$this->fake(['claim_verified'=>true]);
  $this->actingAs($this->owner)->post($this->url.'/assess',['candidate_revision'=>2])->assertSessionHasErrors('analysis');
  $this->assertDatabaseCount('sifi_claim_event_assessments',0);
 }
 public function test_python_offline_fails_closed():void {
  $this->claim();Http::fake(['http://127.0.0.1:8766/*'=>Http::response(['error'=>'offline'],503)]);
  $this->actingAs($this->owner)->post($this->url.'/assess',['candidate_revision'=>2])->assertSessionHasErrors('analysis');
  $this->assertDatabaseCount('sifi_claim_event_assessments',0);
 }
 public function test_input_change_during_python_discards_snapshot():void {
  $this->claim();
  Http::fake(function(Outbound $r){
    DB::table('sifi_evidence_drafts')->where('id',$this->evidence)->update(['revision'=>2]);
    $d=$r->data();return Http::response(['schema_version'=>'sifi.claim_event.advisory.v1',
      'mode'=>'OFFLINE_FIXTURE_ONLY','kind'=>'STRUCTURAL_REVIEW_AID_ONLY','status'=>'NOT_VERIFIED',
      'claim_verified'=>false,'event_verified'=>false,'evidence_admission'=>'NOT_EVALUATED',
      'python_issue_created'=>false,'candidate_id'=>$d['candidate_id'],
      'candidate_revision'=>$d['candidate_revision'],'request_nonce'=>$d['request_nonce'],
      'claim_count'=>1,'event_count'=>0,'link_count'=>0,'distinct_source_count'=>1,
      'signal_observations'=>[['signal_id'=>$this->signal,'source_id'=>'SRC-ALPHA','signal_version'=>1]],
      'verification_checks'=>['Periksa sumber primer']]);
  });
  $this->actingAs($this->owner)->post($this->url.'/assess',['candidate_revision'=>2])->assertSessionHasErrors('analysis');
  $this->assertDatabaseCount('sifi_claim_event_assessments',0);
 }
 public function test_no_python_issue_or_admitted_evidence_tables_created():void {
  $this->claim();$this->assertFalse(DB::getSchemaBuilder()->hasTable('sifi_python_issues'));
  $this->assertFalse(DB::getSchemaBuilder()->hasTable('sifi_qualified_evidence'));
 }
}
