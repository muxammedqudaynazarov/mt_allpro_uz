<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function toggle(User $user, Group $group): bool
    {
        return $user->isSuperAdmin() || ($user->isAdmin() && $group->admin_id === $user->id);
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
