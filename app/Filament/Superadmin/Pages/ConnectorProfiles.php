<?php

namespace App\Filament\Superadmin\Pages;

use App\Models\User;
use App\Services\Sifi\Integration\ConnectorProfileDrafts;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Schema;

/** Draft management only; no provider activation, HTTP calls or billing controls. */
final class ConnectorProfiles extends Page
{
    protected string $view = 'filament.superadmin.pages.connector-profiles';

    protected static ?string $navigationLabel = 'Profil Koneksi';

    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return config('sifi_superadmin.enabled', false) === true
            && $actor instanceof User
            && $actor->hasVerifiedEmail()
            && $actor->hasRole('superadmin', 'web');
    }

    public function databaseReady(): bool
    {
        return Schema::hasTable('sifi_connector_profiles')
            && Schema::hasTable('sifi_connector_audits');
    }

    /** Safe presentation data only; secrets never leave the service boundary. */
    public function profiles(): array
    {
        if (! static::canAccess() || ! $this->databaseReady()) {
            return [];
        }

        $actor = auth()->user();
        if (! $actor instanceof User) {
            return [];
        }

        return app(ConnectorProfileDrafts::class)->publicList($actor);
    }

    public function createDraftAction(): Action
    {
        return Action::make('createDraft')
            ->label('Tambah draft provider')
            ->modalHeading('Konfigurasi provider (draft)')
            ->modalDescription('Hanya menyimpan konfigurasi. Tidak melakukan koneksi API, aktivasi, atau otorisasi biaya.')
            ->visible(fn (): bool => static::canAccess() && $this->databaseReady())
            ->schema([
                TextInput::make('provider_key')
                    ->label('Kode provider')
                    ->required()
                    ->maxLength(64)
                    ->helperText('Huruf kecil, angka, tanda hubung atau garis bawah.'),
                TextInput::make('label')
                    ->label('Nama koneksi')
                    ->required()
                    ->maxLength(100),
                Select::make('kind')
                    ->label('Kategori sumber')
                    ->options([
                        'AI' => 'AI',
                        'SOCIAL' => 'Media sosial',
                        'SEARCH' => 'Pencarian',
                        'NEWS' => 'Berita',
                        'OPEN_DATA' => 'Data terbuka',
                        'LOCAL' => 'Lokal (loopback)',
                    ])
                    ->required(),
                TextInput::make('endpoint')
                    ->label('URL endpoint')
                    ->url()
                    ->required()
                    ->maxLength(255),
                Select::make('auth_scheme')
                    ->label('Metode autentikasi')
                    ->options([
                        'NONE' => 'Tanpa credential',
                        'BEARER' => 'Bearer token',
                        'API_KEY' => 'API key',
                        'OAUTH2' => 'OAuth2 (credential draft)',
                    ])
                    ->required(),
                TextInput::make('credential')
                    ->label('Credential (opsional)')
                    ->password()
                    ->maxLength(4096)
                    ->helperText('Disimpan terenkripsi; tidak akan ditampilkan lagi.'),
                TextInput::make('rate_limit_per_minute')
                    ->label('Batas permintaan per menit (konfigurasi)')
                    ->integer()
                    ->minValue(0)
                    ->maxValue(1000)
                    ->default(0),
            ])
            ->action(function (array $data): void {
                if (! static::canAccess() || ! $this->databaseReady()) {
                    throw new AuthorizationException('Connector drafts unavailable.');
                }

                $actor = auth()->user();
                if (! $actor instanceof User) {
                    throw new AuthorizationException('Superadmin required.');
                }

                app(ConnectorProfileDrafts::class)->create($actor, $data);

                Notification::make()
                    ->title('Draft disimpan. Provider tetap nonaktif.')
                    ->success()
                    ->send();
            });
    }
}