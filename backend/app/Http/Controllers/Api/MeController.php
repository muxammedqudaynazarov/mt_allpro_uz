<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Services\Telegram\TelegramBot;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MeController extends Controller
{
    public function show(Request $request): MeResource
    {
        return new MeResource($request->user()->load('managedBy'));
    }

    /**
     * Telegram'ni ulash uchun bir martalik havola (15 daqiqa amal qiladi).
     * Eski tizimdagi "Telegram ID'ni @getmyid_bot orqali bilib, qo'lda kiritish"
     * o'rniga xodim o'zi bitta tugma bilan ulaydi.
     */
    public function telegramLink(Request $request, TelegramBot $telegram): JsonResponse
    {
        abort_unless($telegram->enabled(), 422, 'Telegram bot sozlanmagan.');

        $token = Str::random(32);
        Cache::put('telegram-link:'.$token, $request->user()->id, now()->addMinutes(15));

        return response()->json(['data' => ['url' => $telegram->deepLink($token), 'expires_at' => now()->addMinutes(15)->toIso8601String()]]);
    }

    public function telegramUnlink(Request $request): Response
    {
        $request->user()->forceFill(['telegram_chat_id' => null, 'telegram_username' => null])->save();
        Audit::log('telegram.unlinked', 'Telegram uzildi');

        return response()->noContent();
    }
}
