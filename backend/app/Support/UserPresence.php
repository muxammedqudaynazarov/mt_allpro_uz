<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Onlayn/oflayn holat va kirish tarixi. Sayt ochiq va ko'rinib turgan bo'lsa, brauzer har
 * daqiqada "ping" yuboradi; har qanday API so'rovi ham faollik hisoblanadi.
 */
final class UserPresence
{
    /** Shuncha vaqt faollik bo'lmasa — oflayn. */
    public const ONLINE_SECONDS = 180;

    /** Shundan uzoq tanaffusdan keyin qaytsa — yangi tashrif (oldingisi oxirgi faollikda tugagan). */
    public const VISIT_GAP_MINUTES = 15;

    /** Bazaga yozish oralig'i (har so'rovda emas). */
    private const WRITE_EVERY_SECONDS = 60;

    public static function touch(User $user, Request $request): void
    {
        $now = now();
        if ($user->last_seen_at?->gt($now->copy()->subSeconds(self::WRITE_EVERY_SECONDS)) && $user->online_until?->gt($now)) {
            return;
        }

        // Parallel so'rovlar ikki tashrif ochib qo'ymasligi uchun; band bo'lsa — keyingi so'rovda.
        Cache::lock('presence:'.$user->id, 10)->get(function () use ($user, $request, $now) {
            $visit = UserVisit::query()->where('user_id', $user->id)->whereNull('ended_at')->latest('last_seen_at')->first();
            if ($visit && $visit->last_seen_at->gt($now->copy()->subMinutes(self::VISIT_GAP_MINUTES))) {
                $visit->forceFill(['last_seen_at' => $now])->save();
            } else {
                $visit?->forceFill(['ended_at' => $visit->last_seen_at, 'end_reason' => UserVisit::IDLE])->save();
                self::open($user, $request, $now);
            }
            self::mark($user, $now, $now->copy()->addSeconds(self::ONLINE_SECONDS));
        });
    }

    /** Tizimga kirdi: yangi tashrif. */
    public static function login(User $user, Request $request): void
    {
        $now = now();
        self::closeOpen($user, null);
        self::open($user, $request, $now);
        self::mark($user, $now, $now->copy()->addSeconds(self::ONLINE_SECONDS));
    }

    /** Tizimdan chiqdi. */
    public static function logout(User $user): void
    {
        $now = now();
        self::closeOpen($user, $now);
        self::mark($user, $now, $now);
    }

    public static function isOnline(User $user): bool
    {
        return (bool) $user->online_until?->isFuture();
    }

    /** Ochiq tashriflarni yopadi: chiqishda — hozir, aks holda — oxirgi faollikda (faol emas). */
    private static function closeOpen(User $user, ?Carbon $at): void
    {
        UserVisit::query()->where('user_id', $user->id)->whereNull('ended_at')->get()->each(fn (UserVisit $visit) => $visit->forceFill([
            'ended_at' => $at ?? $visit->last_seen_at,
            'end_reason' => $at ? UserVisit::LOGOUT : UserVisit::IDLE,
        ])->save());
    }

    private static function open(User $user, Request $request, Carbon $now): void
    {
        UserVisit::create([
            'user_id' => $user->id,
            'started_at' => $now,
            'last_seen_at' => $now,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
        ]);
    }

    /** updated_at'ga tegmasdan. */
    private static function mark(User $user, Carbon $seen, Carbon $until): void
    {
        DB::table('users')->where('id', $user->id)->update(['last_seen_at' => $seen, 'online_until' => $until]);
        $user->forceFill(['last_seen_at' => $seen, 'online_until' => $until])->syncOriginalAttributes(['last_seen_at', 'online_until']);
    }
}
