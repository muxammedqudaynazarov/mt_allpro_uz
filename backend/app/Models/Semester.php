<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
class Semester extends Model
{
    use HasFactory;

    public const AUTUMN = 'autumn';

    public const SPRING = 'spring';

    protected $fillable = ['academic_year_id', 'type', 'name', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /** "Kuzgi semestr" / "Bahorgi semestr". */
    public function label(): string
    {
        return match ($this->type) {
            self::AUTUMN => __('Kuzgi semestr'),
            self::SPRING => __('Bahorgi semestr'),
            default => (string) $this->name,
        };
    }

    /** "2026–2027, Kuzgi semestr" */
    public function fullLabel(): string
    {
        return trim(($this->academicYear?->name ? $this->academicYear->name.', ' : '').$this->label());
    }

    /** Sana qaysi semestrga to'g'ri keladi. */
    public static function forDate(Carbon $date): ?self
    {
        return self::query()->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->first();
    }

    /** Semestr sanalari o'zgargach, darslar qayta biriktiriladi. */
    public function reassignLessons(): void
    {
        Lesson::query()->where('semester_id', $this->id)->update(['semester_id' => null]);
        Lesson::query()->whereNull('semester_id')
            ->whereBetween('starts_at', [$this->starts_on->copy()->startOfDay(), $this->ends_on->copy()->endOfDay()])
            ->update(['semester_id' => $this->id]);
    }

    /** Bugun davom etayotgan semestr (bo'lmasa — eng oxirgi boshlangani). */
    public static function current(): ?self
    {
        $today = today()->toDateString();

        return self::query()->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)->first()
            ?? self::query()->whereDate('starts_on', '<=', $today)->orderByDesc('starts_on')->first();
    }

    public function scopeOverlapping(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from);
    }
}
