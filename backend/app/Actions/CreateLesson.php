<?php

namespace App\Actions;

use App\Enums\LessonStatus;
use App\Exceptions\ExternalServiceException;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonSlot;
use App\Models\Setting;
use App\Models\User;
use App\Models\ZoomHost;
use App\Services\Zoom\ZoomClient;
use App\Support\Audit;
use App\Support\HostAvailability;
use App\Support\Notifier;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Yangi dars yaratish:
 *  1) vaqt oralig'ini aniqlash (juftlik yoki "hozir"),
 *  2) bo'sh Zoom hostni tanlash va bandligini qulf (lock) ostida tekshirish,
 *  3) Zoom'da uchrashuv yaratish, 4) bazaga yozish, 5) Telegram xabari.
 */
class CreateLesson
{
    public function __construct(private readonly ZoomClient $zoom, private readonly Notifier $notifier) {}

    /**
     * @param  ZoomHost|null  $host  o'qituvchi tanlagan host; ko'rsatilmasa — birinchi bo'sh host
     * @param  Carbon|null  $date  boshqa kun uchun (kalendar); ko'rsatilmasa — bugun
     */
    public function handle(User $teacher, Group $group, ?LessonSlot $slot, ?string $topic, ?ZoomHost $host = null, ?Carbon $date = null, ?int $seriesId = null): Lesson
    {
        [$startsAt, $endsAt] = self::window($slot, null, $date);

        if (! $group->is_active) {
            throw ValidationException::withMessages(['group_id' => __('Bu guruh nofaol — unga dars yaratib boʻlmaydi.')]);
        }
        if ($host && (! $host->is_active || ! $host->zoomAccount?->is_active)) {
            throw ValidationException::withMessages(['host_id' => __('Tanlangan Zoom host nofaol.')]);
        }

        self::assertGroupFree($group, $startsAt, $endsAt);

        $candidates = $host ? collect([$host]) : HostAvailability::free($startsAt, $endsAt, $group);
        if ($candidates->isEmpty()) {
            throw ValidationException::withMessages(['host_id' => __('Bu vaqtda boʻsh Zoom host yoʻq. Boshqa vaqtni tanlang.')]);
        }

        foreach ($candidates as $candidate) {
            $lesson = $this->tryHost($candidate, explicit: $host !== null, teacher: $teacher, group: $group, slot: $slot, topic: $topic, startsAt: $startsAt, endsAt: $endsAt, seriesId: $seriesId);
            if ($lesson) {
                return $lesson;
            }
        }

        throw ValidationException::withMessages(['host_id' => __('Bu vaqtda boʻsh Zoom host yoʻq. Boshqa vaqtni tanlang.')]);
    }

