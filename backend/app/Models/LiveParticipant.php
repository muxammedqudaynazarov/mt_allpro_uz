<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Efirdagi darsning qatnashchisi (Zoom webhook'laridan). Dars tugagach aniq
 * davomat Zoom hisobotidan olinadi — bu jadval faqat "hozir kim darsda" uchun.
 *
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 */
class LiveParticipant extends Model
{
    use MassPrunable;

    public $timestamps = false;

    /** Dars tugagach aniq davomat Zoom hisobotidan olinadi — jonli yozuvlar 7 kun saqlanadi. */
    public function prunable(): Builder
    {
        return static::query()->where('joined_at', '<', now()->subDays(7));
    }

    protected $fillable = ['lesson_id', 'participant_key', 'name', 'email', 'is_host', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return ['is_host' => 'boolean', 'joined_at' => 'datetime', 'left_at' => 'datetime'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
