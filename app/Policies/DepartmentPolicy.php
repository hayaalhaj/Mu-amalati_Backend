<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    /**
     * Determine whether the user can manage departments.
     */
    public function manage(User $user, ?Department $department = null): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view a department.
     */
    public function view(User $user, ?Department $department = null): bool
    {
        return true;
    }
}
