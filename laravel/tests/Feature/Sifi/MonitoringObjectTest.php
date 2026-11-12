<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\SifiSourceDraft;
use App\Models\User;
use App\Services\Sifi\WorldBankGdpSourceAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class MonitoringObjectTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-26T12:00:00Z'));
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['name' => '[SYNTHETIC] Objek universal', 'description' => 'Konfigurasi uji otomatis, bukan fakta atau hasil pemantauan.', 'category' => 'Kategori bebas dari pengguna', 'aliases' => ['Alias A'], 'keywords' => ['perubahan'], 'exclusions' => ['iklan'], 'geography' => null, 'objective' => 'Memeriksa informasi dan keterbatasan secara terstruktur.', 'horizon' => '90 hari', 'state' => 'CONFIGURED', 'source_ids' => []], $overrides);
    }

    private function create(array $v = []): SifiMonitoringObject
    {
        $this->actingAs($this->owner)->post('/sifi/monitoring-objects', $this->data($v))->assertSessionHasNoErrors()->assertRedirect('/sifi/monitoring-objects');

        return SifiMonitoringObject::where('user_id', $this->owner->id)->latest('created_at')->orderByDesc('id')->firstOrFail();
    }

    private function source(User $u, ?string $url = null): SifiSourceDraft
    {
        return SifiSourceDraft::create(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'name' => 'Synthetic source registration reference', 'source_type' => 'OPEN_DATA', 'homepage_url' => $url ?? WorldBankGdpSourceAdapter::DOCUMENTATION, 'revision' => 1]);
    }

    public function test_three_objects_and_open_categories_need_no_geography_or_custom_code(): void
    {
        foreach ([['name' => '[SYNTHETIC] Isu publik Kota Yogyakarta', 'category' => 'Isu publik', 'geography' => 'Kota Yogyakarta'], ['name' => '[SYNTHETIC] Merek fiktif NusaVolt', 'category' => 'Perusahaan / merek'], ['name' => '[SYNTHETIC] Industri kendaraan listrik', 'category' => 'Industri energi dan transportasi']] as $v) {
            $this->create($v);
        }
        $this->assertDatabaseCount('sifi_monitoring_objects', 3);
        $this->assertDatabaseCount('sifi_monitoring_object_audit', 3);
        $this->assertSame(2, SifiMonitoringObject::whereNull('geography')->count());
        $this->get('/sifi/monitoring-objects')->assertOk()->assertInertia(fn (Assert $p) => $p->has('objects', 3)->where('mode', 'CONFIGURATION_ONLY_COLLECTOR_OFF'));
        Http::assertNothingSent();
        $this->assertDatabaseCount('sifi_issue_candidates', 0);
        $this->assertDatabaseCount('sifi_evidence_drafts', 0);
    }

    public function test_owned_reviewed_source_can_be_shared_but_unreviewed_source_cannot(): void
    {
        $s = $this->source($this->owner);
        $this->create(['name' => '[SYNTHETIC] A', 'source_ids' => [$s->id]]);
        $this->create(['name' => '[SYNTHETIC] B', 'source_ids' => [$s->id]]);
        $this->assertDatabaseCount('sifi_monitoring_object_sources', 2);
        $bad = $this->source($this->owner, 'https://unreviewed.example.test/data');
        $this->post('/sifi/monitoring-objects', $this->data(['source_ids' => [$bad->id]]))->assertSessionHasErrors('source_ids');
        $this->assertDatabaseCount('sifi_monitoring_objects', 2);
        $this->assertSame('UNASSESSED', $s->fresh()->rights_state);
        $this->assertSame('OFF', $s->fresh()->collector_state);
        Http::assertNothingSent();
    }

    public function test_foreign_objects_sources_and_context_are_denied_without_leaks(): void
    {
        $o = $this->create();
        $s = $this->source($this->owner);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other);
        $this->get('/sifi/monitoring-objects')->assertOk()->assertInertia(fn (Assert $p) => $p->has('objects', 0)->has('sourceChoices', 0));
        $this->patch('/sifi/monitoring-objects/'.$o->id, $this->data(['revision' => 1]))->assertForbidden();
        $this->get('/sifi/workbench?object='.$o->id)->assertForbidden();
        $this->post('/sifi/monitoring-objects', $this->data(['source_ids' => [$s->id]]))->assertSessionHasErrors('source_ids');
        $this->assertDatabaseCount('sifi_monitoring_objects', 1);
    }

    public function test_revision_conflict_noop_and_append_only_history(): void
    {
        $o = $this->create();
        $url = '/sifi/monitoring-objects/'.$o->id;
        $this->patch($url, $this->data(['revision' => 1]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sifi_monitoring_object_audit', 1);
        $this->patch($url, $this->data(['revision' => 1, 'name' => '[SYNTHETIC] Diperbarui']))->assertSessionHasNoErrors();
        $this->assertSame(2, $o->fresh()->revision);
        $this->patch($url, $this->data(['revision' => 1, 'name' => 'Stale overwrite']))->assertSessionHasErrors('revision');
        $this->assertSame('[SYNTHETIC] Diperbarui', $o->fresh()->name);
        $audit = DB::table('sifi_monitoring_object_audit')->where('object_id', $o->id)->orderBy('revision')->get();
        $this->assertCount(2, $audit);
        $this->assertStringContainsString('[SYNTHETIC] Objek universal', $audit[0]->snapshot_json);
    }

    public function test_pause_archive_and_explicit_resume_only_change_configuration(): void
    {
        $o = $this->create();
        foreach (['PAUSED', 'ARCHIVED', 'CONFIGURED'] as $i => $state) {
            $this->patch('/sifi/monitoring-objects/'.$o->id, $this->data(['revision' => $i + 1, 'state' => $state]))->assertSessionHasNoErrors();
            $this->assertSame($state, $o->fresh()->state);
        }
        $this->assertSame(4, $o->fresh()->revision);
        $this->assertDatabaseCount('jobs', 0);
        Http::assertNothingSent();
    }

    public function test_object_workbench_context_does_not_launder_global_candidates_or_receipts(): void
    {
        $o = $this->create(['category' => 'Komet / penelitian', 'keywords' => ['komet'], 'exclusions' => ['reklame']]);
        $this->get('/sifi/workbench?object='.$o->id)->assertOk()->assertInertia(fn (Assert $p) => $p->where('monitoringObject.id', $o->id)->where('monitoringObject.geography', null)->where('focus', null)->has('cases', 0)->where('total', 0)->missing('acquisitionWorkspace')->where('production_authorized', false));
        $this->get('/sifi/workbench?object='.$o->id.'&candidate='.(string) Str::uuid())->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_validation_rejects_forced_authority_and_duplicate_or_unbounded_terms(): void
    {
        foreach ([['user_id' => 999], ['collector_state' => 'ON'], ['grant' => 'forge'], ['schedule' => 'every minute'], ['keywords' => ['same', 'SAME']], ['exclusions' => array_fill(0, 31, 'x')], ['category' => ''], ['aliases' => [str_repeat('a', 101)]]] as $bad) {
            $this->actingAs($this->owner)->post('/sifi/monitoring-objects', $this->data($bad))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('sifi_monitoring_objects', 0);
        Http::assertNothingSent();
    }

    public function test_expired_rights_fail_closed_on_update_without_mutating_existing_object(): void
    {
        $s = $this->source($this->owner);
        $o = $this->create(['source_ids' => [$s->id]]);
        $this->travel(31)->days();
        $this->patch('/sifi/monitoring-objects/'.$o->id, $this->data(['revision' => 1, 'source_ids' => [$s->id], 'name' => 'Must not persist']))->assertSessionHasErrors('source_ids');
        $this->assertSame(1, $o->fresh()->revision);
        $this->patch('/sifi/monitoring-objects/'.$o->id, $this->data(['revision' => 1, 'source_ids' => [], 'state' => 'PAUSED']))->assertSessionHasNoErrors();
        $this->assertSame('PAUSED', $o->fresh()->state);
        $this->travelBack();
    }

    public function test_auth_verified_and_csrf_are_required(): void
    {
        $this->get('/sifi/monitoring-objects')->assertRedirect('/login');
        $this->post('/sifi/monitoring-objects', $this->data())->assertRedirect('/login');
        $u = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($u)->get('/sifi/monitoring-objects')->assertRedirect('/email/verify');
        $this->actingAs($this->owner);
        $this->app->instance('env', 'local');
        $this->post('/sifi/monitoring-objects', $this->data())->assertStatus(419);
        $this->app->instance('env', 'testing');
    }

    public function test_readonly_lists_do_not_update_revisions_or_issue_pipeline(): void
    {
        $o = $this->create();
        $before = $o->fresh()->toArray();
        $this->get('/sifi/monitoring-objects')->assertOk();
        $this->get('/sifi/workbench?object='.$o->id)->assertOk();
        $this->assertSame($before,$o->fresh()->toArray());
        $this->assertDatabaseCount('sifi_monitoring_object_audit',1);
        $this->assertDatabaseCount('sifi_issue_candidates',0);
        $this->assertDatabaseCount('sifi_claim_drafts',0);
        Http::assertNothingSent();
    }
}
