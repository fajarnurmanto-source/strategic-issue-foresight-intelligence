<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperadminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_is_denied_while_feature_is_disabled(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::findOrCreate('superadmin', 'web');
        $user->assignRole($role);

        $this->assertFalse(
            $user->canAccessPanel(Filament::getPanel('superadmin'))
        );

        $this->actingAs($user)
            ->get('/superadmin')
            ->assertForbidden();
    }

    public function test_only_verified_superadmin_is_authorized_when_enabled(): void
    {
        config()->set('sifi_superadmin.enabled', true);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $panel = Filament::getPanel('superadmin');

        $this->assertFalse($user->canAccessPanel($panel));

        $role = Role::findOrCreate('superadmin', 'web');
        $user->assignRole($role);

        $this->assertTrue($user->canAccessPanel($panel));

        $this->actingAs($user)
            ->get('/superadmin')
            ->assertOk();

        $user->email_verified_at = null;
        $user->save();

        $this->assertFalse($user->canAccessPanel($panel));
    }
}
