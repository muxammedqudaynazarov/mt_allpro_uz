<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zoom bulutidagi bitta yozuv fayli va uning serverdagi nusxasi.
 *
 * @property Carbon|null $recording_start
 * @property Carbon|null $recording_end
 */
class Recording extends Model
{
    public const PENDING = 'pending';

    public const SKIPPED = 'skipped';

    public const DOWNLOADED = 'downloaded';

    public const FAILED = 'failed';

    /** Video va audio. Chat, transkript va boshqa matnli fayllar faqat dars sahifasida ko'rinadi. */
    public const MEDIA_TYPES = ['MP4', 'M4A'];

    protected $fillable = [
        'lesson_id', 'zoom_account_id', 'zoom_meeting_id', 'zoom_meeting_uuid', 'zoom_file_id', 'file_type', 'file_extension',
        'recording_type', 'recording_start', 'recording_end', 'size', 'download_url', 'download_token', 'download_token_expires_at', 'status', 'path', 'attempts', 'error',
        'downloaded_at', 'zoom_deleted_at',
    ];

    protected $hidden = ['download_url', 'download_token'];

    protected function casts(): array
    {
        return [
            'download_url' => 'encrypted',
            'download_token' => 'encrypted',
            'download_token_expires_at' => 'datetime',
            'size' => 'integer',
            'attempts' => 'integer',
            'recording_start' => 'datetime',
            'recording_end' => 'datetime',
            'downloaded_at' => 'datetime',
            'zoom_deleted_at' => 'datetime',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function zoomAccount(): BelongsTo
    {
        return $this->belongsTo(ZoomAccount::class);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('status', self::PENDING)
            ->orWhere(fn (Builder $q) => $q->where('status', self::FAILED)->where('attempts', '<', (int) config('recordings.max_attempts'))));
    }

    public function absolutePath(): ?string
    {
        return $this->path ? rtrim((string) config('recordings.path'), '/').'/'.$this->path : null;
    }

    public function isVideo(): bool
    {
        return $this->file_type === 'MP4';
    }

    public function scopeMedia(Builder $query): Builder
    {
        return $query->whereIn('file_type', self::MEDIA_TYPES);
    }

    public function mimeType(): string
    {
        return match (strtolower((string) $this->file_extension)) {
            'mp4' => 'video/mp4',
            'm4a' => 'audio/mp4',
            'vtt' => 'text/vtt; charset=UTF-8',
            'txt' => 'text/plain; charset=UTF-8',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
    }
}
