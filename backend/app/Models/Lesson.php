<?php

namespace App\Models;

use App\Enums\LessonStatus;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property LessonStatus $status
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $ended_at
 */
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'group_id', 'lesson_slot_id', 'zoom_account_id',
        'zoom_meeting_id', 'zoom_host_email', 'topic', 'join_url', 'start_url', 'passcode',
        'status', 'starts_at', 'ends_at', 'ended_at', 'ended_by_id',
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

    /**
     * Foydalanuvchi ko'ra oladigan darslar:
     * o'qituvchi — o'zinikini, administrator — o'zinikini va o'z guruhlaridagini, bosh administrator — hammasini.
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isSuperAdmin()) {
            return $query;
        }
        if ($viewer->isAdmin()) {
            return $query->where(fn (Builder $q) => $q
                ->where('user_id', $viewer->id)
                ->orWhereIn('group_id', Group::withTrashed()->where('admin_id', $viewer->id)->select('id')));
        }

        return $query->where('user_id', $viewer->id);
    }

    public function durationMinutes(): int
    {
        $end = $this->ended_at && $this->ended_at->lessThan($this->ends_at) ? $this->ended_at : $this->ends_at;

        return max(0, (int) round($this->starts_at->diffInMinutes($end)));
    }
}
