<?php

use App\Http\Middleware\EnsureAccountStatus;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // React SPA bilan cookie-sessiya orqali ishlash (Sanctum).
        $middleware->statefulApi();
        $middleware->append(SecurityHeaders::class);
        // Til: global darajada X-Locale sarlavhasidan (autentifikatsiya xatolari ham tarjima qilinadi),
        // guruh darajasida esa sessiya ochilgach foydalanuvchi profilidagi tildan.
        $middleware->append(SetLocale::class);
        $middleware->appendToGroup('api', SetLocale::class);
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));
        $middleware->alias([
            'account' => EnsureAccountStatus::class,
            'role' => EnsureRole::class,
        ]);
        // Mehmon foydalanuvchini React login sahifasiga yo'naltiramiz (API uchun 401 JSON qaytadi).
        $middleware->redirectGuestsTo(fn () => rtrim((string) config('app.frontend_url'), '/').'/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => __('Tizimga kirish talab qilinadi.')], 401);
            }
        });
        // Framework'ning inglizcha standart xabarlari o'rniga tanlangan tildagi matn.
        $exceptions->render(function (AccessDeniedHttpException|AuthorizationException $e, Request $request) {
            if (($request->is('api/*') || $request->expectsJson()) && in_array($e->getMessage(), ['', 'This action is unauthorized.'], true)) {
                return response()->json(['message' => __('Bu amal uchun ruxsatingiz yoʻq.')], 403);
            }
        });
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => __('Juda koʻp urinish. Bir daqiqadan soʻng qayta urinib koʻring.')], 429, $e->getHeaders());
            }
        });
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __('Maʼlumot topilmadi.')], 404);
            }
        });
    })->create();
