<?php

namespace App\Services\Zoom;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Models\LiveParticipant;
use App\Models\ZoomAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

/**
 * Zoom hodisalarini darsga qo'llaydi. Zoom bir hodisani qayta yuborishi mumkin —
 * barcha amallar takroriy chaqiruvga chidamli (idempotent).
 */
class WebhookHandler
{
    public function __construct(private readonly MeetingTracker $tracker) {}

    /** @param  string|null  $downloadToken  recording.completed bilan Zoom bergan yuklash tokeni (24 soat) */
    public function handle(ZoomAccount $account, string $event, array $payload, ?string $downloadToken = null): void
    {
        $object = (array) ($payload['object'] ?? []);

        if (str_starts_with($event, 'recording.')) {
            Event::dispatch('zoom.recording', [$account, $event, $object, $downloadToken]);

            return;
        }

        $meetingId = (string) ($object['id'] ?? '');
        if ($meetingId === '') {
            return;
        }
        $lesson = Lesson::query()
            ->where('zoom_account_id', $account->id)
            ->where('zoom_meeting_id', $meetingId)
            ->latest('starts_at')
            ->first();
        if (! $lesson) {
            return;
        }

        match ($event) {
            'meeting.started' => $this->started($lesson, self::time($object['start_time'] ?? null)),
            'meeting.ended' => $this->ended($lesson, self::time($object['end_time'] ?? null)),
            'meeting.participant_joined' => $this->joined($lesson, $object),
            'meeting.participant_left' => $this->left($lesson, (array) ($object['participant'] ?? [])),
            default => null,
        };
    }

    private function started(Lesson $lesson, Carbon $at): void
    {
        if ($lesson->zoom_state === Lesson::ZOOM_ENDED) {
            return;
        }
        $lesson->zoom_state = Lesson::ZOOM_STARTED;
        $lesson->zoom_started_at ??= $at;
        $lesson->zoom_checked_at = now();
        if ($lesson->status === LessonStatus::Scheduled) {
            $lesson->status = LessonStatus::Live;
        }
        $lesson->save();
    }

    private function ended(Lesson $lesson, Carbon $at): void
    {
        if ($lesson->zoom_state !== Lesson::ZOOM_ENDED) {
            $lesson->zoom_started_at ??= $lesson->starts_at->min($at);
            $this->tracker->markEnded($lesson, $at);
            $lesson->zoom_checked_at = now();
            $lesson->save();
        }
        LiveParticipant::query()->where('lesson_id', $lesson->id)->whereNull('left_at')->update(['left_at' => $at]);
    }

    private function joined(Lesson $lesson, array $object): void
    {
        $participant = (array) ($object['participant'] ?? []);
        $key = self::key($participant);
        $joinedAt = self::time($participant['join_time'] ?? null);

        if ($lesson->zoom_state !== Lesson::ZOOM_STARTED && $lesson->zoom_state !== Lesson::ZOOM_ENDED) {
            $this->started($lesson, $joinedAt);
        }

        $open = LiveParticipant::query()->where('lesson_id', $lesson->id)->where('participant_key', $key)->whereNull('left_at')->exists();
        if ($open) {
            return;
        }

        $hostId = (string) ($object['host_id'] ?? '');
        LiveParticipant::create([
            'lesson_id' => $lesson->id,
            'participant_key' => $key,
            'name' => mb_substr(trim((string) ($participant['user_name'] ?? '')) ?: '—', 0, 120),
            'is_host' => $hostId !== '' && (string) ($participant['id'] ?? '') === $hostId,
            'joined_at' => $joinedAt,
        ]);
    }

    private function left(Lesson $lesson, array $participant): void
    {
        LiveParticipant::query()
            ->where('lesson_id', $lesson->id)
            ->where('participant_key', self::key($participant))
            ->whereNull('left_at')
            ->update(['left_at' => self::time($participant['leave_time'] ?? null)]);
    }

    /**
     * Ulanish identifikatori: participant_uuid (qayta kirganda ham saqlanadi), bo'lmasa
     * shu sessiyadagi user_id, eng oxiri — ism.
     */
    private static function key(array $participant): string
    {
        $value = (string) ($participant['participant_uuid'] ?? '') ?: (string) ($participant['user_id'] ?? '');

        return $value !== '' ? mb_substr($value, 0, 64) : 'name:'.md5(mb_strtolower(trim((string) ($participant['user_name'] ?? ''))));
    }

    private static function time(mixed $value): Carbon
    {
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->setTimezone(config('app.timezone'));
            } catch (\Throwable) {
            }
        }

        return now();
    }
}
