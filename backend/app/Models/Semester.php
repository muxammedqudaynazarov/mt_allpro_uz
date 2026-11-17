<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
class Semester extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
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
