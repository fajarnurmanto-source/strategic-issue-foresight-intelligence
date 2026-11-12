<?php

namespace App\Policies;

use App\Models\SifiEvidenceDraft;
use App\Models\SifiIssueCandidate;
use App\Models\User;

final class SifiEvidenceDraftPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user, SifiIssueCandidate $candidate): bool
    {
        return (int) $candidate->user_id === (int) $user->id;
    }

    public function view(User $user, SifiEvidenceDraft $evidence): bool
    {
        return (int) $evidence->owner_user_id === (int) $user->id || (int) $evidence->reviewer_user_id === (int) $user->id;
    }

    public function update(User $user, SifiEvidenceDraft $evidence): bool
    {
        return (int) $evidence->owner_user_id === (int) $user->id
            && in_array($evidence->workflow_state, ['DRAFT', 'REVIEW_RECORDED'], true);
    }

    public function assign(User $user, SifiEvidenceDraft $evidence): bool
    {
        return $this->update($user, $evidence) && $evidence->workflow_state === 'DRAFT';
    }

    public function review(User $user, SifiEvidenceDraft $evidence): bool
    {
        return (int) $evidence->reviewer_user_id === (int) $user->id
            && (int) $evidence->owner_user_id !== (int) $user->id
            && $evidence->workflow_state === 'REVIEW_REQUESTED';
    }
}
