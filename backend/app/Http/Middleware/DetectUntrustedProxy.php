<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Proksi sarlavhalari (X-Forwarded-Host/Prefix) ishonchsiz IP'dan kelsa, shu IP'ni eslab qoladi.
 * Bunday holatda Laravel sarlavhalarga ishonmaydi: havolalar /zoom siz chiqadi, kirish buziladi.
 * `php artisan app:check` saqlangan IP'ni ko'rsatib, TRUSTED_PROXIES ga nima yozishni aytadi.
 */
class DetectUntrustedProxy
{
    public const CACHE_KEY = 'proxy:untrusted';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->has('X-Forwarded-Prefix') || $request->headers->has('X-Forwarded-Host')) {
            $this->check((string) $request->server->get('REMOTE_ADDR'));
        }

        return $next($request);
    }

    private function check(string $ip): void
    {
        $proxies = config('trustedproxy.proxies');
        if ($ip === '' || $proxies === '*' || $proxies === '**') {
            return;
        }
        $list = is_array($proxies) ? $proxies : array_filter(array_map('trim', explode(',', (string) $proxies)));
        if ($list && IpUtils::checkIp($ip, $list)) {
            return;
        }

        try {
            // Har IP uchun 10 daqiqada bir marta yoziladi — noto'g'ri sozlamada har so'rov bazaga yozmaydi.
            if (Cache::add(self::CACHE_KEY.':'.$ip, true, 600)) {
                Cache::put(self::CACHE_KEY, ['ip' => $ip, 'at' => now()->toIso8601String()], now()->addDays(2));
                Log::warning("Proksi sarlavhalari ishonchsiz IP'dan keldi: {$ip}. Bu sizning proksi serveringiz bo'lsa, .env: TRUSTED_PROXIES={$ip}");
            }
        } catch (Throwable) {
            // Kesh ishlamasa ham so'rov to'xtamasin.
        }
    }
}
