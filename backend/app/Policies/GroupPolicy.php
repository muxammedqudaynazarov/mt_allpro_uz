<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewAll();
    }

    public function toggle(User $user, Group $group): bool
    {
        return $user->isSuperAdmin();
    }

    /** Guruh talabalari (davomat) — ko'rish, birlashtirish, nomini o'zgartirish. */
    public function manageStudents(User $user, Group $group): bool
    {
        return $user->canViewAll();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Group $group): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Group $group): bool
    {
        return $user->isSuperAdmin();
    }
}
