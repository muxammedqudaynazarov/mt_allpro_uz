<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Hemis\HemisOAuth;
use App\Services\Telegram\TelegramBot;
use Illuminate\Http\JsonResponse;

/** Login sahifasi uchun ochiq sozlamalar (maxfiy qiymatlarsiz). */
class ConfigController extends Controller
{
    public function __invoke(HemisOAuth $hemis, TelegramBot $telegram): JsonResponse
    {
        return response()->json(['data' => [
            'app_name' => config('app.name'),
            'university' => config('lessons.university'),
            'hemis_enabled' => $hemis->enabled(),
            'telegram_enabled' => $telegram->enabled(),
            'telegram_bot_username' => $telegram->username(),
            'default_duration' => (int) config('lessons.default_duration'),
        ]]);
    }
}
