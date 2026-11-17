<?php

namespace App\Models;

use App\Enums\LessonStatus;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property LessonStatus $status
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $ended_at
 * @property string|null $zoom_state
 * @property Carbon|null $zoom_started_at
 * @property Carbon|null $zoom_ended_at
 */
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    /** Zoom'dagi holat (zoom_state): hali boshlanmagan, efirda, tugagan, o'tkazilmagan. */
    public const ZOOM_WAITING = 'waiting';

    public const ZOOM_STARTED = 'started';

    public const ZOOM_ENDED = 'ended';

    public const ZOOM_MISSED = 'missed';

    protected $fillable = [
        'user_id', 'group_id', 'lesson_slot_id', 'lesson_series_id', 'zoom_account_id',
        'zoom_meeting_id', 'zoom_host_email', 'topic', 'join_url', 'start_url', 'passcode',
        'status', 'starts_at', 'ends_at', 'ended_at', 'ended_by_id',
        'zoom_state', 'zoom_started_at', 'zoom_ended_at', 'zoom_minutes', 'zoom_checked_at',
        'attendance_due_at', 'attendance_synced_at', 'attendance_attempts', 'attendance_error',
    ];

    protected $hidden = ['start_url'];

    protected function casts(): array
    {
        return [
            'status' => LessonStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'ended_at' => 'datetime',
            'start_url' => 'encrypted',
            'zoom_started_at' => 'datetime',
            'zoom_ended_at' => 'datetime',
            'zoom_minutes' => 'integer',
            'zoom_checked_at' => 'datetime',
            'attendance_due_at' => 'datetime',
            'attendance_synced_at' => 'datetime',
            'attendance_attempts' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class)->withTrashed();
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(LessonSlot::class, 'lesson_slot_id');
    }

    public function zoomAccount(): BelongsTo
    {
        return $this->belongsTo(ZoomAccount::class);
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by_id')->withTrashed();
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(LessonSeries::class, 'lesson_series_id');
    }

    public function liveParticipants(): HasMany
    {
        return $this->hasMany(LiveParticipant::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(LessonAttendance::class);
    }

    public function participations(): HasMany
    {
        return $this->hasMany(ZoomParticipation::class);
    }

    /** Qatnashgan talabalar soni ("talaba emas" deb belgilanganlarsiz). */
    public function scopeWithParticipantsCount(Builder $query): Builder
    {
        return $query->withCount(['attendances as participants_count' => fn (Builder $q) => $q
            ->whereHas('student', fn (Builder $s) => $s->where('is_ignored', false))]);
    }

    /** Host darsni jadvaldagi vaqtdan necha daqiqa keyin boshlagani (manfiy — oldinroq). */
    public function startDelayMinutes(): ?int
    {
        return $this->zoom_started_at ? (int) round($this->starts_at->diffInSeconds($this->zoom_started_at, false) / 60) : null;
    }

    /** Dars Zoom'da haqiqatda necha daqiqa davom etgani. */
    public function actualMinutes(): ?int
    {
        if ($this->zoom_minutes !== null) {
            return $this->zoom_minutes;
        }
        if ($this->zoom_started_at && $this->zoom_ended_at) {
            return max(0, (int) round($this->zoom_started_at->diffInSeconds($this->zoom_ended_at) / 60));
        }

        return null;
    }

    /**
     * Vaqtga qarab hisoblangan haqiqiy holat. Scheduler ishlamay qolsa ham
     * foydalanuvchi to'g'ri holatni ko'radi.
     */
    public function effectiveStatus(?Carbon $now = null): LessonStatus
    {
        if (! $this->status->isOpen()) {
            return $this->status;
        }
        $now ??= now();
        if ($now->greaterThanOrEqualTo($this->ends_at)) {
            return LessonStatus::Finished;
        }

        return $now->greaterThanOrEqualTo($this->starts_at) ? LessonStatus::Live : LessonStatus::Scheduled;
    }

    public function isOpen(): bool
    {
        return $this->effectiveStatus()->isOpen();
    }

    /** Ochiq (yakunlanmagan) va vaqti tugamagan darslar. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', LessonStatus::openValues())->where('ends_at', '>', now());
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->open()->where('starts_at', '<=', now());
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->open()->where('starts_at', '>', now());
    }

    public function scopeFinished(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('status', LessonStatus::Finished->value)
            ->orWhere(fn (Builder $q) => $q->whereIn('status', LessonStatus::openValues())->where('ends_at', '<=', now())));
    }

    /** Berilgan vaqt oralig'i bilan kesishadigan ochiq darslar. */
    public function scopeOverlapping(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereIn('status', LessonStatus::openValues())
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from);
    }

    /** Foydalanuvchi ko'ra oladigan darslar: o'qituvchi — o'zinikini, Administrator va O'quv bo'limi — hammasini. */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return $viewer->canViewAll() ? $query : $query->where('user_id', $viewer->id);
    }

    public function durationMinutes(): int
    {
        $end = $this->ended_at && $this->ended_at->lessThan($this->ends_at) ? $this->ended_at : $this->ends_at;

        return max(0, (int) round($this->starts_at->diffInMinutes($end)));
    }
}
