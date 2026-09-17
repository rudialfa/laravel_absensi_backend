<?php

namespace App\Policies;

use App\Models\PpdbPeriod;
use App\Models\User;

class PpdbPeriodPolicy
{
    public function view(User $user, PpdbPeriod $period): bool
    {
        return $user->role === 'admin' && $user->company_id === $period->company_id;
    }

    public function update(User $user, PpdbPeriod $period): bool
    {
        return $this->view($user, $period);
    }
}
