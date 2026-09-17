<?php

namespace App\Policies;

use App\Models\StudentBill;
use App\Models\User;

class StudentBillPolicy
{
    public function view(User $user, StudentBill $bill): bool
    {
        if ($user->role === 'admin') return $user->company_id === $bill->company_id;
        if ($user->role === 'wali') return $user->guardedStudents()->where('students.id', $bill->student_id)->exists();
        return false;
    }

    public function manage(User $user, StudentBill $bill): bool
    {
        return $user->role === 'admin' && $user->company_id === $bill->company_id;
    }
}
