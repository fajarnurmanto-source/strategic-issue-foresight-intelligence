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

final class SifiEvidenceDiagnosticsTest extends TestCase
{
    use RefreshDatabase;
    private User $owner;
    private string $candidate;
    private string $investigation;
    private string $evidence;
    private string $signal;
    private string $url;

    protected function setUp():void
    {
        parent::setUp();$this->withoutVite();Http::preventStrayRequests();
        config()->set('sifi_aq01.base_url','http://127.0.0.1:8766');
        config()->set('sifi_aq01.token',str_repeat('x',40));
        $this->owner=User::factory()->create(['email_verified_at'=>now()]);
        $this->candidate=(string) Str::uuid();$this->investigation=(string) Str::uuid();
        $this->evidence=(string) Str::uuid();$this->signal=str_repeat('a',64);
        $this->url='/sifi/issue-candidates/'.$this->candidate.'/evidence-diagnostics';
        DB::table('sifi_investigations')->insert(['id'=>$this->investigation,'user_id'=>$this->owner->id,
            'title'=>'Fixture','review_state'=>'DRAFT','revision'=>1,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('sifi_issue_candidates')->insert(['id'=>$this->candidate,'investigation_id'=>$this->investigation,
            'user_id'=>$this->owner->id,'title'=>'Candidate','issue_hypothesis'=>'Hipotesis uji',
            'review_state'=>'DRAFT','revision'=>2,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('sifi_investigation_signals')->insert(['investigation_id'=>$this->investigation,
            'signal_id'=>$this->signal,'added_by_user_id'=>$this->owner->id,'created_at'=>now()]);
        DB::table('sifi_evidence_drafts')->insert(['id'=>$this->evidence,'candidate_id'=>$this->candidate,
            'owner_user_id'=>$this->owner->id,'signal_id'=>$this->signal,'claim_text'=>'Dugaan klaim perlu diperiksa',
            'relationship'=>'UNKNOWN','workflow_state'=>'DRAFT','revision'=>1,
            'created_at'=>now(),'updated_at'=>now()]);
    }
    private function fake(array $modify=[]):void
    {
        Http::fake(function (Outbound $r) use ($modify) {
            $d=$r->data();
            return Http::response(array_merge([
                'schema_version'=>'sifi.evidence.diagnostics.v1','mode'=>'OFFLINE_FIXTURE_ONLY',
                'kind'=>'HUMAN_REVIEW_AID_ONLY','status'=>'INSUFFICIENT_FOR_QUALIFICATION',
                'evidence_admission'=>'NOT_EVALUATED','independence_verified'=>false,
                'claim_verified'=>false,'python_issue_created'=>false,
                'candidate_id'=>$d['candidate_id'],'candidate_revision'=>$d['candidate_revision'],
                'request_nonce'=>$d['request_nonce'],'drafts'=>$d['drafts'],
                'draft_count'=>count($d['drafts']),'distinct_source_count'=>1,
                'possible_syndication'=>false,'duplicate_signal_groups'=>[],
                'relationship_counts'=>['UNKNOWN'=>1,'SUPPORTS'=>0,'CONTRADICTS'=>0,'CONTEXT'=>0],
                'signal_observations'=>[['evidence_id'=>$d['drafts'][0]['evidence_id'],
                    'signal_id'=>$d['drafts'][0]['signal_id'],'source_id'=>'SRC-ALPHA',
                    'signal_version'=>1,'relationship'=>'UNKNOWN']],
                'verification_checks'=>['Periksa dokumen primer'],
            ],$modify));
        });
    }
    public function test_guest_rejected():void
    {
        $this->get($this->url)->assertRedirect('/login');
        $this->post($this->url,['revision'=>2])->assertRedirect('/login');
    }
    public function test_outsider_rejected_before_validation_and_http():void
    {
        $other=User::factory()->create(['email_verified_at'=>now()]);
        $this->actingAs($other)->get($this->url)->assertForbidden();
        $this->post($this->url,['revision'=>'bad'])->assertForbidden();
        Http::assertNothingSent();
    }
    public function test_stale_revision_rejected_without_http():void
    {
        $this->actingAs($this->owner)->post($this->url,['revision'=>1])->assertSessionHasErrors('revision');
        Http::assertNothingSent();
    }
    public function test_owner_sees_drafts_and_snapshot_is_not_admission():void
    {
        $this->fake();
        $this->actingAs($this->owner)->get($this->url)->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->component('sifi/evidence-diagnostics')->has('drafts',1)->etc());
        $this->post($this->url,['revision'=>2])->assertRedirect($this->url);
        $this->assertDatabaseCount('sifi_evidence_diagnostics',1);
        $this->assertDatabaseHas('sifi_evidence_diagnostics',['candidate_id'=>$this->candidate,
            'status'=>'ADVISORY_ONLY','candidate_revision'=>2]);
        $this->assertDatabaseHas('sifi_evidence_drafts',['id'=>$this->evidence,'revision'=>1,'workflow_state'=>'DRAFT']);
        $this->assertDatabaseHas('sifi_issue_candidates',['id'=>$this->candidate,'revision'=>2]);
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('sifi_qualified_evidence'));
        $this->get($this->url)->assertOk()->assertInertia(fn(AssertableInertia $p)=>
            $p->component('sifi/evidence-diagnostics')->has('diagnostics',1)
                ->where('diagnostics.0.is_current',true)
                ->where('diagnostics.0.result.evidence_admission','NOT_EVALUATED')->etc());
    }
    public function test_duplicate_request_is_idempotent():void
    {
        $this->fake();$this->actingAs($this->owner)->post($this->url,['revision'=>2])->assertRedirect();
        $this->post($this->url,['revision'=>2])->assertRedirect();
        $this->assertDatabaseCount('sifi_evidence_diagnostics',1);Http::assertSentCount(1);
    }
    public function test_engine_outage_writes_nothing():void
    {
        Http::fake(['http://127.0.0.1:8766/*'=>Http::response(['error'=>'offline'],503)]);
        $this->actingAs($this->owner)->post($this->url,['revision'=>2])->assertSessionHasErrors('diagnostics');
        $this->assertDatabaseCount('sifi_evidence_diagnostics',0);
    }
    public function test_fake_qualified_response_fails_closed():void
    {
        $this->fake(['evidence_admission'=>'ADMITTED']);
        $this->actingAs($this->owner)->post($this->url,['revision'=>2])->assertSessionHasErrors('diagnostics');
        $this->assertDatabaseCount('sifi_evidence_diagnostics',0);
    }
    public function test_draft_change_during_http_discards_result():void
    {
        Http::fake(function (Outbound $r) {
            DB::table('sifi_evidence_drafts')->where('id',$this->evidence)->update(['revision'=>2]);
            $d=$r->data();
            return Http::response([
                'schema_version'=>'sifi.evidence.diagnostics.v1','mode'=>'OFFLINE_FIXTURE_ONLY',
                'kind'=>'HUMAN_REVIEW_AID_ONLY','status'=>'INSUFFICIENT_FOR_QUALIFICATION',
                'evidence_admission'=>'NOT_EVALUATED','independence_verified'=>false,
                'claim_verified'=>false,'python_issue_created'=>false,
                'candidate_id'=>$d['candidate_id'],'candidate_revision'=>$d['candidate_revision'],
                'request_nonce'=>$d['request_nonce'],'drafts'=>$d['drafts'],
                'draft_count'=>1,'distinct_source_count'=>1,'possible_syndication'=>false,
                'duplicate_signal_groups'=>[],
                'relationship_counts'=>['UNKNOWN'=>1,'SUPPORTS'=>0,'CONTRADICTS'=>0,'CONTEXT'=>0],
                'signal_observations'=>[['evidence_id'=>$this->evidence,'signal_id'=>$this->signal,
                    'source_id'=>'SRC-ALPHA','signal_version'=>1,'relationship'=>'UNKNOWN']],
                'verification_checks'=>['Periksa bukti'],
            ]);
        });
        $this->actingAs($this->owner)->post($this->url,['revision'=>2])->assertSessionHasErrors('diagnostics');
        $this->assertDatabaseCount('sifi_evidence_diagnostics',0);
    }
    public function test_malformed_relationship_counts_fail_closed():void
    {
        $this->fake(['relationship_counts'=>['UNKNOWN'=>0,'SUPPORTS'=>1,'CONTRADICTS'=>0,'CONTEXT'=>0]]);
        $this->actingAs($this->owner)->post($this->url,['revision'=>2])->assertSessionHasErrors('diagnostics');
        $this->assertDatabaseCount('sifi_evidence_diagnostics',0);
    }
    public function test_no_evidence_draft_is_rejected():void
    {
        DB::table('sifi_evidence_drafts')->where('id',$this->evidence)->delete();
        $this->actingAs($this->owner)->post($this->url,['revision'=>2])->assertSessionHasErrors('diagnostics');
        Http::assertNothingSent();
    }
}
