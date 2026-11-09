<?php

namespace Tests\Unit\Sifi;

use App\Services\Sifi\Integration\GdeltLocalPermission;
use Tests\TestCase;

final class GdeltLocalPermissionTest extends TestCase
{
    public function test_default_deny_isolated_optin_and_expiry(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-09 12:00:00', 'Asia/Jakarta'));
        config([
            'sifi_ih03d.gdelt_news_enabled' => false,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        $permission = app(GdeltLocalPermission::class);
        $this->assertFalse($permission->eligible());
        config(['sifi_ih03d.gdelt_news_enabled' => true]);
        $this->assertTrue($permission->eligible());
        config(['database.connections.sqlite.database' => '/tmp/arbitrary.sqlite']);
        $this->assertFalse($permission->eligible());
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-15 12:00:00', 'Asia/Jakarta'));
        $this->assertFalse($permission->eligible());
    }

    public function test_real_contact_validation(): void
    {
        $permission = app(GdeltLocalPermission::class);
        $permission->assertContact('maintainer@example.org');
        $this->expectException(\InvalidArgumentException::class);
        $permission->assertContact('placeholder@example.invalid');
    }
}
