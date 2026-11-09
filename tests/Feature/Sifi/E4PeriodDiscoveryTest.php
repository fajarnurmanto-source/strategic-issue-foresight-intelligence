<?php

namespace Tests\Feature\Sifi;

use App\Models\SifiMonitoringObject;
use App\Models\User;
use App\Services\Sifi\E4PeriodDiscovery;
use App\Services\Sifi\SearchProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class E4PeriodDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        config(['sifi_e4.live_enabled' => false]);
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
    }

    private function register(array $overrides = []): SifiMonitoringObject
    {
        $data = array_replace([
            'name' => '[SYNTHETIC] Sampah perkotaan',
            'description' => 'Objek sintetis untuk uji E4, bukan fakta.',
            'category' => 'Isu',
            'aliases' => ['limbah'],
            'keywords' => ['sampah perkotaan', 'pengelolaan sampah'],
            'exclusions' => [],
            'geography' => null,
            'objective' => 'Menelusuri publikasi berdasarkan periode.',
            'horizon' => '30 hari',
            'state' => 'CONFIGURED',
            'source_ids' => [],
        ], $overrides);
        $this->actingAs($this->owner)->post('/sifi/monitoring-objects', $data)
            ->assertSessionHasNoErrors()->assertRedirect('/sifi/monitoring-objects');
        return SifiMonitoringObject::query()->where('user_id', $this->owner->id)
            ->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
    }

    public function test_registration_precedes_period_and_planning_stays_read_only(): void
    {
        $object = $this->register();
        $today = now('Asia/Jakarta')->toDateString();
        $from = now('Asia/Jakarta')->subDays(7)->toDateString();

        $this->get('/sifi/discovery-period')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('sifi/e4-period-discovery')
                ->has('objects', 1)->where('plan', null)->where('production_admission', 'DENIED'));
        $this->get('/sifi/discovery-period?'.http_build_query([
            'object_id' => $object->id, 'start_date' => $from, 'end_date' => $today,
        ]))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('sifi/e4-period-discovery')
            ->where('plan.object_id', $object->id)
            ->where('plan.keyword_count', 2)
            ->where('plan.start_date', $from)
            ->where('plan.end_date', $today)
            ->where('plan.live_enabled', false)
            ->where('plan.eligible_dispatch_count', 0)
            ->has('plan.modules', 8)
            ->has('plan.sources', 28));
        $this->assertDatabaseCount('sifi_research_sessions', 0);
        Http::assertNothingSent();
    }

    public function test_foreign_object_and_unregistered_keywords_are_rejected(): void
    {
        $object = $this->register();
        $today = now('Asia/Jakarta')->toDateString();
        $other = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($other)->get('/sifi/discovery-period')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('objects', 0));
        $this->get('/sifi/discovery-period?'.http_build_query([
            'object_id' => $object->id, 'start_date' => $today, 'end_date' => $today,
        ]))->assertNotFound();

        $this->actingAs($this->owner);
        $this->register(['name' => 'Objek tanpa keyword', 'keywords' => []]);
        $noKeywords = SifiMonitoringObject::query()->where('user_id', $this->owner->id)
            ->where('name', 'Objek tanpa keyword')->firstOrFail();
        $this->get('/sifi/discovery-period?'.http_build_query([
            'object_id' => $noKeywords->id, 'start_date' => $today, 'end_date' => $today,
        ]))->assertSessionHasErrors('object_id');
        Http::assertNothingSent();
    }

    public function test_period_rejects_future_reverse_and_overlong_windows(): void
    {
        $object = $this->register();
        $service = app(E4PeriodDiscovery::class);
        $today = now('Asia/Jakarta')->toDateString();
        $past = now('Asia/Jakarta')->subDays(400)->toDateString();
        $future = now('Asia/Jakarta')->addDay()->toDateString();

        foreach ([[$past, $today], [$today, $past], [$today, $future]] as [$start, $end]) {
            try {
                $service->plan($this->owner, $object, $start, $end);
                $this->fail('Periode di luar kewenangan diterima.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertArrayHasKey('end_date', $exception->errors());
            }
        }
        Http::assertNothingSent();
    }

    public function test_live_post_is_denied_without_isolated_opt_in_or_provider_requests(): void
    {
        $object = $this->register();
        $today = now('Asia/Jakarta')->toDateString();
        $this->post('/sifi/discovery-period/collect', [
            'object_id' => $object->id,
            'object_revision' => $object->revision,
            'start_date' => $today,
            'end_date' => $today,
            'submission_key' => (string) \Illuminate\Support\Str::uuid(),
            'consent' => true,
        ])->assertSessionHasErrors('source');
        $this->assertDatabaseCount('sifi_research_sessions', 0);
        Http::assertNothingSent();
    }

    public function test_crossref_metadata_filter_uses_inclusive_start_and_exclusive_end(): void
    {
        $query = app(SearchProviderRegistry::class)->parameters(
            'CROSSREF', 'sampah perkotaan', 5,
            ['date_window' => ['start' => '2026-11-01', 'end' => '2026-11-09']]
        );
        $this->assertSame('from-pub-date:2026-11-01,until-pub-date:2026-11-08', $query['filter']);
        Http::assertNothingSent();
    }
}
