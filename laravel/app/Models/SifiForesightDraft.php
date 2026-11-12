<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Human-authored scenario hypotheses. No forecast, evidence admission or Issue Object.
 *
 * These attributes correspond to non-nullable text columns in the foresight migration.
 *
 * @property string $baseline_scenario
 * @property string $alternative_scenario
 * @property string $stress_scenario
 * @property string $critical_drivers
 * @property string $falsification_checks
 * @property string $decision_options
 * @property string $uncertainty_note
 */
final class SifiForesightDraft extends Model
{
    protected $table = 'sifi_foresight_drafts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'trajectory_id', 'owner_user_id', 'baseline_scenario', 'alternative_scenario', 'stress_scenario',
        'critical_drivers', 'falsification_checks', 'decision_options', 'uncertainty_note', 'revision'];

    protected function casts(): array
    {
        return ['revision' => 'integer'];
    }
}
