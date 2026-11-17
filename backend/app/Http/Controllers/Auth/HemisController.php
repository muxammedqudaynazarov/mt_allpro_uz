<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Exceptions\ExternalServiceException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Hemis\HemisOAuth;
use App\Services\Hemis\HemisUser;
use App\Support\Audit;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HEMIS orqali kirish. Bu marshrutlar "web" guruhida — OAuth "state"
 * qiymati sessiyada saqlanadi va CSRF hujumlaridan himoya qiladi.
 */
class HemisController extends Controller
{
    public function __construct(private readonly HemisOAuth $hemis, private readonly Notifier $notifier) {}

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->hemis->enabled()) {
            return $this->toLogin('hemis_failed');
        }

        $state = Str::random(40);
        $request->session()->put('hemis_oauth_state', $state);

        return redirect()->away($this->hemis->authorizeUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('hemis_oauth_state');

        if ($request->filled('error')) {
            return $this->toLogin('hemis_denied');
        }
        if (! is_string($expected) || ! hash_equals($expected, (string) $request->query('state'))) {
            return $this->toLogin('state');
        }
        if (! $request->filled('code')) {
            return $this->toLogin('hemis_failed');
        }

        try {
            $profile = $this->hemis->fetchUser($this->hemis->exchangeCode((string) $request->query('code')));
        } catch (ExternalServiceException $e) {
            Log::warning('HEMIS orqali kirish muvaffaqiyatsiz', ['error' => $e->getMessage()]);

            return $this->toLogin('hemis_failed');
        }

        if ($profile->type && $this->hemis->allowedTypes() && ! in_array($profile->type, $this->hemis->allowedTypes(), true)) {
            return $this->toLogin('not_employee');
        }
        if (($university = $this->hemis->universityId()) && $profile->universityId && $profile->universityId !== $university) {
            return $this->toLogin('university');
        }

        $user = $this->findUser($profile);
        $isNew = false;
        // .env HEMIS_ADMIN_IDS: rolini olib tashlashgan, bloklashgan yoki o'chirishgan bo'lsa ham — tiklanadi.
        $protected = User::isProtectedHemisId($profile->id);

        if (! $user) {
            if (! $this->hemis->autoRegister() && ! $protected) {
                return $this->toLogin('registration_closed');
            }
            $user = new User(['roles' => [Role::Teacher->value], 'status' => UserStatus::Pending]);
            $isNew = true;
        }
        $restored = $protected && $user->grantProtectedAdmin() && ! $isNew;

        if ($user->status === UserStatus::Blocked) {
            Audit::log('auth.blocked', 'Bloklangan foydalanuvchi kirishga urindi: :name', $user, actor: $user, replace: ['name' => $user->displayName()]);

            return $this->toLogin('blocked');
        }

        DB::transaction(function () use ($user, $profile) {
            $user->fill([
                'name' => User::normalizeName($profile->name) ?? $user->name,
                'first_name' => User::normalizeName($profile->firstName) ?? $user->first_name,
                'last_name' => User::normalizeName($profile->lastName) ?? $user->last_name,
                'middle_name' => User::normalizeName($profile->middleName) ?? $user->middle_name,
                'hemis_id' => $profile->id,
                // Shaxsga doir ma'lumotlardan faqat F.I.Sh., HEMIS ID va rasm saqlanadi.
                'picture_url' => $profile->picture,
                'last_login_at' => now(),
            ])->save();
        });

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        Audit::log($isNew ? 'auth.registered' : 'auth.login', $isNew ? 'HEMIS orqali roʻyxatdan oʻtdi: :name' : 'HEMIS orqali kirdi: :name', $user, actor: $user, replace: ['name' => $user->displayName()]);
        if ($restored) {
            Audit::log('user.protected_restored', 'Himoyalangan administrator huquqlari tiklandi: :name', $user, actor: $user, replace: ['name' => $user->displayName()]);
        }
        if ($isNew && ! $user->isActive()) {
            $this->notifier->userPending($user);
        }

        return redirect()->away($this->frontend($user->isActive() ? '/' : '/pending'));
    }

    /**
     * Mavjud foydalanuvchini topish: avval HEMIS ID, keyin UUID bo'yicha.
     * Eski tizimdan ko'chirilgan (hali HEMIS orqali hech kirmagan) xodimlar
     * qo'shimcha ravishda HEMIS logini bo'yicha topiladi — bu bog'lanish faqat
     * bir marta, birinchi kirishda bo'ladi.
     */
    private function findUser(HemisUser $profile): ?User
    {
        $user = User::withTrashed()->where('hemis_id', $profile->id)->first();

        // Eski tizimdan ko'chirilgan va hali kirmagan hisob HEMIS login bo'yicha topiladi (login saqlanmaydi).
        if (! $user && $profile->login) {
            $user = User::withTrashed()
                ->where('hemis_id', $profile->login)
                ->whereNull('last_login_at')
                ->first();
        }

        // O'chirilgan xodim qayta kira olmaydi.
        if ($user?->trashed()) {
            $user->status = UserStatus::Blocked;
        }

        return $user;
    }

    private function toLogin(string $error): RedirectResponse
    {
        return redirect()->away($this->frontend('/login?error='.$error));
    }

    private function frontend(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
