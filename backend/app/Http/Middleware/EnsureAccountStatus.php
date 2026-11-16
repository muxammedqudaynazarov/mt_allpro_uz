<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloklangan foydalanuvchining sessiyasini darhol tugatadi (eski tizimda
 * bloklangan o'qituvchi bemalol kira olardi). "account:active" parametri bilan
 * esa tasdiqlanmagan xodimlarni ham to'xtatadi.
 */
class EnsureAccountStatus
{
    public function handle(Request $request, Closure $next, string $required = 'any'): Response
    {
        $user = $request->user();

        if ($user && $user->status === UserStatus::Blocked) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json(['message' => 'Hisobingiz bloklangan.', 'code' => 'blocked'], 401);
        }

        if ($required === 'active' && $user && $user->status !== UserStatus::Active) {
            return response()->json(['message' => 'Hisobingiz hali administrator tomonidan tasdiqlanmagan.', 'code' => 'pending'], 403);
        }

        return $next($request);
    }
}
