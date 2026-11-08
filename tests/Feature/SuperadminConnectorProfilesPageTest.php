<?php

namespace Tests\Feature;

use App\Filament\Superadmin\Pages\ConnectorProfiles;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class SuperadminConnectorProfilesPageTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedAdmin(): User
    {
        $actor = User::factory()->create(['email_verified_at' => now()]);
        $actor->assignRole(Role::findOrCreate('superadmin', 'web'));

        return $actor;
    }

    private function syntheticInput(): array
    {
        return [
            'provider_key' => 'synthetic-hub-provider',
            'label' => 'Synthetic Hub Provider',
            'kind' => 'SOCIAL',
            'endpoint' => 'https://example.test/api/v1',
            'auth_scheme' => 'BEARER',
            'credential' => 'SYNTHETIC-HUB-KEY-NOT-REAL',
            'rate_limit_per_minute' => 5,
        ];
    }

    public function test_disabled_flag_blocks_page(): void
    {
        config()->set('sifi_superadmin.enabled', false);
        $this->actingAs($this->verifiedAdmin())
            ->get('/superadmin/connector-profiles')
            ->assertForbidden();
    }

    public function test_regular_user_cannot_access_page(): void
    {
        config()->set('sifi_superadmin.enabled', true);
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get('/superadmin/connector-profiles')
            ->assertForbidden();
    }

    public function test_admin_can_render_page_and_create_disabled_draft(): void
    {
        config()->set('sifi_superadmin.enabled', true);
        $admin = $this->verifiedAdmin();
        $this->actingAs($admin);

        $this->get('/superadmin/connector-profiles')
            ->assertOk()
            ->assertSee('SHADOW / DRAFT ONLY');

        Filament::setCurrentPanel(Filament::getPanel('superadmin'));

        Livewire::test(ConnectorProfiles::class)
            ->callAction('createDraft', data: $this->syntheticInput())
            ->assertHasNoFormErrors();

        $this->assertSame(1, DB::table('sifi_connector_profiles')->count());
        $this->assertSame(1, DB::table('sifi_connector_audits')->count());
        $this->assertSame(0, (int) DB::table('sifi_connector_profiles')->value('enabled'));
        $this->assertSame(0, (int) DB::table('sifi_connector_profiles')->value('billing_authorized'));

        $this->get('/superadmin/connector-profiles')
            ->assertOk()
            ->assertSee('Synthetic Hub Provider')
            ->assertDontSee('SYNTHETIC-HUB-KEY-NOT-REAL');
    }

    public function test_forged_enable_and_billing_flags_are_ignored(): void
    {
        config()->set('sifi_superadmin.enabled', true);
        $this->actingAs($this->verifiedAdmin());
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));

        Livewire::test(ConnectorProfiles::class)
            ->callAction('createDraft', data: [
                ...$this->syntheticInput(),
                'enabled' => true,
                'billing_authorized' => true,
                'max_cost_micros' => 999999999,
            ])
            ->assertHasNoFormErrors();

        $profile = DB::table('sifi_connector_profiles')->first();
        $this->assertNotNull($profile);
        $this->assertSame(0, (int) $profile->enabled);
        $this->assertSame(0, (int) $profile->billing_authorized);
        $this->assertSame(0, (int) $profile->max_cost_micros);
    }
}