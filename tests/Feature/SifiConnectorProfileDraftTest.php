<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Sifi\Integration\ConnectorProfileDrafts;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class SifiConnectorProfileDraftTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole(Role::findOrCreate('superadmin', 'web'));
        return $user;
    }

    private function syntheticInput(): array
    {
        return [
            'provider_key' => 'synthetic-social-api',
            'label' => 'Synthetic Social Connector',
            'kind' => 'SOCIAL',
            'endpoint' => 'https://example.test/api/v1',
            'auth_scheme' => 'BEARER',
            'credential' => 'SYNTHETIC-SECRET-NOT-A-REAL-KEY',
            'rate_limit_per_minute' => 10,
        ];
    }

    public function test_disabled_superadmin_cannot_create_drafts(): void
    {
        config()->set('sifi_superadmin.enabled', false);
        $this->expectException(AuthorizationException::class);
        app(ConnectorProfileDrafts::class)->create($this->admin(), $this->syntheticInput());
    }

    public function test_non_admin_cannot_create_drafts(): void
    {
        config()->set('sifi_superadmin.enabled', true);
        $actor = User::factory()->create(['email_verified_at' => now()]);
        $this->expectException(AuthorizationException::class);
        app(ConnectorProfileDrafts::class)->create($actor, $this->syntheticInput());
    }

    public function test_unverified_admin_cannot_create_drafts(): void
    {
        config()->set('sifi_superadmin.enabled', true);
        $actor = $this->admin();
        $actor->email_verified_at = null;
        $actor->save();
        $this->expectException(AuthorizationException::class);
        app(ConnectorProfileDrafts::class)->create($actor, $this->syntheticInput());
    }

    public function test_draft_secret_is_encrypted_hidden_and_never_enabled(): void
    {
        config()->set('sifi_superadmin.enabled', true);
        $admin = $this->admin();
        $service = app(ConnectorProfileDrafts::class);
        $profile = $service->create($admin, $this->syntheticInput());

        $this->assertFalse($profile->enabled);
        $this->assertFalse($profile->billing_authorized);
        $this->assertSame(0, $profile->max_cost_micros);
        $this->assertSame('UNASSESSED', $profile->rights_status);
        $this->assertSame('NOT_TESTED', $profile->connection_status);
        $this->assertNotSame('SYNTHETIC-SECRET-NOT-A-REAL-KEY',
            $profile->getRawOriginal('credential_ciphertext'));
        $this->assertSame('SYNTHETIC-SECRET-NOT-A-REAL-KEY', $profile->credential_ciphertext);
        $this->assertArrayNotHasKey('credential_ciphertext', $profile->toArray());

        $list = $service->publicList($admin);
        $this->assertCount(1, $list);
        $this->assertTrue($list[0]['credential_saved']);
        $this->assertArrayNotHasKey('credential_ciphertext', $list[0]);
        $this->assertArrayNotHasKey('credential', $list[0]);

        $audits = DB::table('sifi_connector_audits')->get();
        $this->assertCount(1, $audits);
        $this->assertSame('PROFILE_DRAFT_CREATED', $audits[0]->event_code);
        $this->assertStringNotContainsString('SYNTHETIC-SECRET-NOT-A-REAL-KEY',
            json_encode($audits, JSON_THROW_ON_ERROR));
    }

    public function test_non_https_remote_endpoint_is_rejected(): void
    {
        config()->set('sifi_superadmin.enabled', true);
        $input = $this->syntheticInput();
        $input['endpoint'] = 'http://169.254.169.254/latest/meta-data';
        $this->expectException(\InvalidArgumentException::class);
        app(ConnectorProfileDrafts::class)->create($this->admin(), $input);
    }

    public function test_endpoint_cannot_embed_credentials_or_queries(): void
    {
        config()->set('sifi_superadmin.enabled', true);
        $input = $this->syntheticInput();
        $input['endpoint'] = 'https://user:secret@example.test/api?token=secret';
        $this->expectException(\InvalidArgumentException::class);
        app(ConnectorProfileDrafts::class)->create($this->admin(), $input);
    }

    public function test_readonly_readiness_lists_all_fail_closed_dispatch_blockers_without_network_or_writes(): void
    {
        config()->set('sifi_superadmin.enabled', true);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $admin = $this->admin();
        $service = app(ConnectorProfileDrafts::class);
        $service->create($admin, $this->syntheticInput());

        $result = $service->publicList($admin);
        $this->assertCount(1, $result);
        $this->assertSame('NOT_ADMITTED', $result[0]['readiness']['state']);
        $this->assertFalse($result[0]['readiness']['can_dispatch']);
        $this->assertSame('NOT_RUN', $result[0]['readiness']['connection_probe']);
        $codes = array_column($result[0]['readiness']['reasons'], 'code');
        $this->assertContains('NO_DISPATCH_CONTRACT', $codes);
        $this->assertContains('CONFIGURATION_DISABLED', $codes);
        $this->assertContains('RIGHTS_NOT_APPROVED', $codes);
        $this->assertContains('CONNECTION_NOT_VERIFIED', $codes);
        $this->assertNotContains('CREDENTIAL_REFERENCE_MISSING', $codes);
        $this->assertArrayNotHasKey('credential', $result[0]);
        $this->assertArrayNotHasKey('credential_ciphertext', $result[0]);
        $this->assertDatabaseCount('sifi_connector_audits', 1);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_missing_credential_and_unset_rate_are_visible_as_diagnostic_reasons(): void
    {
        config()->set('sifi_superadmin.enabled', true);
        $admin = $this->admin();
        $input = $this->syntheticInput();
        unset($input['credential']);
        $input['rate_limit_per_minute'] = 0;
        app(ConnectorProfileDrafts::class)->create($admin, $input);

        $profile = app(ConnectorProfileDrafts::class)->publicList($admin)[0];
        $codes = array_column($profile['readiness']['reasons'], 'code');
        $this->assertContains('CREDENTIAL_REFERENCE_MISSING', $codes);
        $this->assertContains('RATE_LIMIT_UNSET', $codes);
        $this->assertFalse($profile['credential_saved']);
        $this->assertFalse($profile['readiness']['can_dispatch']);
    }

}