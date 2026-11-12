<?php

namespace App\Policies;

use App\Models\SifiMonitoringObject;
use App\Models\User;

final class SifiMonitoringObjectPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->hasVerifiedEmail();
    }

    public function create(User $u): bool
    {
        return $this->viewAny($u);
    }

    public function view(User $u, SifiMonitoringObject $o): bool
    {
        return $this->viewAny($u) && (int) $o->user_id === (int) $u->id;
    }

    public function update(User $u, SifiMonitoringObject $o): bool
    {
        return $this->view($u, $o);
    }
}
