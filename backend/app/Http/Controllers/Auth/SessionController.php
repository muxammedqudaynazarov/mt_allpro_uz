<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    /**
     * Zaxira kirish: faqat paroli o'rnatilgan faol administratorlar uchun
     * (parol `php artisan app:create-admin` buyrug'i bilan beriladi).
     */
    public function store(Request $request): MeResource
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ], [], ['login' => 'login', 'password' => 'parol']);

        $key = 'login:'.Str::lower($data['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Juda koʻp urinish. '.RateLimiter::availableIn($key).' soniyadan soʻng qayta urinib koʻring.',
            ]);
        }

        $user = User::where('login', Str::lower($data['login']))->first();
        $valid = $user && $user->password && Hash::check($data['password'], $user->password);

        if (! $valid || ! $user->isAdmin() || $user->status !== UserStatus::Active) {
            RateLimiter::hit($key, 60);
            // Bir xil xabar: login mavjudligini oshkor qilmaymiz.
            throw ValidationException::withMessages(['login' => 'Login yoki parol notoʻgʻri.']);
        }

        RateLimiter::clear($key);
        Auth::guard('web')->login($user, (bool) ($data['remember'] ?? false));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log('auth.login_password', "Login/parol orqali kirdi: {$user->name}", $user, actor: $user);

        return new MeResource($user);
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
