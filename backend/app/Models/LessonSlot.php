<?php

namespace App\Models;

use Database\Factories\LessonSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LessonSlot extends Model
{
    /** @use HasFactory<LessonSlotFactory> */
    use HasFactory;

    protected $fillable = ['name', 'starts_at', 'duration_minutes', 'sort', 'is_active'];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Berilgan kun uchun paraning boshlanish vaqti. */
    public function startOn(?Carbon $day = null): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->starts_at));

        return ($day ?? now())->copy()->setTime($hour, $minute);
    }

    public function endOn(?Carbon $day = null): Carbon
    {
        return $this->startOn($day)->addMinutes($this->duration_minutes);
    }

    public function endsAtLabel(): string
    {
        return $this->endOn()->format('H:i');
    }
}
