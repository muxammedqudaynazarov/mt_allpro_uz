<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Services\Telegram\TelegramBot;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class MeController extends Controller
{
    public function show(Request $request): MeResource
    {
        return new MeResource($request->user());
    }

    /**
     * Profil: interfeys tili (hamma) va ism-sharif (faqat Administrator — u HEMIS'siz,
     * login/parol bilan kirishi mumkin). Boshqalarning ismi HEMIS'dan olinadi.
     */
    public function update(Request $request): MeResource
    {
        $user = $request->user();
        $data = $request->validate([
            'locale' => ['sometimes', Rule::in((array) config('app.supported_locales', ['kaa', 'uz']))],
            'last_name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'first_name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        if (array_key_exists('locale', $data)) {
            $user->locale = $data['locale'];
        }
        if (array_intersect(['last_name', 'first_name', 'middle_name'], array_keys($data))) {
            abort_unless($user->isSuperAdmin(), 403, __('Ism-sharif HEMIS’dan olinadi.'));
            $user->fill(array_intersect_key($data, array_flip(['last_name', 'first_name', 'middle_name'])));
            $user->name = User::fullName($user->last_name, $user->first_name, $user->middle_name);
        }
        $changes = array_keys($user->getDirty());
        $user->save();
        if (array_diff($changes, ['locale'])) {
            Audit::log('profile.updated', 'Profil oʻzgartirildi: :name', $user, replace: ['name' => $user->name]);
        }

        return new MeResource($user);
    }

    /** Zaxira login paroli bor foydalanuvchi (Administrator, O'quv bo'limi) uni o'zi almashtiradi. */
    public function password(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->password !== null, 403, __('Sizda login/parol orqali kirish yoʻq.'));

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)->letters()->numbers()],
        ], [], ['current_password' => __('joriy parol'), 'password' => __('yangi parol')]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('Joriy parol notoʻgʻri.')]);
        }
        $user->forceFill(['password' => $data['password']])->save();
        Audit::log('profile.password', 'Parol almashtirildi: :name', $user, replace: ['name' => $user->name]);

        return response()->noContent();
    }

    /**
     * Telegram'ni ulash uchun bir martalik havola (15 daqiqa amal qiladi).
     * Eski tizimdagi "Telegram ID'ni @getmyid_bot orqali bilib, qo'lda kiritish"
     * o'rniga xodim o'zi bitta tugma bilan ulaydi.
     */
    public function telegramLink(Request $request, TelegramBot $telegram): JsonResponse
    {
        abort_unless($telegram->enabled(), 422, __('Telegram bot sozlanmagan.'));

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
