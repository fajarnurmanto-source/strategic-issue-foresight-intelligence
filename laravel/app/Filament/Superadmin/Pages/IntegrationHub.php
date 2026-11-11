<?php

namespace App\Filament\Superadmin\Pages;

use App\Models\User;
use App\Services\Sifi\Integration\ProviderInventory;
use Filament\Pages\Page;

final class IntegrationHub extends Page
{
    protected string $view = 'filament.superadmin.pages.integration-hub';

    protected static ?string $navigationLabel = 'Integration Hub';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return config('sifi_superadmin.enabled', false) === true
            && $user instanceof User
            && $user->hasVerifiedEmail()
            && $user->hasRole('superadmin', 'web');
    }

    /** @return array<string, mixed> */
    public function inventory(): array
    {
        return app(ProviderInventory::class)->snapshot();
    }
}