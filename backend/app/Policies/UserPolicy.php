<?php

namespace App\Policies;

use App\Models\User;

/**
 * Foydalanuvchilarni faqat Administrator boshqaradi. O'quv bo'limi ro'yxatni
 * ko'ra oladi, lekin o'zgartira olmaydi. Hech kim o'zining rolini yoki holatini
 * o'zgartira olmaydi.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewAll();
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
