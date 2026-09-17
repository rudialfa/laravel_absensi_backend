<?php

namespace App\Policies;

use App\Models\BillType;
use App\Models\User;

class BillTypePolicy
{
    public function view(User $user, BillType $billType): bool
    {
        return $user->company_id === $billType->company_id;
    }

    public function update(User $user, BillType $billType): bool
    {
        return $user->role === 'admin' && $user->company_id === $billType->company_id;
    }

    public function delete(User $user, BillType $billType): bool
    {
        return $this->update($user, $billType);
    }
}
