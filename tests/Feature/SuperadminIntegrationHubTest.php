<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class SuperadminIntegrationHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_superadmin_cannot_open_integration_hub(): void
    {
        config()->set('sifi_superadmin.enabled', false);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole(Role::findOrCreate('superadmin', 'web'));

        $this->actingAs($user)->get('/superadmin/integration-hub')->assertForbidden();
    }

    public function test_non_admin_cannot_open_integration_hub(): void
    {
        config()->set('sifi_superadmin.enabled', true);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/superadmin/integration-hub')->assertForbidden();
    }

    public function test_verified_superadmin_can_read_catalogue_without_connecting(): void
    {
        config()->set('sifi_superadmin.enabled', true);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole(Role::findOrCreate('superadmin', 'web'));

        $this->actingAs($user)
            ->get('/superadmin/integration-hub')
            ->assertOk()
            ->assertSee('Katalog AI')
            ->assertSee('CONNECTION CHECK: NOT RUN');
    }
}