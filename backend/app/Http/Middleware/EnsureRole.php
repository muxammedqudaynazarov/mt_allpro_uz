<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Masalan: ->middleware('role:super_admin') yoki 'role:admin,super_admin'. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()?->hasRole(...$roles)) {
            return response()->json(['message' => __('Bu amal uchun ruxsatingiz yoʻq.')], 403);
        }

        return $next($request);
    }
}
