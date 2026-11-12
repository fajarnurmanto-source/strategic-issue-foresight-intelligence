<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property list<string> $aliases_json
 * @property list<string> $keywords_json
 * @property list<string> $exclusions_json
 * @property string $name
 * @property string $objective
 * @property string $category
 * @property string|null $geography
 * @property int $revision
 */
final class SifiMonitoringObject extends Model
{
    protected $table = 'sifi_monitoring_objects';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'user_id', 'name', 'description', 'category', 'aliases_json', 'keywords_json', 'exclusions_json', 'geography', 'objective', 'horizon', 'state', 'revision'];

    protected function casts(): array
    {
        return ['aliases_json' => 'array', 'keywords_json' => 'array', 'exclusions_json' => 'array', 'revision' => 'integer'];
    }
}
