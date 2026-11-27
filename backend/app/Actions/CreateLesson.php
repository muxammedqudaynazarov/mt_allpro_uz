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
use App\Support\LessonDays;
use App\Support\Notifier;
use App\Support\ZoomAutoEnd;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Yangi dars yaratish:
 *  1) vaqt oralig'ini aniqlash (juftlik va kun),
 *  2) guruh, keyin bo'sh Zoom host bandligini qulf (lock) ostida tekshirish —
 *     qulflar doim shu tartibda olinadi (guruh → host), shuning uchun bir-birini kutib qolmaydi,
 *  3) Zoom'da uchrashuv yaratish, 4) bazaga yozish, 5) Telegram xabari.
 */
class CreateLesson
{
    public function __construct(private readonly ZoomClient $zoom, private readonly Notifier $notifier) {}

    /**
     * @param  Group|iterable<Group>  $groups  dars guruhi; ma'ruzada — bir nechta guruh (birinchisi asosiy)
     * @param  ZoomHost|null  $host  o'qituvchi tanlagan host; ko'rsatilmasa — birinchi bo'sh host
     * @param  Carbon|null  $date  boshqa kun uchun (kalendar); ko'rsatilmasa — bugun
     * @param  bool  $allowStartedSlot  faqat haftalik darsni avtomatik yaratish (MaterializeSeries) uchun: server
     *                                  kechiksa ham boshlangan juftlikka yaratiladi — talabalar havolani oladi.
     *                                  Qo'lda (forma, "Har hafta takrorlash" bilan ham) — boshlangan juftlik yopiq.
     * @param  bool  $lecture  ma'ruza: bir uchrashuvda bir nechta guruh, hisobotda alohida
     */
    public function handle(User $teacher, Group|iterable $groups, ?LessonSlot $slot, ?string $topic, ?ZoomHost $host = null, ?Carbon $date = null, ?int $seriesId = null, bool $allowStartedSlot = false, bool $lecture = false): Lesson
    {
        $groups = self::normalizeGroups($groups, $lecture);
        [$startsAt, $endsAt] = self::window($slot, null, $date, allowStarted: $allowStartedSlot);

        foreach ($groups as $group) {
            if (! $group->is_active) {
                throw ValidationException::withMessages(['group_id' => $groups->count() > 1
                    ? __(':group guruhi nofaol — unga dars yaratib boʻlmaydi.', ['group' => $group->name])
                    : __('Bu guruh nofaol — unga dars yaratib boʻlmaydi.')]);
            }
        }
        if ($host && (! $host->is_active || ! $host->zoomAccount?->is_active)) {
            throw ValidationException::withMessages(['host_id' => __('Tanlangan Zoom host nofaol.')]);
        }

        self::assertGroupsFree($groups, $startsAt, $endsAt);

        // Ikki o'qituvchi bir guruhga turli hostlar bilan bir vaqtda dars yaratsa — ikkinchisi
        // guruh bandligini birinchisi tugagach ko'radi (host qulfi buni to'smaydi).
        // Ma'ruzada barcha guruhlar qulflanadi — doim ID tartibida, shuning uchun bir-birini kutib qolmaydi.
        $keys = $groups->pluck('id')->sort()->map(fn (int $id) => 'lesson-create:group:'.$id)->values()->all();
        try {
            return self::withLocks($keys, function () use ($teacher, $groups, $slot, $topic, $host, $startsAt, $endsAt, $seriesId, $lecture) {
                self::assertGroupsFree($groups, $startsAt, $endsAt);

                $candidates = $host ? collect([$host]) : HostAvailability::free($startsAt, $endsAt, $groups->first());
                if ($candidates->isEmpty()) {
                    throw ValidationException::withMessages(['host_id' => __('Bu vaqtda boʻsh Zoom host yoʻq. Boshqa vaqtni tanlang.')]);
                }

                foreach ($candidates as $candidate) {
                    $lesson = $this->tryHost($candidate, explicit: $host !== null, teacher: $teacher, groups: $groups, slot: $slot, topic: $topic, startsAt: $startsAt, endsAt: $endsAt, seriesId: $seriesId, lecture: $lecture);
                    if ($lesson) {
                        return $lesson;
                    }
                }

                throw ValidationException::withMessages(['host_id' => __('Bu vaqtda boʻsh Zoom host yoʻq. Boshqa vaqtni tanlang.')]);
            });
        } catch (LockTimeoutException) {
            throw new ExternalServiceException(__('Bu guruhga hozir boshqa dars yaratilmoqda. Bir necha soniyadan soʻng qayta urinib koʻring.'), status: 409);
        }
    }

