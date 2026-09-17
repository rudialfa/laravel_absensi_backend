<?php

namespace App\Policies;

use App\Models\StudentMutation;
use App\Models\User;

class StudentMutationPolicy
{
    public function view(User $user, StudentMutation $mutation): bool
    {
        return $user->role === 'admin' && $user->company_id === $mutation->company_id;
    }
}
