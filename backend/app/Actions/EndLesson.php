<?php

namespace App\Actions;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Zoom\ZoomClient;
use App\Support\Audit;

class EndLesson
{
    public function __construct(private readonly ZoomClient $zoom) {}

    public function handle(Lesson $lesson, User $actor): Lesson
    {
        if ($lesson->zoom_meeting_id && $lesson->zoomAccount) {
            $this->zoom->endMeeting($lesson->zoomAccount, $lesson->zoom_meeting_id);
        }

        $now = now();
        $wasStarted = $now->greaterThanOrEqualTo($lesson->starts_at);
        $lesson->forceFill([
            'status' => $wasStarted ? LessonStatus::Finished : LessonStatus::Cancelled,
            'ended_at' => $now,
            'ended_by_id' => $actor->id,
            'start_url' => null,
        ])->save();

        Audit::log(
            $wasStarted ? 'lesson.ended' : 'lesson.cancelled',
            $wasStarted ? 'Dars yakunlandi: :group' : 'Dars bekor qilindi: :group',
            $lesson,
            actor: $actor,
            replace: ['group' => $lesson->group->name],
        );

        return $lesson;
    }
}
