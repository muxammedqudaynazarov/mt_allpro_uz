<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ZoomAccount;
use App\Services\Zoom\WebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Zoom Event Subscriptions qabul qiluvchi manzil: /api/zoom/webhook/{akkaunt}.
 *
 * Har bir so'rov imzosi (x-zm-signature) akkauntning Secret Token'i bilan tekshiriladi,
 * URL tasdiqlash (endpoint.url_validation) so'rovi ham. Aks holda javobdagi
 * encryptedToken orqali har qanday matnning HMAC'ini olish — ya'ni imzoni soxtalashtirish mumkin bo'lardi.
 */
class ZoomWebhookController extends Controller
{
    /** Eski so'rovni qayta yuborish (replay) hujumidan himoya. */
    private const MAX_AGE_SECONDS = 300;

    /**
     * Bir IP'dan shuncha noto'g'ri imzoli so'rov kelsa — u 10 daqiqaga bloklanadi.
     * Zoom so'rovlari doim to'g'ri imzolangan; soxta so'rovlar esa logni to'ldira olmaydi.
     */
    private const MAX_BAD_ATTEMPTS = 30;

    private const BAD_ATTEMPTS_WINDOW = 600;

    public function __invoke(Request $request, ZoomAccount $zoomAccount, WebhookHandler $handler): JsonResponse|Response
    {
        $secret = (string) $zoomAccount->webhook_secret;
        if ($secret === '') {
            abort(404);
        }

        $badKey = 'zoom-webhook-bad:'.$request->ip();
        if (RateLimiter::tooManyAttempts($badKey, self::MAX_BAD_ATTEMPTS)) {
            return response()->json(['message' => 'Too many invalid requests'], 429);
        }

        $body = $request->getContent();
        $timestamp = (string) $request->header('x-zm-request-timestamp', '');
        $signature = (string) $request->header('x-zm-signature', '');
        $expected = 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$body, $secret);

        if ($timestamp === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::MAX_AGE_SECONDS || ! hash_equals($expected, $signature)) {
            $attempts = RateLimiter::hit($badKey, self::BAD_ATTEMPTS_WINDOW);
            // Logga faqat birinchi urinish va bloklash yoziladi — soxta so'rovlar oqimi diskni to'ldirmaydi.
            if ($attempts === 1 || $attempts === self::MAX_BAD_ATTEMPTS) {
                Log::warning($attempts === 1 ? 'Zoom webhook: imzo notoʻgʻri' : 'Zoom webhook: IP 10 daqiqaga bloklandi', ['account' => $zoomAccount->id, 'ip' => $request->ip()]);
            }

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $data = json_decode($body, true);
        if (! is_array($data) || ! isset($data['event'])) {
            return response()->json(['message' => 'Bad payload'], 400);
        }

        if ($zoomAccount->webhook_received_at === null || $zoomAccount->webhook_received_at->lt(now()->subMinute())) {
            $zoomAccount->forceFill(['webhook_received_at' => now()])->saveQuietly();
        }

        if ($data['event'] === 'endpoint.url_validation') {
            $plain = (string) ($data['payload']['plainToken'] ?? '');

            return response()->json(['plainToken' => $plain, 'encryptedToken' => hash_hmac('sha256', $plain, $secret)]);
        }

        $handler->handle($zoomAccount, (string) $data['event'], (array) ($data['payload'] ?? []), self::downloadToken($data));

        return response()->noContent();
    }

    /** Zoom yuklash tokeni hodisaning ildizida keladi (ba'zi versiyalarda payload ichida). */
    private static function downloadToken(array $data): ?string
    {
        $token = $data['download_token'] ?? $data['payload']['download_token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }
}
