<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Talabaning bitta darsdagi yakuniy qatnashuvi (zoom_participations'dan hisoblanadi).
 *
 * @property Carbon $first_joined_at
 * @property Carbon $last_left_at
 */
class LessonAttendance extends Model
{
    public $timestamps = false;

    protected $fillable = ['lesson_id', 'student_id', 'first_joined_at', 'last_left_at', 'seconds', 'late_seconds', 'sessions'];

    protected function casts(): array
    {
        return [
            'first_joined_at' => 'datetime',
            'last_left_at' => 'datetime',
            'seconds' => 'integer',
            'late_seconds' => 'integer',
            'sessions' => 'integer',
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
