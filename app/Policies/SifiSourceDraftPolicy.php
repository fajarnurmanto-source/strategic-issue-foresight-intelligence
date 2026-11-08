<?php

namespace App\Policies;

use App\Models\SifiSourceDraft;
use App\Models\User;

final class SifiSourceDraftPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function create(User $user): bool { return true; }
    public function view(User $user, SifiSourceDraft $source): bool { return (int) $source->user_id === (int) $user->id; }
    public function update(User $user, SifiSourceDraft $source): bool { return $this->view($user,$source); }
}
