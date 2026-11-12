<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Geography and topic configuration, never collection authority. */
final class SifiCoverageProfile extends Model
{
    protected $table = 'sifi_coverage_profiles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'user_id', 'name', 'level', 'region_name', 'domains_json', 'revision'];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'domains_json' => 'array'];
    }
}
