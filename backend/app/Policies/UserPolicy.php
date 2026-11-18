<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\User;

/**
 * Foydalanuvchilarni Administrator boshqaradi. O'quv bo'limi ro'yxatni ko'radi va yangi
 * (tasdiq kutayotgan) foydalanuvchini tasdiqlay oladi — boshqa hech narsani o'zgartira olmaydi.
 * Hech kim o'zining rolini yoki holatini o'zgartira olmaydi.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewAll();
    }

    /** Faqat "Tasdiq kutmoqda" -> "Faol": Administrator va O'quv bo'limi. */
    public function approve(User $actor, User $target): bool
    {
        return in_array('users.approve', $actor->permissions(), true)
            && ! $actor->is($target)
            && $target->status === UserStatus::Pending;
    }

    public function updateStatus(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && ! $actor->is($target);
    }

    public function updateRoles(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && ! $actor->is($target);
    }

    /** Ismni vaqtincha to'g'rilash (HEMIS orqali kirganda yana yangilanadi) va kalendar ruxsati. */
    public function updateProfile(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && ! $actor->is($target);
    }
}
