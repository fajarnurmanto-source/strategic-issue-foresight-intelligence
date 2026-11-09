<?php
namespace Tests\Feature\Sifi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;
/** Regression for the local, not-authorized executive briefing workflow. */
final class SifiExecutiveBriefWorkflowTest extends TestCase {
 use RefreshDatabase;
 private User $owner;private string $candidate;private string $case;private string $trajectory;private string $foresight;private string $url;
 protected function setUp():void {
  parent::setUp();$this->withoutVite();
  $this->owner=User::factory()->create(['email_verified_at'=>now()]);
  $this->candidate=(string)Str::uuid();$this->case=(string)Str::uuid();
  $this->trajectory=(string)Str::uuid();$this->foresight=(string)Str::uuid();$inv=(string)Str::uuid();
  $this->url='/sifi/issue-candidates/'.$this->candidate.'/executive-brief';
  DB::table('sifi_investigations')->insert(['id'=>$inv,'user_id'=>$this->owner->id,'title'=>'Investigation','review_state'=>'DRAFT','revision'=>1,'created_at'=>now(),'updated_at'=>now()]);
  DB::table('sifi_issue_candidates')->insert(['id'=>$this->candidate,'investigation_id'=>$inv,'user_id'=>$this->owner->id,
    'title'=>'Candidate','issue_hypothesis'=>'This is only a tentative claim','evidence_gaps'=>'Primary evidence missing','next_checks'=>'Find originals',
    'review_state'=>'DRAFT','revision'=>3,'created_at'=>now(),'updated_at'=>now()]);
  DB::table('sifi_issue_case_drafts')->insert(['id'=>$this->case,'candidate_id'=>$this->candidate,'owner_user_id'=>$this->owner->id,
    'title'=>'Issue Case','issue_domain'=>'environment','geography_label'=>'City A','scope_note'=>'Unproven',
    'lifecycle_state'=>'DRAFT','verification_state'=>'NOT_VERIFIED','revision'=>1,'created_at'=>now(),'updated_at'=>now()]);
  DB::table('sifi_trajectory_drafts')->insert(['id'=>$this->trajectory,'case_id'=>$this->case,'owner_user_id'=>$this->owner->id,
    'horizon'=>'UNSPECIFIED','trajectory_hypothesis'=>'Unknown direction','risk_hypothesis'=>'Unconfirmed risk',
    'opportunity_hypothesis'=>'Unconfirmed action','watch_condition'=>'No baseline','uncertainty_note'=>'No observations',
    'revision'=>1,'created_at'=>now(),'updated_at'=>now()]);
  DB::table('sifi_foresight_drafts')->insert(['id'=>$this->foresight,'trajectory_id'=>$this->trajectory,'owner_user_id'=>$this->owner->id,
    'baseline_scenario'=>'Unknown baseline outcome','alternative_scenario'=>'Alternative is tentative',
    'stress_scenario'=>'Stress is hypothetical','critical_drivers'=>'Unknown drivers',
    'falsification_checks'=>'Gather counterevidence','decision_options'=>'Options unapproved',
    'uncertainty_note'=>'No verified series','revision'=>1,'created_at'=>now(),'updated_at'=>now()]);
 }
 private function digest():string {
  return app(\App\Services\Sifi\ExecutiveBriefSource::class)->digest(
   app(\App\Services\Sifi\ExecutiveBriefSource::class)->assemble(
    \App\Models\SifiIssueCandidate::findOrFail($this->candidate),
    \App\Models\SifiIssueCaseDraft::findOrFail($this->case),
    \App\Models\SifiTrajectoryDraft::findOrFail($this->trajectory),
    \App\Models\SifiForesightDraft::findOrFail($this->foresight)));
 }
 private function fields(array $overrides=[]):array {
  return array_merge(['candidate_revision'=>3,'case_revision'=>1,'trajectory_revision'=>1,'foresight_revision'=>1,
   'input_digest'=>$this->digest(),'title'=>'Internal assessment briefing','audience'=>'Analyst review only',
   'executive_summary'=>'This is a provisional and unverified interpretation of fixture data.',
   'decision_questions'=>'Which observations are necessary before any institutional decision?',
   'verification_priorities'=>'Confirm rights and primary evidence before making a recommendation.',
   'limitations'=>'No real sources or temporal evidence, no verified forecast or approved decision.',
   'review_state'=>'DRAFT'],$overrides);
 }
 private function create():void {$this->actingAs($this->owner)->post($this->url,$this->fields())->assertRedirect($this->url);}
 public function test_guests_cannot_view_or_create():void {
  $this->get($this->url)->assertRedirect('/login');$this->post($this->url,[])->assertRedirect('/login');
 }
 public function test_owner_can_create_and_view_audited_draft():void {
  $this->create();$this->assertDatabaseCount('sifi_executive_briefs',1);
  $this->assertDatabaseCount('sifi_executive_brief_audit',1);
  $this->get($this->url)->assertOk()->assertInertia(fn(AssertableInertia $page)=>$page
   ->component('sifi/executive-brief-workspace')->where('brief.review_state','DRAFT')->where('sourceCurrent',true)->etc());
 }
 public function test_another_user_is_forbidden_before_validation():void {
  $this->create();$stranger=User::factory()->create(['email_verified_at'=>now()]);$this->actingAs($stranger);
  $this->get($this->url)->assertForbidden();
  $this->post($this->url,['verified'=>true])->assertForbidden();
  $this->get($this->url.'/download')->assertForbidden();
 }
 public function test_rejects_verified_or_publication_claims():void {
  $this->actingAs($this->owner)->post($this->url,$this->fields(['verified'=>true,'published'=>true]))
   ->assertSessionHasErrors(['verified','published']);
  $this->assertDatabaseCount('sifi_executive_briefs',0);
 }
 public function test_stale_digest_is_rejected_without_db_write():void {
  $old=$this->digest();DB::table('sifi_trajectory_drafts')->where('id',$this->trajectory)
   ->update(['revision'=>2,'risk_hypothesis'=>'Changed risk assumption']);
  $this->actingAs($this->owner)->post($this->url,$this->fields([
   'trajectory_revision'=>2,'input_digest'=>$old]))->assertSessionHasErrors('input_digest');
  $this->assertDatabaseCount('sifi_executive_briefs',0);
 }
 public function test_optimistic_revision_and_append_audit():void {
  $this->create();$this->post($this->url,$this->fields([
   'brief_revision'=>1,'review_state'=>'REVIEW_REQUESTED']))->assertRedirect($this->url);
  $this->assertDatabaseHas('sifi_executive_briefs',['candidate_id'=>$this->candidate,'revision'=>2,
    'review_state'=>'REVIEW_REQUESTED']);
  $this->post($this->url,$this->fields(['brief_revision'=>1]))->assertSessionHasErrors('brief_revision');
  $this->assertDatabaseCount('sifi_executive_brief_audit',2);
 }
 public function test_download_watermarks_and_has_no_verified_issues():void {
  $this->create();$r=$this->get($this->url.'/download')->assertOk();
  $this->assertMatchesRegularExpression('/## Judul briefing\n\n> \S/u',$r->getContent());
  $r->assertSee('UNAPPROVED OFFLINE DRAFT',false);
  $r->assertSee('EVIDENCE NOT_EVALUATED',false);
  $r->assertSee('No real sources or temporal evidence',false);
  $this->assertDatabaseMissing('sifi_issue_case_drafts',['id'=>$this->case,'verification_state'=>'VERIFIED']);
 }
 public function test_download_is_blocked_if_upstream_changes():void {
  $this->create();DB::table('sifi_foresight_drafts')->where('id',$this->foresight)->update([
    'revision'=>2,'stress_scenario'=>'New hypothetical stress case']);
  $this->get($this->url.'/download')->assertStatus(409);
 }
 public function test_identical_save_is_idempotent():void {
  $this->create();$this->post($this->url,$this->fields(['brief_revision'=>1]))->assertRedirect($this->url);
  $this->assertDatabaseCount('sifi_executive_brief_audit',1);
 }
}
