<?php

namespace Tests\Feature\Sifi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class SifiEvidenceIntakeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function user(bool $verified = true): User
    {
        return User::factory()->create(['email_verified_at' => $verified ? now() : null]);
    }

    private function fixture(User $owner, bool $link = true): array
    {
        $i = (string) Str::uuid();
        $c = (string) Str::uuid();
        $signal = str_repeat('a', 64);
        DB::table('sifi_investigations')->insert(['id' => $i, 'user_id' => $owner->id, 'title' => 'Fixture',
            'review_state' => 'DRAFT', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sifi_issue_candidates')->insert(['id' => $c, 'investigation_id' => $i, 'user_id' => $owner->id,
            'title' => 'Kandidat fixture', 'review_state' => 'DRAFT', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        if ($link) {
            DB::table('sifi_investigation_signals')->insert(['investigation_id' => $i, 'signal_id' => $signal,
                'added_by_user_id' => $owner->id, 'created_at' => now()]);
        }

        return [$c, $signal];
    }

    private function payload(string $signal): array
    {
        return ['signal_id' => $signal, 'claim_text' => 'Dugaan publikasi memuat indikator perubahan lingkungan',
            'relationship' => 'UNKNOWN', 'rationale' => 'Sumber primer belum tersedia', 'known_gaps' => 'Data resmi belum diverifikasi'];
    }

    private function create(User $u, string $c, string $s): string
    {
        $this->actingAs($u)->post("/sifi/issue-candidates/$c/evidence", $this->payload($s))->assertRedirect();

        return DB::table('sifi_evidence_drafts')->where('candidate_id', $c)->value('id');
    }

    public function test_readiness_hides_unlinked_provenance_and_blocks_review_until_reference_returns(): void
    {
        $owner = $this->user();
        $reviewer = $this->user();
        [$candidate,$signal] = $this->fixture($owner);
        $draft = $this->create($owner, $candidate, $signal);
        $page = route('sifi.evidence.index', $candidate);
        $request = "/sifi/evidence/{$draft}/request-review";
        $this->actingAs($owner)->get($page)->assertInertia(fn ($view) => $view
            ->where('evidence.0.review_readiness.state', 'READY_TO_REQUEST')
            ->where('evidence.0.history.0.action', 'CREATED')
            ->where('evidence.0.history.0.revision', 1)
            ->missing('evidence.0.history.0.snapshot_json'));
        $link = DB::table('sifi_investigation_signals')->where('signal_id', $signal);
        $previous = (array) $link->first();
        $link->delete();
        $this->get($page)->assertInertia(fn ($view) => $view
            ->where('evidence.0.review_readiness.state', 'NEEDS_CONTEXT')
            ->has('evidence.0.review_readiness.blockers.signal_id')
            ->where('evidence.0.source_provenance', null));
        $payload = ['revision' => 1, 'reviewer_email' => $reviewer->email];
        $this->post($request, $payload)->assertSessionHasErrors('signal_id');
        $this->assertDatabaseCount('sifi_evidence_draft_audit', 1);
        DB::table('sifi_investigation_signals')->insert($previous);
        $this->post($request, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->get($page)->assertInertia(fn ($view) => $view
            ->where('evidence.0.review_readiness.state', 'WAITING_REVIEW')
            ->where('evidence.0.history.0.action', 'REVIEW_REQUESTED')
            ->where('evidence.0.history.1.action', 'CREATED'));
    }

    public function test_owner_revises_and_resubmits_without_rewriting_review_or_audit(): void
    {
        $owner = $this->user();
        $reviewer = $this->user();
        [$candidate,$signal] = $this->fixture($owner);
        $draft = $this->create($owner, $candidate, $signal);
        $url = "/sifi/evidence/{$draft}";
        $payload = $this->payload($signal);
        unset($payload['signal_id']);
        $firstReview = ['revision' => 2, 'review_finding' => 'FOLLOWUP_REQUIRED', 'review_notes' => 'Lengkapi konteks sumber sebelum telaah ulang.'];
        $this->post($url.'/request-review', ['revision' => 1, 'reviewer_email' => $reviewer->email])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($reviewer)->post($url.'/review', $firstReview)->assertSessionHasNoErrors()->assertRedirect();
        $this->patch($url, $payload + ['revision' => 3])->assertForbidden();
        $this->actingAs($owner)->patch($url, $payload + ['revision' => 2])->assertSessionHasErrors('revision');
        $this->patch($url, $payload + ['revision' => 3])->assertSessionHasErrors('follow_up');
        $payload['known_gaps'] = 'Lingkup waktu data dijelaskan dalam revisi.';
        $this->patch($url, $payload + ['revision' => 3])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('sifi_evidence_drafts', ['id' => $draft, 'workflow_state' => 'DRAFT', 'revision' => 4, 'reviewer_user_id' => null]);
        $this->actingAs($reviewer)->post($url.'/review', $firstReview)->assertForbidden();
        $this->actingAs($owner)->post($url.'/request-review', ['revision' => 4, 'reviewer_email' => $reviewer->email])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($reviewer)->post($url.'/review', $firstReview)->assertSessionHasErrors('revision');
        $this->post($url.'/review', ['revision' => 5, 'review_finding' => 'INDEPENDENCE_UNRESOLVED', 'review_notes' => 'Periksa independensi sumber pembanding.'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('sifi_evidence_review_notes', ['evidence_id' => $draft, 'evidence_revision' => 3, 'review_notes' => $firstReview['review_notes']]);
        $this->assertDatabaseHas('sifi_evidence_review_notes', ['evidence_id' => $draft, 'evidence_revision' => 6]);
        $this->assertDatabaseCount('sifi_evidence_review_notes', 2);
        $this->assertSame(['CREATED', 'REVIEW_REQUESTED', 'REVIEW_RECORDED', 'REVISED_AFTER_REVIEW', 'REVIEW_RESUBMITTED', 'REVIEW_RECORDED'],
            DB::table('sifi_evidence_draft_audit')->where('evidence_id', $draft)->orderBy('revision')->pluck('action')->all());
        $this->actingAs($owner)->get("/sifi/issue-candidates/{$candidate}/evidence")
            ->assertInertia(fn ($view) => $view->where('evidence.0.reviews.0.evidence_revision', 6)
                ->where('evidence.0.reviews.1.evidence_revision', 3));
        Http::assertNothingSent();
    }

    public function test_guest_must_login(): void
    {
        $this->get('/sifi/evidence-reviews')->assertRedirect('/login');
        $this->get('/sifi/issue-candidates/'.Str::uuid().'/evidence')->assertRedirect('/login');
    }

    public function test_intake_is_local_draft_and_produces_no_http_calls(): void
    {
        $u = $this->user();
        [$c,$s] = $this->fixture($u);
        $id = $this->create($u, $c, $s);
        $this->assertNotEmpty($id);
        $this->assertDatabaseHas('sifi_evidence_drafts', ['id' => $id, 'workflow_state' => 'DRAFT',
            'reviewer_user_id' => null, 'revision' => 1]);
        $this->assertDatabaseHas('sifi_evidence_draft_audit', ['evidence_id' => $id, 'action' => 'CREATED', 'revision' => 1]);
        $this->get("/sifi/issue-candidates/$c/evidence")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('sifi/evidence-intake')->has('evidence', 1)->etc());
        Http::assertNothingSent();
    }

    public function test_only_attached_signal_can_be_used(): void
    {
        $u = $this->user();
        [$c,$s] = $this->fixture($u, false);
        $this->actingAs($u)->post("/sifi/issue-candidates/$c/evidence", $this->payload($s))->assertSessionHasErrors('signal_id');
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
    }

    public function test_cross_owner_denied(): void
    {
        $owner = $this->user();
        [$c,$s] = $this->fixture($owner);
        $id = $this->create($owner, $c, $s);
        $other = $this->user();
        $this->actingAs($other)->get("/sifi/issue-candidates/$c/evidence")->assertForbidden();
        $this->post("/sifi/issue-candidates/$c/evidence", $this->payload($s))->assertForbidden();
        $this->patch("/sifi/evidence/$id", array_merge($this->payload($s), ['revision' => 1]))->assertForbidden();
        $this->post("/sifi/evidence/$id/request-review", ['revision' => 1, 'reviewer_email' => $other->email])->assertForbidden();
        $this->assertDatabaseCount('sifi_evidence_draft_audit', 1);
    }

    public function test_blocks_fake_admission_and_fabricated_states(): void
    {
        $u = $this->user();
        [$c,$s] = $this->fixture($u);
        $this->actingAs($u)->post("/sifi/issue-candidates/$c/evidence", array_merge($this->payload($s),
            ['evidence_admitted' => true, 'qualification_state' => 'QUALIFIED', 'workflow_state' => 'ADMITTED']))
            ->assertSessionHasErrors(['evidence_admitted', 'qualification_state', 'workflow_state']);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('sifi_qualified_evidence'));
    }

    public function test_duplicate_is_rejected_without_audit_append(): void
    {
        $u = $this->user();
        [$c,$s] = $this->fixture($u);
        $this->create($u, $c, $s);
        $this->post("/sifi/issue-candidates/$c/evidence", $this->payload($s))->assertSessionHasErrors('signal_id');
        $this->assertDatabaseCount('sifi_evidence_drafts', 1);
        $this->assertDatabaseCount('sifi_evidence_draft_audit', 1);
    }

    public function test_edit_and_stale_revision(): void
    {
        $u = $this->user();
        [$c,$s] = $this->fixture($u);
        $id = $this->create($u, $c, $s);
        $update = ['revision' => 1, 'claim_text' => 'Hipotesis diperbarui', 'rationale' => 'Uji sumber primer',
            'known_gaps' => 'Independensi belum diketahui', 'relationship' => 'CONTEXT'];
        $this->patch("/sifi/evidence/$id", $update)->assertRedirect();
        $this->assertDatabaseHas('sifi_evidence_drafts', ['id' => $id, 'revision' => 2, 'claim_text' => 'Hipotesis diperbarui']);
        $this->patch("/sifi/evidence/$id", $update)->assertSessionHasErrors('revision');
        $this->assertDatabaseCount('sifi_evidence_draft_audit', 2);
    }

    public function test_reviewer_requires_other_verified_user(): void
    {
        $u = $this->user();
        [$c,$s] = $this->fixture($u);
        $id = $this->create($u, $c, $s);
        $this->post("/sifi/evidence/$id/request-review", ['revision' => 1, 'reviewer_email' => $u->email])
            ->assertSessionHasErrors('reviewer_email');
        $notVerified = $this->user(false);
        $this->post("/sifi/evidence/$id/request-review", ['revision' => 1, 'reviewer_email' => $notVerified->email])
            ->assertSessionHasErrors('reviewer_email');
        $this->assertDatabaseHas('sifi_evidence_drafts', ['id' => $id, 'workflow_state' => 'DRAFT', 'revision' => 1]);
    }

    public function test_assign_review_and_immutable_review_note(): void
    {
        $owner = $this->user();
        $reviewer = $this->user();
        [$c,$s] = $this->fixture($owner);
        $id = $this->create($owner, $c, $s);
        $this->post("/sifi/evidence/$id/request-review", ['revision' => 1, 'reviewer_email' => $reviewer->email])->assertRedirect();
        $this->assertDatabaseHas('sifi_evidence_drafts', ['id' => $id, 'reviewer_user_id' => $reviewer->id,
            'workflow_state' => 'REVIEW_REQUESTED', 'revision' => 2]);
        $this->actingAs($reviewer)->get('/sifi/evidence-reviews')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('sifi/evidence-review-queue')->has('evidence', 1)->etc());
        $this->post("/sifi/evidence/$id/review", ['revision' => 2, 'review_finding' => 'FOLLOWUP_REQUIRED',
            'review_notes' => 'Perlu konfirmasi dari dataset primer yang sah'])->assertRedirect();
        $this->assertDatabaseHas('sifi_evidence_drafts', ['id' => $id, 'workflow_state' => 'REVIEW_RECORDED', 'revision' => 3]);
        $this->assertDatabaseHas('sifi_evidence_review_notes', ['evidence_id' => $id, 'reviewer_user_id' => $reviewer->id,
            'review_finding' => 'FOLLOWUP_REQUIRED', 'evidence_revision' => 3]);
        $this->assertDatabaseCount('sifi_evidence_draft_audit', 3);
        $this->post("/sifi/evidence/$id/review", ['revision' => 3, 'review_finding' => 'INSUFFICIENT',
            'review_notes' => 'Catatan kedua tidak boleh menimpa'])->assertForbidden();
        $this->assertDatabaseCount('sifi_evidence_review_notes', 1);
        Http::assertNothingSent();
    }

    public function test_no_self_review_or_unauthorized_review_and_no_approval_state(): void
    {
        $owner = $this->user();
        $reviewer = $this->user();
        $outsider = $this->user();
        [$c,$s] = $this->fixture($owner);
        $id = $this->create($owner, $c, $s);
        $this->post("/sifi/evidence/$id/request-review", ['revision' => 1, 'reviewer_email' => $reviewer->email])->assertRedirect();
        $payload = ['revision' => 2, 'review_finding' => 'QUALIFIED', 'review_notes' => 'Auto approve tidak boleh'];
        $this->actingAs($reviewer)->post("/sifi/evidence/$id/review", $payload)->assertSessionHasErrors('review_finding');
        $this->actingAs($outsider)->post("/sifi/evidence/$id/review", ['revision' => 2, 'review_finding' => 'INSUFFICIENT', 'review_notes' => 'Akses tidak sah'])->assertForbidden();
        $this->actingAs($owner)->post("/sifi/evidence/$id/review", ['revision' => 2, 'review_finding' => 'INSUFFICIENT', 'review_notes' => 'Self review'])->assertForbidden();
        $this->assertDatabaseCount('sifi_evidence_review_notes', 0);
    }

    public function test_reviewer_queue_is_private(): void
    {
        $owner = $this->user();
        $reviewer = $this->user();
        $other = $this->user();
        [$c,$s] = $this->fixture($owner);
        $id = $this->create($owner, $c, $s);
        $this->post("/sifi/evidence/$id/request-review",['revision' => 1, 'reviewer_email' => $reviewer->email])->assertRedirect();
        $this->actingAs($other)->get('/sifi/evidence-reviews')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('sifi/evidence-review-queue')->has('evidence',0)->etc());
        $this->assertDatabaseCount('sifi_evidence_review_notes',0);
    }
}
