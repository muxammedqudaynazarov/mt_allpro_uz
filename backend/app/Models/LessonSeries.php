<?php

namespace App\Models;

use App\Support\LessonDays;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Har haftalik takroriy dars: shu hafta kuni, shu juftlikda.
 *
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 */
class LessonSeries extends Model
{
    use HasFactory;

    /** Zoom uchrashuvi dars boshlanishidan shuncha daqiqa oldin yaratiladi. */
    public const LEAD_MINUTES = 60;

    private const PIVOT = 'lesson_series_group';

    private const PIVOT_KEY = 'lesson_series_id';

    protected $table = 'lesson_series';

    protected $fillable = ['user_id', 'group_id', 'lesson_slot_id', 'zoom_host_id', 'weekday', 'topic', 'is_lecture', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
            'is_lecture' => 'boolean',
            'cancelled_at' => 'datetime',
            'last_error_on' => 'date',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class)->withTrashed();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, self::PIVOT)->withTrashed();
    }

    protected static function booted(): void
    {
        static::created(function (LessonSeries $series) {
            DB::table(self::PIVOT)->insertOrIgnore([self::PIVOT_KEY => $series->id, 'group_id' => $series->group_id]);
        });
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(LessonSlot::class, 'lesson_slot_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(ZoomHost::class, 'zoom_host_id');
    }

    public function skips(): HasMany
    {
        return $this->hasMany(LessonSeriesSkip::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * Haftalik darsning barcha guruhlari (ma'ruzada — bir nechta).
     *
     * @return Collection<int, Group>
     */
    public function groupList(): Collection
    {
        if (! $this->is_lecture) {
            return collect([$this->group]);
        }
        $groups = $this->relationLoaded('groups') ? $this->groups : $this->groups()->get();

        return ($groups->isEmpty() ? collect([$this->group]) : $groups)->sortBy(fn (Group $group) => sprintf('%03d|%s', $group->course, mb_strtolower($group->name)))->values();
    }

    /** @return list<int> */
    public function groupIds(): array
    {
        return $this->groupList()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    /** Guruh nomlari: "101, 102, 103". */
    public function groupsLabel(): string
    {
        return $this->groupList()->pluck('name')->implode(', ');
    }

    /** Shu guruhning haftalik darslari (ma'ruzalar ham). */
    public function scopeForGroup(Builder $query, int $groupId): Builder
    {
        $table = $this->getTable();

        return $query->where(fn (Builder $q) => $q
            ->where($table.'.group_id', $groupId)
            ->orWhereIn($table.'.id', fn ($sub) => $sub->select(self::PIVOT_KEY)->from(self::PIVOT)->where('group_id', $groupId)));
    }

    /** Guruhlarni saqlaydi; birinchisi — asosiy (group_id). */
    public function syncGroups(iterable $groupIds): void
    {
        $ids = collect($groupIds)->map(fn ($id) => (int) $id)->push((int) $this->group_id)->unique()->values()->all();
        $this->groups()->sync($ids);
        $this->unsetRelation('groups');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return $viewer->canViewAll() ? $query : $query->where('user_id', $viewer->id);
    }

    /** Berilgan kun shu jadvalga to'g'ri keladimi (hafta kuni va amal qilish muddati). */
    public function occursOn(Carbon $day): bool
    {
        return $this->is_active
            && $day->isoWeekday() === $this->weekday
            && ! LessonDays::isClosed($day)
            && $day->greaterThanOrEqualTo($this->starts_on->copy()->startOfDay())
            && ($this->ends_on === null || $day->lessThanOrEqualTo($this->ends_on->copy()->endOfDay()));
    }

    /**
     * Oraliqdagi barcha sanalar (Y-m-d).
     *
     * @return list<Carbon>
     */
    public function datesBetween(Carbon $from, Carbon $to): array
    {
        // Eski (dam olish kuniga qo'shilgan) jadval endi o'tkazilmaydi.
        if (LessonDays::isClosedWeekday((int) $this->weekday)) {
            return [];
        }
        $day = $from->copy()->startOfDay();
        $offset = ($this->weekday - $day->isoWeekday() + 7) % 7;
        $day->addDays($offset);
        $dates = [];
        while ($day->lessThanOrEqualTo($to)) {
            if ($this->occursOn($day)) {
                $dates[] = $day->copy();
            }
            $day->addWeek();
        }

        return $dates;
    }

    /** Bugundan keyingi (yoki bugungi, hali yaratilmagan) navbatdagi sana. */
    public function nextDate(?Carbon $now = null): ?Carbon
    {
        $now ??= now();
        $dates = $this->datesBetween($now->copy()->startOfDay(), $now->copy()->addWeeks(8));
        $skipped = $this->skips->pluck('date')->map(fn ($date) => Carbon::parse($date)->toDateString())->all();
        foreach ($dates as $date) {
            if (in_array($date->toDateString(), $skipped, true)) {
                continue;
            }
            if ($date->isSameDay($now) && $this->slot && $this->slot->endOn($date)->lessThanOrEqualTo($now)) {
                continue;
            }

            return $date;
        }

        return null;
    }
}