    /**
     * Hostni qulf (lock) ostida band qiladi. Bir host bir vaqtda faqat bitta uchrashuv
     * o'tkaza oladi: ikki o'qituvchi bir vaqtda bitta hostni tanlasa, ikkinchisi band
     * xabarini oladi (yoki avtomatik tanlovda keyingi bo'sh hostga o'tadi).
     */
    private function tryHost(ZoomHost $host, bool $explicit, User $teacher, Group $group, ?LessonSlot $slot, ?string $topic, Carbon $startsAt, Carbon $endsAt, ?int $seriesId): ?Lesson
    {
        $lock = Cache::lock('lesson-create:host:'.$host->id, 30);

        try {
            return $lock->block(10, function () use ($host, $explicit, $teacher, $group, $slot, $topic, $startsAt, $endsAt, $seriesId) {
                self::assertGroupFree($group, $startsAt, $endsAt);
                if (self::hostConflict($host, $startsAt, $endsAt)) {
                    if ($explicit) {
                        throw ValidationException::withMessages(['host_id' => __('Zoom host (:email) shu vaqtda boshqa darsda band.', ['email' => $host->email])]);
                    }

                    return null;
                }

                $account = $host->zoomAccount;
                $title = trim((string) $topic) !== '' ? trim((string) $topic) : $group->name;
                $passcode = (string) random_int(100000, 999999);
                $duration = max(10, (int) ceil($startsAt->diffInMinutes($endsAt)));

                $record = (bool) (Setting::get('recording', [])['enabled'] ?? false);
                $meeting = $this->zoom->createMeeting($account, $host->email, $title, $startsAt, $duration, $passcode, $record);

                $lesson = Lesson::create([
                    'user_id' => $teacher->id,
                    'group_id' => $group->id,
                    'lesson_slot_id' => $slot?->id,
                    'lesson_series_id' => $seriesId,
                    'zoom_account_id' => $account->id,
                    'zoom_meeting_id' => $meeting['id'],
                    'zoom_host_email' => $host->email,
                    'topic' => $title,
                    'join_url' => $meeting['join_url'],
                    'start_url' => $meeting['start_url'],
                    'passcode' => $meeting['password'] ?: $passcode,
                    'record_cloud' => $record,
                    'status' => $startsAt->isFuture() ? LessonStatus::Scheduled : LessonStatus::Live,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);

                $lesson->setRelation('group', $group);
                $lesson->setRelation('teacher', $teacher);

                Audit::log('lesson.created', 'Dars yaratildi: :group, :time', $lesson, ['zoom_meeting_id' => $meeting['id'], 'host' => $host->email], $teacher, ['group' => $group->name, 'time' => $startsAt->format('d.m.Y H:i')]);
                $this->notifier->lessonCreated($lesson);

                return $lesson;
            });
        } catch (LockTimeoutException) {
            throw new ExternalServiceException(__('Bu Zoom host uchun hozir boshqa dars yaratilmoqda. Bir necha soniyadan soʻng qayta urinib koʻring.'), status: 409);
        }
    }

    /**
     * Dars vaqt oralig'i. Juftlik allaqachon boshlangan bo'lsa, dars hozirdan
     * boshlanib, juftlik oxirigacha davom etadi.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function window(?LessonSlot $slot, ?Carbon $now = null, ?Carbon $date = null): array
    {
        $now = ($now ?? now())->copy()->startOfMinute();
        if ($date && ! $date->isSameDay($now)) {
            if ($date->lessThan($now->copy()->startOfDay())) {
                throw ValidationException::withMessages(['date' => __('Oʻtgan kunga dars yaratib boʻlmaydi.')]);
            }
            if (! $slot) {
                throw ValidationException::withMessages(['slot_id' => __('Boshqa kun uchun juftlikni tanlang.')]);
            }

            return [$slot->startOn($date), $slot->endOn($date)];
        }
        if (! $slot) {
            return [$now, $now->copy()->addMinutes((int) config('lessons.default_duration', 80))];
        }

        $start = $slot->startOn($now);
        $end = $slot->endOn($now);
        if ($end->lessThanOrEqualTo($now->copy()->addMinutes(5))) {
            throw ValidationException::withMessages(['slot_id' => __('“:name” vaqti tugagan. Boshqa juftlikni yoki “Hozir”ni tanlang.', ['name' => $slot->label()])]);
        }

        return [$start->greaterThan($now) ? $start : $now, $end];
    }

    /** Guruhda shu oraliqda boshqa dars bo'lsa — o'sha dars. */
    public static function groupConflict(Group $group, Carbon $from, Carbon $to): ?Lesson
    {
        return Lesson::query()->overlapping($from, $to)->where('group_id', $group->id)->orderByDesc('ends_at')->first();
    }

    private static function assertGroupFree(Group $group, Carbon $from, Carbon $to): void
    {
        if ($conflict = self::groupConflict($group, $from, $to)) {
            throw ValidationException::withMessages([
                'group_id' => __('Bu guruhda :from–:to oraligʻida boshqa dars bor.', ['from' => $conflict->starts_at->format('H:i'), 'to' => $conflict->ends_at->format('H:i')]),
            ]);
        }
    }

    /** Host shu oraliqda boshqa darsda bandmi. */
    public static function hostConflict(ZoomHost $host, Carbon $from, Carbon $to): bool
    {
        return Lesson::query()
            ->overlapping($from, $to)
            ->where('zoom_account_id', $host->zoom_account_id)
            ->where('zoom_host_email', $host->email)
            ->exists();
    }
}
