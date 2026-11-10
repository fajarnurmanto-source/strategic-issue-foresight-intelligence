<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Laravel-only evidence TRIAGE candidate, never a qualified/admitted Python evidence record. */
final class SifiEvidenceDraft extends Model
{
    protected $table = 'sifi_evidence_drafts';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'id', 'candidate_id', 'owner_user_id', 'reviewer_user_id', 'signal_id',
        'claim_text', 'rationale', 'known_gaps', 'relationship', 'workflow_state', 'revision',
    ];
    protected function casts(): array { return ['revision'=>'integer']; }
    public function candidate(): BelongsTo { return $this->belongsTo(SifiIssueCandidate::class, 'candidate_id'); }
}
