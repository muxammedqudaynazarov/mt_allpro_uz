<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ZoomAccount;
use App\Services\Zoom\WebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

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

    public function __invoke(Request $request, ZoomAccount $zoomAccount, WebhookHandler $handler): JsonResponse|Response
    {
        $secret = (string) $zoomAccount->webhook_secret;
        if ($secret === '') {
            abort(404);
        }

        $body = $request->getContent();
        $timestamp = (string) $request->header('x-zm-request-timestamp', '');
        $signature = (string) $request->header('x-zm-signature', '');
        $expected = 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$body, $secret);

        if ($timestamp === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::MAX_AGE_SECONDS || ! hash_equals($expected, $signature)) {
            Log::warning('Zoom webhook: imzo notoʻgʻri', ['account' => $zoomAccount->id, 'ip' => $request->ip()]);

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

        $handler->handle($zoomAccount, (string) $data['event'], (array) ($data['payload'] ?? []));

        return response()->noContent();
    }
}
