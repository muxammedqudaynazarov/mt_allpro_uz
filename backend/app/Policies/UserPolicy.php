<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Administrator faqat o'ziga biriktirilgan yoki tasdiq kutayotgan oddiy
     * xodimlarning holatini o'zgartira oladi. Hech kim o'zini o'zgartira olmaydi.
     */
    public function updateStatus(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $actor->isAdmin()
            && ! $target->isAdmin()
            && ($target->managed_by_id === $actor->id || $target->status === UserStatus::Pending);
    }

    public function updateRoles(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && ! $actor->is($target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && ! $actor->is($target);
    }
}
