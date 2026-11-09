<?php

namespace App\Policies;

use App\Models\SifiInvestigation;
use App\Models\SifiIssueCandidate;
use App\Models\User;

/** Reuse native Laravel Policy; no bespoke ACL/roles for the offline POC. */
final class SifiIssueCandidatePolicy
{
    public function create(User $user, SifiInvestigation $investigation): bool
    {
        return (int) $investigation->user_id === (int) $user->id;
    }

    public function view(User $user, SifiIssueCandidate $candidate): bool
    {
        return (int) $candidate->user_id === (int) $user->id;
    }

    public function update(User $user, SifiIssueCandidate $candidate): bool
    {
        return $this->view($user, $candidate);
    }
}
