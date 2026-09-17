<?php

namespace App\Policies;

use App\Models\ClassPromotion;
use App\Models\User;

class ClassPromotionPolicy
{
    public function view(User $user, ClassPromotion $promotion): bool
    {
        return $user->role === 'admin' && $user->company_id === $promotion->company_id;
    }
}
