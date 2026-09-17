<?php

namespace App\Policies;

use App\Models\Extracurricular;
use App\Models\User;

class ExtracurricularPolicy
{
    public function view(User $user, Extracurricular $ekskul): bool
    {
        return $user->company_id === $ekskul->company_id;
    }

    public function manage(User $user, Extracurricular $ekskul): bool
    {
        if ($user->role === 'admin') return $user->company_id === $ekskul->company_id;
        return $user->role === 'guru' && $user->id === $ekskul->pembina_id;
    }
}
