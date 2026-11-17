<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zoom hisobotidagi bitta kirish-chiqish (xom ma'lumot). Talabalar birlashtirilganda
 * davomat shu yozuvlardan qayta hisoblanadi.
 *
 * @property Carbon $joined_at
 * @property Carbon $left_at
 */
class ZoomParticipation extends Model
{
    public $timestamps = false;

    protected $fillable = ['lesson_id', 'student_id', 'is_host', 'meeting_uuid', 'name', 'email', 'zoom_user_id', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return [
            'is_host' => 'boolean',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
