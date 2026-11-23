<?php

namespace App\Actions;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Zoom\MeetingTracker;
use App\Services\Zoom\ZoomClient;
use App\Support\Audit;

class EndLesson
{
    public function __construct(private readonly ZoomClient $zoom, private readonly MeetingTracker $tracker) {}

    public function handle(Lesson $lesson, User $actor): Lesson
    {
        if ($lesson->zoom_meeting_id && $lesson->zoomAccount) {
            $this->zoom->endMeeting($lesson->zoomAccount, $lesson->zoom_meeting_id);
        }

        $now = now();
        $wasStarted = $now->greaterThanOrEqualTo($lesson->starts_at) || $lesson->zoom_state === Lesson::ZOOM_STARTED;
        $lesson->forceFill([
            'status' => $wasStarted ? LessonStatus::Finished : LessonStatus::Cancelled,
            'ended_at' => $now,
            'ended_by_id' => $actor->id,
            'start_url' => null,
        ]);
        if ($wasStarted && $lesson->zoom_meeting_id) {
            // Zoom'dagi aniq vaqtlar va davomat bir necha daqiqadan keyin hisobotdan olinadi.
            if ($lesson->zoom_state === Lesson::ZOOM_STARTED) {
                $this->tracker->markEnded($lesson, $now);
            } else {
                $lesson->forceFill([
                    'zoom_state' => Lesson::ZOOM_MISSED,
                    'attendance_due_at' => $now->copy()->addMinutes(MeetingTracker::REPORT_DELAY_MINUTES),
                ]);
            }
        }
        $lesson->save();

        Audit::log(
            $wasStarted ? 'lesson.ended' : 'lesson.cancelled',
            $wasStarted ? 'Dars yakunlandi: :group' : 'Dars bekor qilindi: :group',
            $lesson,
            actor: $actor,
            replace: ['group' => $lesson->groupsLabel()],
        );

        return $lesson;
    }
}
