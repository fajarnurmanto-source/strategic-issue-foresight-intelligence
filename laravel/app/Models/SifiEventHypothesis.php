<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Alleged event hypothesis; never an observed/confirmed event. */
final class SifiEventHypothesis extends Model
{
    protected $table = 'sifi_event_hypotheses';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'candidate_id', 'owner_user_id', 'summary', 'time_note', 'place_note', 'revision'];

    protected function casts(): array
    {
        return ['revision' => 'integer'];
    }
}
