<?php

namespace App\Services\Zoom;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Darsning Zoom'dagi haqiqiy holatini kuzatadi (scheduler har daqiqada chaqiradi).
 *
 * Zoom API uchrashuv holatini faqat "waiting" yoki "started" deb beradi: host
 * uchrashuvni tugatgach, holat yana "waiting" bo'ladi. Shuning uchun "started"dan
 * "waiting"ga o'tish — host darsni tugatgani. Aniqlik ±1 daqiqa; aniq vaqtlar
 * keyin Zoom hisobotidan olinadi (SyncLessonAttendance).
 */
class MeetingTracker
{
    /** Jadval tugaganidan keyin shuncha daqiqa ichida boshlanmasa — dars o'tkazilmagan. */
    public const MISSED_AFTER_MINUTES = 15;

    /** Zoom hisobotni tayyorlashi uchun kutish. */
    public const REPORT_DELAY_MINUTES = 3;

    public function __construct(private readonly ZoomClient $zoom) {}

    /** Hozir kuzatilishi kerak bo'lgan darslar. */
    public static function due(?Carbon $now = null): Builder
    {
        $now ??= now();

        return Lesson::query()
            ->whereNotNull('zoom_meeting_id')
            ->whereNotNull('zoom_account_id')
            ->where('status', '!=', LessonStatus::Cancelled->value)
            ->where(fn (Builder $q) => $q->whereNull('zoom_state')->orWhereIn('zoom_state', [Lesson::ZOOM_WAITING, Lesson::ZOOM_STARTED]))
            ->where('starts_at', '<=', $now->copy()->addMinutes(10))
            ->where('starts_at', '>=', $now->copy()->subHours(12));
    }

    public function poll(Lesson $lesson, ?Carbon $now = null): void
    {
        $now ??= now();
        $status = $this->zoom->meetingStatus($lesson->zoomAccount, $lesson->zoom_meeting_id);
        $lesson->zoom_checked_at = $now;

        if ($status === 'started') {
            if ($lesson->zoom_state !== Lesson::ZOOM_STARTED) {
                $lesson->zoom_state = Lesson::ZOOM_STARTED;
                $lesson->zoom_started_at ??= $now;
            }
        } elseif ($lesson->zoom_state === Lesson::ZOOM_STARTED) {
            $this->markEnded($lesson, $now);
        } elseif ($status === null || $now->greaterThan($lesson->ends_at->copy()->addMinutes(self::MISSED_AFTER_MINUTES))) {
            // Hech qachon boshlanmagan. Kuzatuv qisqa efirni ko'rmay qolgan bo'lishi mumkin —
            // hisobot baribir tekshiriladi.
            $lesson->zoom_state = Lesson::ZOOM_MISSED;
            $lesson->attendance_due_at = $now;
        } else {
            $lesson->zoom_state = Lesson::ZOOM_WAITING;
        }

        $lesson->save();
    }

    /** Host darsni tugatdi: dars tizimda ham yakunlanadi va hisobot olinadi. */
    public function markEnded(Lesson $lesson, Carbon $at): void
    {
        $lesson->zoom_state = Lesson::ZOOM_ENDED;
        $lesson->zoom_ended_at ??= $at;
        $lesson->attendance_due_at = $at->copy()->addMinutes(self::REPORT_DELAY_MINUTES);
        $lesson->attendance_attempts = 0;

        if ($lesson->status->isOpen()) {
            $lesson->status = LessonStatus::Finished;
            $lesson->ended_at ??= $at;
            $lesson->start_url = null;
        }
    }
}
