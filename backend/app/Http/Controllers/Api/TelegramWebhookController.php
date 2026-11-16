<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Telegram\TelegramBot;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Telegram webhook: faqat "/start <token>" buyrug'i bilan hisobni ulaydi.
 * So'rov haqiqatan Telegram'dan kelganini X-Telegram-Bot-Api-Secret-Token
 * sarlavhasi orqali tekshiramiz.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBot $bot): JsonResponse
    {
        $secret = (string) config('services.telegram.webhook_secret');
        abort_if($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        $message = $request->input('message');
        if (! is_array($message) || ($message['chat']['type'] ?? null) !== 'private') {
            return response()->json(['ok' => true]);
        }

        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim((string) ($message['text'] ?? ''));
        $site = rtrim((string) config('app.frontend_url'), '/');

        if (! preg_match('/^\/start(?:@\w+)?(?:\s+([A-Za-z0-9]{32}))?$/', $text, $match)) {
            $bot->send($chatId, "Bu bot Masofaviy taʼlim tizimi xabarlarini yuboradi.\n\nHisobingizni ulash uchun tizimga kiring va «Profil» sahifasidagi «Telegram’ni ulash» tugmasini bosing:\n{$site}/profile");

            return response()->json(['ok' => true]);
        }

        $userId = isset($match[1]) ? Cache::pull('telegram-link:'.$match[1]) : null;
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            $bot->send($chatId, "Havola eskirgan yoki notoʻgʻri. Tizimdagi «Profil» sahifasidan yangi havola oling:\n{$site}/profile");

            return response()->json(['ok' => true]);
        }

        DB::transaction(function () use ($user, $chatId, $message) {
            // Bitta Telegram hisob faqat bitta xodimga ulanadi.
            User::where('telegram_chat_id', $chatId)->where('id', '!=', $user->id)->update(['telegram_chat_id' => null, 'telegram_username' => null]);
            $user->forceFill([
                'telegram_chat_id' => $chatId,
                'telegram_username' => $message['from']['username'] ?? null,
            ])->save();
        });

        Audit::log('telegram.linked', "Telegram ulandi: {$user->name}", $user, actor: $user);
        $bot->send($chatId, '<b>Hisobingiz ulandi</b>, '.e($user->firstName()).".\n\nEndi yaratgan darslaringiz havolalari shu yerga keladi.");

        return response()->json(['ok' => true]);
    }
}
