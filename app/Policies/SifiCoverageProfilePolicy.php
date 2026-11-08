<?php

namespace App\Policies;

use App\Models\SifiCoverageProfile;
use App\Models\User;

final class SifiCoverageProfilePolicy
{
    public function viewAny(User $user): bool { return true; }
    public function create(User $user): bool { return true; }
    public function view(User $user, SifiCoverageProfile $profile): bool { return (int) $profile->user_id === (int) $user->id; }
    public function update(User $user, SifiCoverageProfile $profile): bool { return $this->view($user,$profile); }
}
