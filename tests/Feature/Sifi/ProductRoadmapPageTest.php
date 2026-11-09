<?php

namespace Tests\Feature\Sifi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ProductRoadmapPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    public function test_guest_cannot_read_in_app_roadmap(): void
    {
        $this->get('/sifi/product-roadmap')->assertRedirect('/login');
    }

    public function test_verified_user_sees_canonical_roadmap_without_network_or_db_mutation(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/sifi/product-roadmap')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('sifi/product-roadmap')
                ->where('version', 'v1.2')
                ->where('source_state', 'AVAILABLE')
                ->where('network_requests', 0)
                ->where('database_writes', 0)
                ->where('production_admission', 'DENIED')
                ->has('journey', 11)
                ->has('milestones', 9)
                ->etc()
        );
        Http::assertNothingSent();
    }
}
