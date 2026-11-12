<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\DeterministicResearchPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ResearchPlanningTest extends TestCase
{
    use RefreshDatabase;

    private User $u;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->u = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($this->u);
    }

    private function object(array $v = []): SifiMonitoringObject
    {
        return SifiMonitoringObject::create(array_replace(['id' => (string) Str::uuid(), 'user_id' => $this->u->id, 'name' => '[SYNTHETIC] Industri kendaraan listrik', 'description' => 'Objek pengujian planner lokal.', 'category' => 'Industri', 'aliases_json' => ['mobil listrik', 'EV'], 'keywords_json' => ['baterai', 'pengisian'], 'exclusions_json' => ['iklan', 'lowongan'], 'geography' => 'Indonesia', 'objective' => 'Mengenali perkembangan kendaraan listrik.', 'horizon' => '90 hari', 'state' => 'CONFIGURED', 'revision' => 1], $v));
    }

    private function url($o): string
    {
        return '/sifi/monitoring-objects/'.$o->id.'/research';
    }

    private function data(array $v = []): array
    {
        return array_replace(['question' => 'Sumber apa yang relevan tentang baterai kendaraan listrik?', 'object_revision' => 1, 'query_limit' => 4, 'result_limit' => 12, 'language' => null, 'source_type' => null, 'time_window' => '90 hari'], $v);
    }

    public function test_deterministic_plan_is_saved_with_zero_external_budget_and_provenance(): void
    {
        $o = $this->object();
        $this->post($this->url($o), $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $s = DB::table('sifi_research_sessions')->first();
        $p = json_decode($s->plan_json, true);
        $r = json_decode($s->results_json, true);
        $this->assertSame('ae14-plan-2', $p['version']);
        $this->assertSame('LOCAL_SYNTHETIC_CATALOG', $p['scope']['provider']);
        $this->assertSame('OFF', $p['provenance']['network']);
        $this->assertSame(0, $p['limits']['request_limit']);
        $this->assertLessThanOrEqual(4, count($p['queries']));
        $this->assertLessThanOrEqual(12, $r['result_count']);
        $this->assertSame(0, $r['requests_used']);
        $this->assertSame(0, $r['retrieved_documents']);
        $this->assertDatabaseCount('sifi_research_session_audit', 1);
        $this->assertDatabaseCount('sifi_claim_drafts', 0);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
        $this->assertDatabaseCount('jobs', 0);
        Http::assertNothingSent();
    }

    public function test_alias_exclusions_limits_no_result_and_follow_up_are_explicit(): void
    {
        $o = $this->object();
        $this->post($this->url($o), $this->data(['question' => 'EV lowongan reputasi', 'query_limit' => 2, 'result_limit' => 1]))->assertSessionHasNoErrors();
        $first = DB::table('sifi_research_sessions')->first();
        $plan = json_decode($first->plan_json, true);
        $this->assertContains('EV', $plan['aliases']);
        $this->assertContains('lowongan', $plan['exclusions']);
        $this->assertLessThanOrEqual(2, count($plan['queries']));
        $this->post($this->url($o), $this->data(['question' => 'zzztidakadapadakatalog', 'parent_session_id' => $first->id]))->assertSessionHasNoErrors();
        $last = DB::table('sifi_research_sessions')->whereNotNull('parent_id')->first();
        $this->assertSame('NO_LOCAL_MATCH', $last->stopping_reason);
        $this->assertSame($first->id, $last->parent_id);
        $this->assertStringContainsString('tidak membuktikan', json_decode($last->results_json, true)['uncertainty']);
    }

    public function test_foreign_owner_object_and_history_are_denied_without_leakage(): void
    {
        $o = $this->object();
        $this->post($this->url($o), $this->data())->assertSessionHasNoErrors();
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other);
        $this->get($this->url($o))->assertForbidden();
        $this->post($this->url($o), $this->data())->assertForbidden();
        $own = $this->object(['user_id' => $other->id]);
        $this->get($this->url($own))->assertInertia(fn (Assert $p) => $p->has('history', 0));
        $this->post($this->url($own), $this->data(['parent_session_id' => DB::table('sifi_research_sessions')->value('id')]))->assertNotFound();
    }

    public function test_stale_paused_and_forged_authority_inputs_fail_closed(): void
    {
        $o = $this->object();
        $o->update(['revision' => 2]);
        $this->post($this->url($o), $this->data())->assertSessionHasErrors('object_revision');
        $o->update(['state' => 'PAUSED']);
        $this->post($this->url($o), $this->data(['object_revision' => 2]))->assertSessionHasErrors('object_revision');
        foreach (['provider' => 'WEB', 'grant' => 'x', 'receipt' => (string) Str::uuid(), 'ai' => true, 'request_limit' => 1, 'evidence' => true] as $k => $v) {
            $this->post($this->url($o), $this->data(['object_revision' => 2, $k => $v]))->assertSessionHasErrors($k);
        }$this->assertDatabaseCount('sifi_research_sessions', 0);
        Http::assertNothingSent();
    }

    public function test_bounds_validation_readonly_get_and_expired_history_redaction(): void
    {
        $o = $this->object();
        foreach ([['query_limit' => 6], ['result_limit' => 21], ['question' => 'short']] as $bad) {
            $this->post($this->url($o), $this->data($bad))->assertSessionHasErrors();
        }$this->get($this->url($o))->assertOk();
        $this->assertDatabaseCount('sifi_research_sessions', 0);
        $this->post($this->url($o), $this->data())->assertSessionHasNoErrors();
        $this->travel(31)->days();
        $this->get($this->url($o))->assertInertia(fn (Assert $p) => $p->where('history.0.expired', true)->where('history.0.plan', null)->where('history.0.results', null));
        $this->travelBack();
    }

    public function test_same_input_produces_same_plan_except_persistence_metadata(): void
    {
        $o = $this->object();
        $d = $this->data();
        $this->post($this->url($o), $d);
        $this->post($this->url($o), $d);
        $rows = DB::table('sifi_research_sessions')->orderBy('created_at')->get();
        $this->assertSame($rows[0]->plan_json, $rows[1]->plan_json);
        $this->assertSame($rows[0]->results_json, $rows[1]->results_json);
    }

    public function test_unrelated_or_excluded_only_questions_never_fall_back_to_object_hits(): void
    {
        $o = $this->object();
        foreach (['zzztidakadapadakatalog', 'lowongan iklan'] as $q) {
            $this->post($this->url($o), $this->data(['question' => $q]))->assertSessionHasNoErrors();
            $row = DB::table('sifi_research_sessions')->where('question', $q)->first();
            $this->assertSame('NO_LOCAL_MATCH', $row->stopping_reason);
            $this->assertSame(0, json_decode($row->results_json, true)['result_count']);
        }
    }

    public function test_three_object_questions_return_expected_catalog_identity(): void
    {
        $cases = [['[SYNTHETIC] Yogyakarta', ['Kota Pelajar'], 'pelayanan publik', 'yog-open'], ['[SYNTHETIC] NusaVolt', ['NV Studio'], 'reputasi NusaVolt', 'nusavolt-news'], ['[SYNTHETIC] Kendaraan listrik', ['EV'], 'baterai listrik', 'ev-api']];
        foreach ($cases as [$name,$aliases,$question,$key]) {
            $o = $this->object(['name' => $name, 'aliases_json' => $aliases]);
            $this->post($this->url($o), $this->data(['question' => $question]))->assertSessionHasNoErrors();
            $r = json_decode(DB::table('sifi_research_sessions')->where('object_id', $o->id)->value('results_json'), true);
            $this->assertContains($key, array_column($r['entries'], 'key'));
            $this->assertSame('NOT_VERIFIED', $r['origin_independence']);
            $ids = array_column($r['entries'], 'candidate_identity_sha256');
            $this->assertSame(count($ids), count(array_unique($ids)));
        }
    }

    public function test_expired_parent_is_rejected_and_question_is_redacted(): void
    {
        $o = $this->object();
        $this->post($this->url($o), $this->data());
        $id = DB::table('sifi_research_sessions')->value('id');
        $this->travel(31)->days();
        $this->get($this->url($o))->assertInertia(fn (Assert $p) => $p->where('history.0.question', null));
        $this->post($this->url($o), $this->data(['parent_session_id' => $id]))->assertSessionHasErrors('parent_session_id');
        $this->assertDatabaseCount('sifi_research_sessions', 1);
        $this->travelBack();
    }

    public function test_catalog_integrity_and_time_budget_fail_closed(): void
    {
        $o = $this->object();
        $p = app(DeterministicResearchPlanner::class);
        $limits = ['query_limit' => 5, 'result_limit' => 2, 'language' => null, 'source_type' => null, 'time_window' => '2026', 'time_limit_ms' => 0];
        $plan = $p->plan($o, 'baterai kendaraan listrik', $limits);
        $r = $p->execute($o, $plan);
        $this->assertSame('TIME_LIMIT_REACHED', $r['stopping_reason']);
        $this->assertSame(0, $r['queries_used']);
        $this->assertFalse($r['time_filter_applied']);
        $this->assertSame('NOT_APPLIED_UNKNOWN_PUBLICATION_DATES', $plan['scope']['time_filter_status']);
        $plan['provenance']['catalog_sha256'] = str_repeat('0', 64);
        $this->expectException(ValidationException::class);
        $p->execute($o, $plan);
    }

    public function test_auth_verified_csrf_and_throttle_are_enforced(): void
    {
        $o = $this->object();
        $this->app['auth']->forgetGuards();
        $this->get($this->url($o))->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['email_verified_at' => null]))->get($this->url($o))->assertRedirect('/email/verify');
        $this->actingAs($this->u);
        $this->app->instance('env', 'local');
        $this->post($this->url($o), $this->data())->assertStatus(419);
        $this->app->instance('env', 'testing');
        for ($i = 0; $i < 22; $i++) {
            $r = $this->post($this->url($o), $this->data());
        }$r->assertStatus(429);
    }

    public function test_cross_object_parent_and_history_are_isolated(): void
    {
        $a = $this->object();
        $b = $this->object();
        $this->post($this->url($a), $this->data());
        $id = DB::table('sifi_research_sessions')->value('id');
        $this->get($this->url($b))->assertInertia(fn (Assert $p) => $p->has('history', 0));
        $this->post($this->url($b), $this->data(['parent_session_id' => $id]))->assertNotFound();
        $this->assertDatabaseCount('sifi_research_sessions', 1);
    }

    public function test_nonempty_migration_rollback_refuses_data_loss(): void
    {
        $o = $this->object();
        $this->post($this->url($o),$this->data());
        $m = require database_path('migrations/2026_10_27_000014_create_sifi_research_sessions.php');
        try {
            $m->down();
            $this->fail('Dropped nonempty research tables');
        } catch (\RuntimeException $e) {
            $this->assertSame('REFUSE_NONEMPTY_RESEARCH_ROLLBACK',$e->getMessage());
        }$this->assertDatabaseCount('sifi_research_sessions',1);
    }
}