    /**
     * @param  Group|iterable<Group>  $groups
     * @return Collection<int, Group>
     */
    public static function normalizeGroups(Group|iterable $groups, bool $lecture): Collection
    {
        $list = collect($groups instanceof Group ? [$groups] : $groups)->unique(fn (Group $group) => $group->id)->values();
        if ($list->isEmpty()) {
            throw ValidationException::withMessages(['group_id' => __('Guruhni tanlang.')]);
        }
        if (! $lecture && $list->count() > 1) {
            throw ValidationException::withMessages(['group_id' => __('Bir nechta guruh faqat maʼruzada tanlanadi.')]);
        }
        if ($list->count() > Lesson::MAX_LECTURE_GROUPS) {
            throw ValidationException::withMessages(['group_id' => __('Maʼruzaga koʻpi bilan :max ta guruh qoʻshiladi.', ['max' => Lesson::MAX_LECTURE_GROUPS])]);
        }

        return $list;
    }

    /**
     * Qulflarni berilgan tartibda olib, $callback'ni bajaradi.
     *
     * @param  list<string>  $keys
     */
    private static function withLocks(array $keys, \Closure $callback): mixed
    {
        if (! $keys) {
            return $callback();
        }
        $key = array_shift($keys);

        return Cache::lock($key, 60)->block(self::lockWait(), fn () => self::withLocks($keys, $callback));
    }

    /** Qulfni kutish vaqti, soniya. */
    private static function lockWait(): int
    {
        return (int) config('lessons.lock_wait', 10);
    }

