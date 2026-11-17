<?php

namespace App\Providers;

use App\Services\Hemis\HemisOAuth;
use App\Services\Telegram\TelegramBot;
use App\Services\Zoom\ZoomClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ZoomClient::class, fn () => ZoomClient::make());
        $this->app->singleton(HemisOAuth::class, fn () => HemisOAuth::make());
        $this->app->singleton(TelegramBot::class, fn () => TelegramBot::make());
    }

    public function boot(): void
    {
        // Ishlab chiqishda $fillable'da yo'q maydonni yozishga urinish darhol xato beradi.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => Password::min(12)->letters()->numbers());

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('lesson-create', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())
            ->response(fn () => response()->json(['message' => __('Juda koʻp urinish. Bir daqiqadan soʻng qayta urinib koʻring.')], 429)));
        // Universitet tarmog'ida ko'p xodim bitta IP orqali chiqadi — chegara yetarlicha keng.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('telegram', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        // Bir vaqtda bir nechta darsga o'nlab talabalar kiradi — har biri alohida hodisa.
        RateLimiter::for('zoom-webhook', fn (Request $request) => Limit::perMinute(3000)->by($request->ip()));
    }
}
