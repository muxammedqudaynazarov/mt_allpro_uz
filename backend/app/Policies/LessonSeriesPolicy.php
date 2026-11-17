<?php

namespace App\Policies;

use App\Models\LessonSeries;
use App\Models\User;

/**
 * Haftalik darslar: o'qituvchi (kalendar huquqi bilan) yaratadi va tahrirlaydi,
 * o'zi bekor qila oladi (huquqi olib qo'yilgan bo'lsa ham), Administrator — hammasini,
 * shu jumladan butunlay o'chirishni.
 */
class LessonSeriesPolicy
{
    public function view(User $user, LessonSeries $series): bool
    {
        return $user->canViewAll() || $series->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->canManageCalendar();
    }

    public function update(User $user, LessonSeries $series): bool
    {
        return $user->isSuperAdmin() || ($series->user_id === $user->id && $user->canManageCalendar());
    }

    /** Butun jadvalni to'xtatish yoki bitta sanani bekor qilish. */
    public function cancel(User $user, LessonSeries $series): bool
    {
        return $user->isSuperAdmin() || $series->user_id === $user->id;
    }

    public function delete(User $user, LessonSeries $series): bool
    {
        return $user->isSuperAdmin();
    }
}
