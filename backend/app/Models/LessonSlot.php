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

    /**
     * Ko'rsatish uchun nom. Standart nomlar ("1-juplıq", eski "1-para") joriy tilga
     * moslab chiqariladi: qoraqalpoqchada "1-juplıq", o'zbekchada "1-juftlik".
     * Administrator o'zi bergan boshqa nomlar o'zgarishsiz qoladi.
     */
    public function label(): string
    {
        if (preg_match('/^\s*(\d+)\s*-\s*(para|juftlik|juplıq)\s*$/iu', (string) $this->name, $match)) {
            return __(':number-juftlik', ['number' => $match[1]]);
        }

        return (string) $this->name;
    }

    /** Standart nom: bazada asosiy til (qoraqalpoqcha) shaklida saqlanadi. */
    public static function defaultName(int $number): string
    {
        return $number.'-juplıq';
    }

    /** Berilgan kun uchun juftlikning boshlanish vaqti. */
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
