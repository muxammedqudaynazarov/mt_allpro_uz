<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    protected $table = 'lesson_series';

    protected $fillable = ['user_id', 'group_id', 'lesson_slot_id', 'zoom_host_id', 'weekday', 'topic', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
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
