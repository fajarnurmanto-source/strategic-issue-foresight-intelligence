<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Laravel-owned analyst draft. Separate from all Python Evidence and Issue Objects. */
final class SifiInvestigation extends Model
{
    protected $table = 'sifi_investigations';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = ['*'];

    /** @return HasOne<SifiIssueCandidate, $this> */
    public function issueCandidate(): HasOne
    {
        return $this->hasOne(SifiIssueCandidate::class, 'investigation_id');
    }
}
