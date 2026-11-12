<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Local analyst-authored proposal; NEVER an admitted Python Issue Object. */
final class SifiIssueCandidate extends Model
{
    protected $table = 'sifi_issue_candidates';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'investigation_id', 'user_id', 'title', 'issue_hypothesis',
        'exposure_notes', 'evidence_gaps', 'next_checks', 'review_state', 'revision',
    ];

    protected function casts(): array
    {
        return ['revision' => 'integer'];
    }

    /** @return BelongsTo<SifiInvestigation, $this> */
    public function investigation(): BelongsTo
    {
        return $this->belongsTo(SifiInvestigation::class, 'investigation_id');
    }
}
