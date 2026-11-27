<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foydalanuvchining tizimdagi bir "tashrifi": kirgan vaqt, oxirgi faollik va chiqqan vaqt.
 * Uzoq tanaffusdan (UserPresence::VISIT_GAP_MINUTES) keyin qaytsa — yangi tashrif.
 */
class UserVisit extends Model
{
    use Prunable;

    public const LOGOUT = 'logout';

    public const IDLE = 'idle';

    /** Tarix shuncha kun saqlanadi. */
    public const KEEP_DAYS = 180;

    public $timestamps = false;

    protected $fillable = ['user_id', 'started_at', 'last_seen_at', 'ended_at', 'end_reason', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'last_seen_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::query()->where('started_at', '<', now()->subDays(self::KEEP_DAYS));
    }

    /** Qurilma: "Chrome, Windows". */
    public function device(): ?string
    {
        $ua = (string) $this->user_agent;
        if ($ua === '') {
            return null;
        }
        $browser = match (true) {
            str_contains($ua, 'Telegram') => 'Telegram',
            str_contains($ua, 'YaBrowser') => 'Yandex',
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        return implode(', ', array_filter([$browser, $os])) ?: null;
    }
}
