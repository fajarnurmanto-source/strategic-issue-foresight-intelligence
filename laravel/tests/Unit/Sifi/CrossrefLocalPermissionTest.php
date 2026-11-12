<?php

namespace Tests\Unit\Sifi;

use App\Services\Sifi\Integration\CrossrefLocalPermission;
use App\Services\Sifi\SearchProviderRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CrossrefLocalPermissionTest extends TestCase
{
    public function test_default_deny_keeps_previous_provider_review_expired(): void
    {
        config(['sifi_ih03b.crossref_metadata_enabled' => false]);
        $this->assertFalse(app(CrossrefLocalPermission::class)->eligible());
        $this->assertSame('2026-11-05', app(SearchProviderRegistry::class)->get('CROSSREF')['expires_on']);
    }

    public function test_isolated_opt_in_renews_only_crossref_metadata_review(): void
    {
        $this->travelTo(Carbon::parse('2026-11-08 12:00:00', 'Asia/Jakarta'));
        config([
            'sifi_ih03b.crossref_metadata_enabled' => true,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        $this->assertTrue(app(CrossrefLocalPermission::class)->eligible());
        $registry = app(SearchProviderRegistry::class);
        $this->assertSame('2026-11-14', $registry->get('CROSSREF')['expires_on']);
        $this->assertSame(1, $registry->get('CROSSREF')['rate_per_minute']);
        $this->assertSame('2026-11-05', $registry->get('WIKIPEDIA_ID')['expires_on']);
        $this->assertSame('2026-11-05', $registry->get('TAVILY_KEYLESS')['expires_on']);
    }

    public function test_non_sqlite_and_expired_review_cannot_open_connection(): void
    {
        config(['sifi_ih03b.crossref_metadata_enabled' => true, 'database.default' => 'mysql']);
        $this->assertFalse(app(CrossrefLocalPermission::class)->eligible());
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        $this->travelTo(Carbon::parse('2026-11-14 00:01:00', 'Asia/Jakarta'));
        $this->assertFalse(app(CrossrefLocalPermission::class)->eligible());
    }

    public function test_unusable_contact_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IH03B_REAL_CONTACT_EMAIL_REQUIRED');
        app(CrossrefLocalPermission::class)->assertContact('synthetic@localhost.test');
    }

    public function test_console_command_denies_when_switch_is_off_without_http(): void
    {
        Http::preventStrayRequests();
        config(['sifi_ih03b.crossref_metadata_enabled' => false]);
        $this->artisan('sifi:ih03b-crossref', ['--owner' => '1'])
            ->expectsOutputToContain('IH03B_CROSSREF_LOCAL_PERMISSION_DENIED')
            ->assertExitCode(1);
        Http::assertNothingSent();
    }
}
