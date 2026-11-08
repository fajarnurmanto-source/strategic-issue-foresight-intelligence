<?php

namespace Tests\Feature\Sifi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class SifiDashboardExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function candidate(User $user, string $title): void
    {
        $investigation = (string) Str::uuid();
        DB::table('sifi_investigations')->insert([
            'id' => $investigation, 'user_id' => $user->id,
            'title' => $title, 'review_state' => 'DRAFT', 'revision' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sifi_issue_candidates')->insert([
            'id' => (string) Str::uuid(), 'investigation_id' => $investigation,
            'user_id' => $user->id, 'title' => $title,
            'review_state' => 'DRAFT', 'revision' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_verified_user_sees_empty_owned_dashboard(): void
    {
        $this->actingAs($this->user())->get('/dashboard')->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('dashboard')
                ->where('metrics.candidates', 0)
                ->where('metrics.investigations', 0)
                ->where('metrics.evidence_drafts', 0)
                ->where('mode', 'OFFLINE_FIXTURE_ONLY')
                ->where('evidence_state', 'NOT_EVALUATED')
                ->where('production_authorized', false)
                ->has('cases', 0)
                ->etc()
        );
    }

    public function test_dashboard_is_scoped_to_authenticated_owner(): void
    {
        $me = $this->user();
        $this->candidate($me, 'Own issue');
        $this->candidate($this->user(), 'Other user private');
        $this->actingAs($me)->get('/dashboard')->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('dashboard')
                ->where('metrics.candidates', 1)
                ->where('metrics.investigations', 1)
                ->has('cases', 1)
                ->where('cases.0.title', 'Own issue')
                ->etc()
        );
    }

    public function test_dashboard_does_not_write_to_the_data_model(): void
    {
        $me = $this->user();
        $this->candidate($me, 'Unverified');
        $this->actingAs($me)->get('/dashboard')->assertOk();
        $this->assertDatabaseCount('sifi_issue_candidates', 1);
        $this->assertDatabaseCount('sifi_issue_case_drafts', 0);
    }
}
