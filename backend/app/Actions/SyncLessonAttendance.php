<?php

namespace App\Actions;

use App\Exceptions\ExternalServiceException;
use App\Models\Lesson;
use App\Models\ZoomParticipation;
use App\Services\Zoom\ZoomClient;
use App\Support\AttendanceCalculator;
use App\Support\StudentName;
use App\Support\StudentResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dars tugagach Zoom'dan uchrashuvning aniq vaqtlari va qatnashchilar ro'yxatini
 * olib, davomatni hisoblaydi. Zoom hisobotni tugaganidan bir necha daqiqa keyin
 * tayyorlaydi, shuning uchun scheduler bir necha marta urinadi.
 */
class SyncLessonAttendance
{
    public const MAX_ATTEMPTS = 6;

    /** Urinishlar orasidagi kutish (daqiqa). */
    private const BACKOFF = [3, 10, 30, 60, 180];

    public function __construct(
        private readonly ZoomClient $zoom,
        private readonly AttendanceCalculator $calculator,
    ) {}

    /**
     * @return bool true — ma'lumot olindi (yoki dars o'tkazilmagani aniq), false — Zoom hisoboti hali tayyor emas
     *
     * @throws ExternalServiceException
     */
    public function handle(Lesson $lesson): bool
    {
        $account = $lesson->zoomAccount;
        if (! $account || ! $lesson->zoom_meeting_id) {
            throw new ExternalServiceException(__('Darsning Zoom akkaunti oʻchirilgan.'));
        }

        $instances = $this->zoom->pastInstances($account, $lesson->zoom_meeting_id);
        if (! $instances) {
            // Kuzatuv efirni ko'rgan bo'lsa, Zoom hali ro'yxatga qo'shmagan — keyinroq urinamiz.
            if ($lesson->zoom_started_at && $lesson->attendance_attempts < 2) {
                return false;
            }
            $this->finish($lesson, $lesson->zoom_started_at ? Lesson::ZOOM_ENDED : Lesson::ZOOM_MISSED);

            return true;
        }

        $tz = (string) config('app.timezone');
        $parse = fn (?string $value) => $value ? Carbon::parse($value)->timezone($tz) : null;
        $hostEmail = mb_strtolower($lesson->zoom_host_email);

        $segments = [];
        $starts = $ends = [];
        $seconds = 0;
        foreach ($instances as $instance) {
            $details = $this->zoom->pastMeeting($account, $instance['uuid']);
            $participants = $this->zoom->meetingParticipants($account, $instance['uuid']);
            if ($participants === null) {
                // Faqat host bo'lgan uchrashuv uchun Zoom hisobot bermaydi.
                if (($details['participants_count'] ?? null) !== null && $details['participants_count'] <= 1) {
                    $participants = [];
                } else {
                    return false;
                }
            }

            $start = $parse($details['start_time'] ?? null) ?? $parse($instance['start_time']);
            $lastLeave = collect($participants)->map(fn ($p) => $parse($p['leave_time']))->filter()->max();
            $end = $parse($details['end_time'] ?? null) ?? $lastLeave ?? ($start ? $lesson->zoom_ended_at : null);
            if (! $start) {
                continue;
            }
            $end = $end && $end->greaterThan($start) ? $end : $start->copy();
            $starts[] = $start;
            $ends[] = $end;
            $seconds += $start->diffInSeconds($end);

            foreach ($participants as $participant) {
                $joined = $parse($participant['join_time']);
                $left = $parse($participant['leave_time']) ?? $end;
                if (! $joined) {
                    continue;
                }
                $segments[] = [
                    'meeting_uuid' => substr($instance['uuid'], 0, 64),
                    'name' => StudentName::clean($participant['name']),
                    'email' => $participant['email'] !== '' ? substr($participant['email'], 0, 191) : null,
                    'zoom_user_id' => $participant['id'] !== '' ? substr($participant['id'], 0, 64) : null,
                    'is_host' => ($participant['email'] !== '' && mb_strtolower($participant['email']) === $hostEmail)
                        || ($participant['id'] !== '' && $participant['id'] === ($details['host_id'] ?? null)),
                    'joined_at' => $joined,
                    'left_at' => $left->greaterThan($joined) ? $left : $joined,
                ];
            }
        }

        if (! $starts) {
            return false;
        }

        $lesson->forceFill([
            'zoom_started_at' => min($starts),
            'zoom_ended_at' => max($ends),
            'zoom_minutes' => (int) round($seconds / 60),
        ]);

        // Bir guruhning talabalari parallel yaratilib qolmasligi uchun.
        Cache::lock('attendance:group:'.$lesson->group_id, 60)->block(30, function () use ($lesson, $segments) {
            DB::transaction(function () use ($lesson, $segments) {
                $resolver = new StudentResolver;
                ZoomParticipation::query()->where('lesson_id', $lesson->id)->delete();
                foreach ($segments as $segment) {
                    $student = $segment['is_host'] ? null : $resolver->resolve($lesson->group_id, $segment['name'], $segment['email'], $segment['zoom_user_id']);
                    ZoomParticipation::create($segment + ['lesson_id' => $lesson->id, 'student_id' => $student?->id]);
                }
                $this->finish($lesson, Lesson::ZOOM_ENDED);
                $this->calculator->rebuild($lesson);
            });
        });

        return true;
    }

    /** Scheduler uchun: xato va "hali tayyor emas" holatida keyingi urinishni rejalashtiradi. */
    public function attempt(Lesson $lesson): bool
    {
        try {
            if ($this->handle($lesson)) {
                return true;
            }
            $error = __('Zoom hisoboti hali tayyor emas.');
        } catch (ExternalServiceException $e) {
            $error = $e->getMessage();
        }

        $attempt = $lesson->attendance_attempts + 1;
        $lesson->forceFill([
            'attendance_attempts' => $attempt,
            'attendance_error' => mb_substr($error, 0, 255),
            'attendance_due_at' => $attempt < self::MAX_ATTEMPTS ? now()->addMinutes(self::BACKOFF[min($attempt, count(self::BACKOFF)) - 1]) : null,
        ])->save();

        return false;
    }

    private function finish(Lesson $lesson, string $state): void
    {
        $lesson->forceFill([
            'zoom_state' => $state,
            'attendance_synced_at' => now(),
            'attendance_due_at' => null,
            'attendance_error' => null,
        ])->save();
    }
}
