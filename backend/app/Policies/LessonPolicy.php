<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    public function create(User $user): bool
    {
        return $user->isActive() && in_array('lessons.create', $user->permissions(), true);
    }

    public function view(User $user, Lesson $lesson): bool
    {
        return $user->canViewAll() || $lesson->user_id === $user->id;
    }

    /** Host havolasi faqat darsni yaratgan o'qituvchiga beriladi. */
    public function start(User $user, Lesson $lesson): bool
    {
        return $lesson->user_id === $user->id && $lesson->isOpen();
    }

    public function end(User $user, Lesson $lesson): bool
    {
        return $lesson->isOpen() && ($lesson->user_id === $user->id || $user->isSuperAdmin());
    }
}
