<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Offline configuration draft. Not an executable connector or API permission. */
final class SifiConnectorProfile extends Model
{
    protected $table = 'sifi_connector_profiles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    protected $hidden = ['credential_ciphertext'];

    protected function casts(): array
    {
        return [
            'credential_ciphertext' => 'encrypted',
            'rate_limit_per_minute' => 'integer',
            'max_cost_micros' => 'integer',
            'enabled' => 'boolean',
            'billing_authorized' => 'boolean',
        ];
    }

    public function credentialIsStored(): bool
    {
        return $this->getRawOriginal('credential_ciphertext') !== null;
    }
}