    /**
     * Hostni qulf (lock) ostida band qiladi. Bir host bir vaqtda faqat bitta uchrashuv
     * o'tkaza oladi: ikki o'qituvchi bir vaqtda bitta hostni tanlasa, ikkinchisi band
     * xabarini oladi (yoki avtomatik tanlovda keyingi bo'sh hostga o'tadi).
     */
    /** @param  Collection<int, Group>  $groups */
    private function tryHost(ZoomHost $host, bool $explicit, User $teacher, Collection $groups, ?LessonSlot $slot, ?string $topic, Carbon $startsAt, Carbon $endsAt, ?int $seriesId, bool $lecture): ?Lesson
    {
        $lock = Cache::lock('lesson-create:host:'.$host->id, 30);
        $group = $groups->first();
        $names = $groups->pluck('name')->implode(', ');

        try {
            return $lock->block(self::lockWait(), function () use ($host, $explicit, $teacher, $groups, $group, $names, $slot, $topic, $startsAt, $endsAt, $seriesId, $lecture) {
                if (self::hostConflict($host, $startsAt, $endsAt)) {
                    if ($explicit) {
                        throw ValidationException::withMessages(['host_id' => __('Zoom host (:email) shu vaqtda boshqa darsda band.', ['email' => $host->email])]);
                    }

                    return null;
                }

                $account = $host->zoomAccount;
                $title = trim((string) $topic) !== '' ? trim((string) $topic) : ($lecture ? __('Maʼruza').': '.$names : $group->name);
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
                    'auto_end' => ZoomAutoEnd::enabled(),
                    'is_lecture' => $lecture,
                    'status' => $startsAt->isFuture() ? LessonStatus::Scheduled : LessonStatus::Live,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);

                if ($lecture) {
                    $lesson->syncGroups($groups->pluck('id'));
                }
                $lesson->setRelation('group', $group);
                $lesson->setRelation('groups', $groups);
                $lesson->setRelation('teacher', $teacher);

                Audit::log('lesson.created', $lecture ? 'Maʼruza yaratildi: :group, :time' : 'Dars yaratildi: :group, :time', $lesson, ['zoom_meeting_id' => $meeting['id'], 'host' => $host->email, 'groups' => $groups->pluck('id')->all()], $teacher, ['group' => $names, 'time' => $startsAt->format('d.m.Y H:i')]);
                // Haftalik dars uchun xabar 30 daqiqa oldin yuboriladi (lessons:remind).
                if (! $seriesId) {
                    $this->notifier->lessonCreated($lesson);
                }

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
    /**
     * Dars vaqti. Qo'lda yaratishda boshlangan juftlik tanlanmaydi (11:30 dan keyin 11:30–12:50 yopiq);
     * $allowStarted — haftalik dars uchun: juftlik tugamaguncha yaratiladi.
     */
    public static function window(?LessonSlot $slot, ?Carbon $now = null, ?Carbon $date = null, bool $allowStarted = false): array
    {
        $now = ($now ?? now())->copy()->startOfMinute();
        $day = $date ?? $now;
        if (LessonDays::isClosed($day)) {
            throw ValidationException::withMessages(['date' => LessonDays::closedMessage($day->isoWeekday())]);
        }
        if ($date && ! $date->isSameDay($now)) {
            if ($date->lessThan($now->copy()->startOfDay())) {
                throw ValidationException::withMessages(['date' => __('Oʻtgan kunga dars yaratib boʻlmaydi.')]);
            }
            if (! $slot) {
                throw ValidationException::withMessages(['slot_id' => __('Boshqa kun uchun juftlikni tanlang.')]);
            }

            return [$slot->startOn($date), $slot->startOn($date)->addMinutes(ZoomAutoEnd::minutes($slot->duration_minutes))];
        }
        if (! $slot) {
            return [$now, $now->copy()->addMinutes(ZoomAutoEnd::minutes((int) config('lessons.default_duration', 80)))];
        }

        $start = $slot->startOn($now);
        // Zoom'ni avtomatik yakunlash yoqilgan bo'lsa — dars juftlikning shu qismi (masalan, 50% = 40 daqiqa).
        $end = $slot->startOn($now)->addMinutes(ZoomAutoEnd::minutes($slot->duration_minutes));
        if ($end->lessThanOrEqualTo($now->copy()->addMinutes(5))) {
            throw ValidationException::withMessages(['slot_id' => __('“:name” vaqti tugagan. Boshqa juftlikni yoki boshqa kunni tanlang.', ['name' => $slot->label()])]);
        }
        if (! $allowStarted && $start->lessThanOrEqualTo($now)) {
            throw ValidationException::withMessages(['slot_id' => __('“:name” boshlanib boʻlgan — unga dars yaratib boʻlmaydi. Keyingi juftlikni yoki boshqa kunni tanlang.', ['name' => $slot->label()])]);
        }

        return [$start->greaterThan($now) ? $start : $now, $end];
    }

    /** Guruhda (ma'ruzalarni ham hisobga olib) shu oraliqda boshqa dars bo'lsa — o'sha dars. */
    public static function groupConflict(Group $group, Carbon $from, Carbon $to): ?Lesson
    {
        return Lesson::query()->overlapping($from, $to)->forGroup($group->id)->orderByDesc('ends_at')->first();
    }

    /** @param  Collection<int, Group>  $groups */
    private static function assertGroupsFree(Collection $groups, Carbon $from, Carbon $to): void
    {
        foreach ($groups as $group) {
            if ($conflict = self::groupConflict($group, $from, $to)) {
                $times = ['from' => $conflict->starts_at->format('H:i'), 'to' => $conflict->ends_at->format('H:i')];
                throw ValidationException::withMessages([
                    'group_id' => $groups->count() > 1
                        ? __(':group guruhida :from–:to oraligʻida boshqa dars bor.', [...$times, 'group' => $group->name])
                        : __('Bu guruhda :from–:to oraligʻida boshqa dars bor.', $times),
                ]);
            }
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
