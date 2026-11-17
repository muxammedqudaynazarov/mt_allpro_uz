<?php

namespace App\Actions;

use App\Enums\LessonStatus;
use App\Exceptions\ExternalServiceException;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonSlot;
use App\Models\User;
use App\Services\Zoom\ZoomClient;
use App\Support\Audit;
use App\Support\Notifier;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Yangi dars yaratish:
 *  1) vaqt oralig'ini aniqlash (para yoki "hozir"),
 *  2) guruh va Zoom host bandligini qulf (lock) ostida tekshirish,
 *  3) Zoom'da uchrashuv yaratish, 4) bazaga yozish, 5) Telegram xabari.
 */
class CreateLesson
{
    public function __construct(private readonly ZoomClient $zoom, private readonly Notifier $notifier) {}

    public function handle(User $teacher, Group $group, ?LessonSlot $slot, ?string $topic): Lesson
    {
        [$startsAt, $endsAt] = self::window($slot);
        $account = $group->zoomAccount;

        if (! $group->is_active || ! $account?->is_active) {
            throw ValidationException::withMessages(['group_id' => __('Bu guruhga hozir dars yaratib boʻlmaydi: guruh yoki uning Zoom akkaunti nofaol.')]);
        }

        // Bir Zoom host bir vaqtda faqat bitta uchrashuv o'tkaza oladi. Ikki o'qituvchi
        // bir vaqtda bir guruhni tanlasa, ikkinchisi kutib turadi va band xabarini oladi.
        $lock = Cache::lock('lesson-create:'.$account->id.':'.mb_strtolower($group->zoom_user_email), 30);

        try {
            return $lock->block(10, function () use ($teacher, $group, $slot, $topic, $startsAt, $endsAt, $account) {
                if ($conflict = self::conflict($group, $startsAt, $endsAt)) {
                    throw ValidationException::withMessages([
                        'group_id' => $conflict->group_id === $group->id
                            ? __('Bu guruhda :from–:to oraligʻida boshqa dars bor.', ['from' => $conflict->starts_at->format('H:i'), 'to' => $conflict->ends_at->format('H:i')])
                            : __('Guruhning Zoom hisobi (:email) shu vaqtda boshqa guruh darsida band.', ['email' => $group->zoom_user_email]),
                    ]);
                }

                $title = trim((string) $topic) !== '' ? trim((string) $topic) : $group->name;
                $passcode = (string) random_int(100000, 999999);
                $duration = max(10, (int) ceil($startsAt->diffInMinutes($endsAt)));

                $meeting = $this->zoom->createMeeting($account, $group->zoom_user_email, $title, $startsAt, $duration, $passcode);

                $lesson = Lesson::create([
                    'user_id' => $teacher->id,
                    'group_id' => $group->id,
                    'lesson_slot_id' => $slot?->id,
                    'zoom_account_id' => $account->id,
                    'zoom_meeting_id' => $meeting['id'],
                    'zoom_host_email' => mb_strtolower($group->zoom_user_email),
                    'topic' => $title,
                    'join_url' => $meeting['join_url'],
                    'start_url' => $meeting['start_url'],
                    'passcode' => $meeting['password'] ?: $passcode,
                    'status' => $startsAt->isFuture() ? LessonStatus::Scheduled : LessonStatus::Live,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);

                $lesson->setRelation('group', $group);
                $lesson->setRelation('teacher', $teacher);

                Audit::log('lesson.created', 'Dars yaratildi: :group, :time', $lesson, ['zoom_meeting_id' => $meeting['id']], $teacher, ['group' => $group->name, 'time' => $startsAt->format('d.m.Y H:i')]);
                $this->notifier->lessonCreated($lesson);

                return $lesson;
            });
        } catch (LockTimeoutException) {
            throw new ExternalServiceException(__('Bu guruh uchun hozir boshqa dars yaratilmoqda. Bir necha soniyadan soʻng qayta urinib koʻring.'), status: 409);
        }
    }

    /**
     * Dars vaqt oralig'i. Para allaqachon boshlangan bo'lsa, dars hozirdan
     * boshlanib, para oxirigacha davom etadi.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function window(?LessonSlot $slot, ?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy()->startOfMinute();
        if (! $slot) {
            return [$now, $now->copy()->addMinutes((int) config('lessons.default_duration', 80))];
        }

        $start = $slot->startOn($now);
        $end = $slot->endOn($now);
        if ($end->lessThanOrEqualTo($now->copy()->addMinutes(5))) {
            throw ValidationException::withMessages(['slot_id' => __('“:name” vaqti tugagan. Boshqa parani yoki “Hozir”ni tanlang.', ['name' => $slot->name])]);
        }

        return [$start->greaterThan($now) ? $start : $now, $end];
    }

    /** Guruh yoki uning Zoom host'i shu oraliqda band bo'lsa, to'qnashgan darsni qaytaradi. */
    public static function conflict(Group $group, Carbon $from, Carbon $to): ?Lesson
    {
        return Lesson::query()
            ->overlapping($from, $to)
            ->where(fn ($q) => $q
                ->where('group_id', $group->id)
                ->orWhere(fn ($q) => $q
                    ->where('zoom_account_id', $group->zoom_account_id)
                    ->where('zoom_host_email', mb_strtolower($group->zoom_user_email))))
            ->orderByDesc('ends_at')
            ->first();
    }
}
