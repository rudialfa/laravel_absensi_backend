<?php

namespace App\Policies;

use App\Models\PpdbApplicant;
use App\Models\User;

class PpdbApplicantPolicy
{
    public function manage(User $user, PpdbApplicant $applicant): bool
    {
        return $user->role === 'admin' && $user->company_id === $applicant->company_id;
    }
}
