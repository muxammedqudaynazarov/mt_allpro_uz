<?php

namespace App\Actions;

use App\Models\Group;
use App\Models\LessonSeries;
use App\Models\LessonSlot;
use App\Models\User;
use App\Models\ZoomHost;
use App\Support\Audit;
use App\Support\LessonDays;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Har haftalik darsni ro'yxatga oladi (Zoom uchrashuvi dars kuni yaratiladi). */
class CreateSeries
{
    /** @param  Group|iterable<Group>  $groups  guruh; ma'ruzada — bir nechta (birinchisi asosiy) */
    public function handle(User $teacher, Group|iterable $groups, LessonSlot $slot, int $weekday, ?ZoomHost $host, ?string $topic, Carbon $startsOn, ?Carbon $endsOn, User $actor, bool $lecture = false): LessonSeries
    {
        $groups = CreateLesson::normalizeGroups($groups, $lecture);
        $group = $groups->first();
        if (LessonDays::isClosedWeekday($weekday)) {
            throw ValidationException::withMessages(['weekday' => LessonDays::closedMessage($weekday)]);
        }
        foreach ($groups as $item) {
            if (! $item->is_active) {
                throw ValidationException::withMessages(['group_id' => $groups->count() > 1
                    ? __(':group guruhi nofaol — unga dars yaratib boʻlmaydi.', ['group' => $item->name])
                    : __('Bu guruh nofaol — unga dars yaratib boʻlmaydi.')]);
            }
        }
        if ($startsOn->lt(today())) {
            throw ValidationException::withMessages(['starts_on' => __('Boshlanish sanasi bugundan oldin boʻlishi mumkin emas.')]);
        }
        if ($endsOn && $endsOn->lt($startsOn)) {
            throw ValidationException::withMessages(['ends_on' => __('Tugash sanasi boshlanishidan oldin boʻlishi mumkin emas.')]);
        }

        $same = LessonSeries::query()->active()->where('weekday', $weekday)->where('lesson_slot_id', $slot->id)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $startsOn))
            ->when($endsOn, fn ($q) => $q->whereDate('starts_on', '<=', $endsOn));
        foreach ($groups as $item) {
            if ((clone $same)->forGroup($item->id)->exists()) {
                throw ValidationException::withMessages(['group_id' => $groups->count() > 1
                    ? __(':group guruhida shu kuni va juftlikda haftalik dars allaqachon bor.', ['group' => $item->name])
                    : __('Bu guruhda shu kuni va juftlikda haftalik dars allaqachon bor.')]);
            }
        }
        if ((clone $same)->where('user_id', $teacher->id)->exists()) {
            throw ValidationException::withMessages(['lesson_slot_id' => __('Oʻqituvchida shu kuni va juftlikda boshqa haftalik dars bor.')]);
        }

        $series = LessonSeries::create([
            'user_id' => $teacher->id,
            'group_id' => $group->id,
            'lesson_slot_id' => $slot->id,
            'zoom_host_id' => $host?->id,
            'weekday' => $weekday,
            'topic' => filled($topic) ? trim($topic) : null,
            'is_lecture' => $lecture,
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn?->toDateString(),
        ]);

        if ($lecture) {
            $series->syncGroups($groups->pluck('id'));
        }
        $series->setRelation('group', $group);
        $series->setRelation('groups', $groups);

        Audit::log('series.created', $lecture ? 'Haftalik maʼruza qoʻshildi: :group, :day :time' : 'Haftalik dars qoʻshildi: :group, :day :time', $series, ['teacher_id' => $teacher->id], $actor, [
            'group' => $groups->pluck('name')->implode(', '),
            'day' => self::weekdayName($weekday),
            'time' => $slot->starts_at,
        ]);

        return $series;
    }

    public static function weekdayName(int $weekday): string
    {
        return __(['', 'dushanba', 'seshanba', 'chorshanba', 'payshanba', 'juma', 'shanba', 'yakshanba'][$weekday] ?? '');
    }
}
