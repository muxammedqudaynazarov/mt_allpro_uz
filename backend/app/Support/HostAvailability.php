<?php

namespace App\Support;

use App\Models\Group;
use App\Models\Lesson;
use App\Models\ZoomHost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Berilgan vaqt oralig'ida qaysi Zoom hostlar bo'shligini aniqlaydi. Tartib:
 * guruhning standart hosti → Licensed hostlar → nomi bo'yicha.
 */
final class HostAvailability
{
    /** @return Collection<string, Carbon> "akkaunt|email" => band bo'lgan darsning tugash vaqti */
    public static function busy(Carbon $from, Carbon $to): Collection
    {
        return Lesson::query()
            ->overlapping($from, $to)
            ->get(['zoom_account_id', 'zoom_host_email', 'ends_at'])
            ->groupBy(fn (Lesson $lesson) => $lesson->zoom_account_id.'|'.mb_strtolower($lesson->zoom_host_email))
            ->map(fn (Collection $lessons) => $lessons->max('ends_at'));
    }

    /**
     * @return Collection<int, array{host: ZoomHost, busy_until: ?Carbon, default: bool}>
     */
    public static function options(Carbon $from, Carbon $to, ?Group $group = null): Collection
    {
        $busy = self::busy($from, $to);
        $default = $group ? ZoomHost::forGroup($group) : null;

        return ZoomHost::query()
            ->usable()
            ->with('zoomAccount')
            ->get()
            ->map(fn (ZoomHost $host) => [
                'host' => $host,
                'busy_until' => $busy->get($host->zoom_account_id.'|'.$host->email),
                'default' => $default?->is($host) ?? false,
            ])
            ->sortBy([
                fn ($a, $b) => (int) $b['default'] <=> (int) $a['default'],
                fn ($a, $b) => (int) $b['host']->isLicensed() <=> (int) $a['host']->isLicensed(),
                fn ($a, $b) => strcmp($a['host']->name, $b['host']->name),
            ])
            ->values();
    }

    /** Bo'sh hostlar, tavsiya etilgani birinchi. @return Collection<int, ZoomHost> */
    public static function free(Carbon $from, Carbon $to, ?Group $group = null): Collection
    {
        return self::options($from, $to, $group)->whereNull('busy_until')->pluck('host')->values();
    }

    /** @return array<string, mixed> */
    public static function present(array $option): array
    {
        $host = $option['host'];

        return [
            'id' => $host->id,
            'name' => $host->name,
            'email' => $host->email,
            'account' => $host->zoomAccount?->name,
            'licensed' => $host->isLicensed(),
            'default' => $option['default'],
            'busy' => $option['busy_until'] !== null,
            'busy_until' => $option['busy_until']?->toIso8601String(),
        ];
    }
}
