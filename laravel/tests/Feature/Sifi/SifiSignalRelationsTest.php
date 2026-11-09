<?php

namespace Tests\Feature\Sifi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class SifiSignalRelationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('sifi_aq01.base_url','http://127.0.0.1:8766');
        config()->set('sifi_aq01.token', str_repeat('a', 40));
    }

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function safePayload(): array
    {
        return [
            'schema_version'=>'sifi.signal.relations.v1',
            'mode'=>'OFFLINE_FIXTURE_ONLY', 'kind'=>'SIMILARITY_TRIAGE_ONLY',
            'status'=>'BOUNDED_COMPLETE',
            'total_in_projection'=>1,'analyzed_signals'=>1,'distinct_source_ids'=>1,
            'source_independence_verified'=>false,'evidence_admission'=>'NOT_EVALUATED',
            'claims_verified'=>false,'issues_created'=>false,'live_acquisition'=>false,
            'signals'=>[[
                'signal_id'=>str_repeat('a',64), 'source_id'=>'SRC-A',
                'title'=>'Synthetic publication','signal_version'=>1,
            ]],
            'relationships'=>[],'exact_fingerprint_groups'=>[],
            'human_checks'=>['Inspect original source'],
        ];
    }

    public function test_guests_are_redirected(): void
    {
        $this->get('/sifi/signal-relations')->assertRedirect('/login');
    }

    public function test_online_offline_projection_is_not_claim_verification(): void
    {
        Http::fake(['http://127.0.0.1:8766/*'=>Http::response($this->safePayload(),200)]);
        $this->actingAs($this->user())->get('/sifi/signal-relations')->assertOk()
            ->assertInertia(fn(AssertableInertia $page)=>$page
                ->component('sifi/signal-relations')
                ->where('apiStatus','OFFLINE_FIXTURE_ONLY')
                ->where('projection.claims_verified',false)
                ->where('projection.source_independence_verified',false)
                ->where('projection.evidence_admission','NOT_EVALUATED')
                ->etc());
        Http::assertSentCount(1);
    }

    public function test_untrusted_python_response_is_not_displayed(): void
    {
        $payload=$this->safePayload();$payload['claims_verified']=true;
        Http::fake(['http://127.0.0.1:8766/*'=>Http::response($payload,200)]);
        $this->actingAs($this->user())->get('/sifi/signal-relations')->assertOk()
            ->assertInertia(fn(AssertableInertia $page)=>$page
                ->component('sifi/signal-relations')
                ->where('apiStatus','INVALID_PROJECTION')
                ->where('projection',null)
                ->etc());
    }

    public function test_token_is_never_sent_to_external_host(): void
    {
        config()->set('sifi_aq01.base_url','https://external.example');
        Http::fake();
        $this->actingAs($this->user())->get('/sifi/signal-relations')->assertOk()
            ->assertInertia(fn(AssertableInertia $page)=>$page
                ->where('apiStatus','API_UNAVAILABLE')
                ->where('projection',null)
                ->etc());
        Http::assertNothingSent();
    }

    public function test_no_python_api_does_not_fake_analytics(): void
    {
        Http::fake(['http://127.0.0.1:8766/*'=>Http::response(['error'=>'unavailable'],503)]);
        $this->actingAs($this->user())->get('/sifi/signal-relations')->assertOk()
            ->assertInertia(fn(AssertableInertia $page)=>$page
                ->where('apiStatus','API_UNAVAILABLE')
                ->where('projection',null)
                ->etc());
    }
}
