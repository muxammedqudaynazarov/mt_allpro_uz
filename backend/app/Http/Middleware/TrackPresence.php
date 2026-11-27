<?php

namespace App\Http\Middleware;

use App\Support\UserPresence;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Har bir API so'rovi — foydalanuvchi faolligi (onlayn holat va kirish tarixi). */
class TrackPresence
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // Chiqish so'rovi o'zi yopadi; impersonatsiya yo'q.
        if (($user = $request->user()) && ! $request->is('api/auth/logout')) {
            UserPresence::touch($user, $request);
        }

        return $response;
    }
}
