<?php

namespace App\Services\Zoom;

use App\Models\Lesson;
use App\Models\LiveParticipant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * "Hozir darsda kim bor" ro'yxati.
 *
 * 1) Webhook (asosiy yo'l, har qanday tarifda): Zoom har bir kirish/chiqishni darhol
 *    yuboradi, ro'yxat bazadan olinadi.
 * 2) Dashboard API (zaxira, Business+ tarif): webhook sozlanmagan bo'lsa, Zoom'dan
 *    ko'pi bilan daqiqada bir marta so'raladi.
 */
class LiveMonitor
{
    public const SOURCE_WEBHOOK = 'webhook';

    public const SOURCE_DASHBOARD = 'dashboard';

    public const SOURCE_NONE = 'none';

    public function __construct(private readonly ZoomClient $zoom) {}

    /** @return array{source: string, reason: ?string, checked_at: string, participants: list<array<string, mixed>>} */
    public function snapshot(Lesson $lesson): array
    {
        $account = $lesson->zoomAccount;
        $now = now();

        if ($account?->webhook_secret && $account->webhook_received_at) {
            return [
                'source' => self::SOURCE_WEBHOOK,
                'reason' => null,
                'checked_at' => $now->toIso8601String(),
                'participants' => $this->fromWebhooks($lesson, $now),
            ];
        }

        if (! $account || ! $lesson->zoom_meeting_id || $lesson->zoom_state !== Lesson::ZOOM_STARTED) {
            return ['source' => self::SOURCE_NONE, 'reason' => null, 'checked_at' => $now->toIso8601String(), 'participants' => []];
        }

        return Cache::remember('zoom:live:'.$lesson->id, now()->addSeconds(55), function () use ($lesson, $account) {
            $reason = null;
            $rows = $this->zoom->liveParticipants($account, $lesson->zoom_meeting_id, $reason);
            $now = now();

            return [
                'source' => $rows === null ? self::SOURCE_NONE : self::SOURCE_DASHBOARD,
                'reason' => $reason,
                'checked_at' => $now->toIso8601String(),
                'participants' => $rows === null ? [] : $this->fromDashboard($rows, $now),
            ];
        });
    }

    /** @return list<array<string, mixed>> */
    private function fromWebhooks(Lesson $lesson, Carbon $now): array
    {
        return LiveParticipant::query()
            ->where('lesson_id', $lesson->id)
            ->orderBy('joined_at')
            ->get()
            ->groupBy('participant_key')
            ->map(fn (Collection $rows) => self::row(
                $rows->last()->name,
                $rows->last()->email,
                (bool) $rows->contains('is_host', true),
                $rows->min('joined_at'),
                $rows->contains(fn (LiveParticipant $row) => $row->left_at === null) ? null : $rows->max('left_at'),
                $rows->sum(fn (LiveParticipant $row) => (int) $row->joined_at->diffInSeconds($row->left_at ?? $now)),
            ))
            ->pipe(fn (Collection $rows) => self::sorted($rows));
    }

    /** @return list<array<string, mixed>> */
    private function fromDashboard(array $rows, Carbon $now): array
    {
        return collect($rows)
            ->groupBy(fn (array $row) => $row['key'] !== '' ? $row['key'] : 'name:'.mb_strtolower($row['name']))
            ->map(function (Collection $rows) use ($now) {
                $joins = $rows->map(fn (array $row) => Carbon::parse($row['join_time'])->setTimezone(config('app.timezone')));
                $present = $rows->contains(fn (array $row) => $row['leave_time'] === null);
                $left = $present ? null : $rows->map(fn (array $row) => Carbon::parse($row['leave_time'])->setTimezone(config('app.timezone')))->max();
                $seconds = $rows->sum(fn (array $row) => (int) Carbon::parse($row['join_time'])->diffInSeconds($row['leave_time'] ? Carbon::parse($row['leave_time']) : $now));

                return self::row($rows->last()['name'], $rows->last()['email'] ?: null, false, $joins->min(), $left, $seconds);
            })
            ->pipe(fn (Collection $rows) => self::sorted($rows));
    }

    /** @return array<string, mixed> */
    private static function row(string $name, ?string $email, bool $isHost, Carbon $joined, ?Carbon $left, int $seconds): array
    {
        return [
            'name' => $name,
            'email' => $email,
            'is_host' => $isHost,
            'present' => $left === null,
            'joined_at' => $joined->toIso8601String(),
            'left_at' => $left?->toIso8601String(),
            'minutes' => intdiv(max(0, $seconds), 60),
        ];
    }

    /** Darsdagilar birinchi (host eng yuqorida), keyin chiqib ketganlar; har birida ism bo'yicha. */
    private static function sorted(Collection $rows): array
    {
        return $rows->sortBy([
            fn ($a, $b) => (int) $b['present'] <=> (int) $a['present'],
            fn ($a, $b) => (int) $b['is_host'] <=> (int) $a['is_host'],
            fn ($a, $b) => strcasecmp($a['name'], $b['name']),
        ])->values()->all();
    }
}
