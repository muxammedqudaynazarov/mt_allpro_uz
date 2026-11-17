<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * So'rov tilini aniqlaydi: avval frontend yuborgan X-Locale sarlavhasi,
 * bo'lmasa foydalanuvchi profilidagi til, bo'lmasa APP_LOCALE.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = (array) config('app.supported_locales', ['kaa', 'uz']);
        $locale = $request->header('X-Locale');

        if (! in_array($locale, $supported, true)) {
            $locale = $request->user()?->locale;
        }

        App::setLocale(in_array($locale, $supported, true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
