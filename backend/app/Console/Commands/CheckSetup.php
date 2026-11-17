<?php

namespace App\Console\Commands;

use App\Enums\LessonStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Middleware\DetectUntrustedProxy;
use App\Models\Lesson;
use App\Models\User;
use App\Models\ZoomAccount;
use App\Services\Hemis\HemisOAuth;
use App\Services\Telegram\TelegramBot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * O'rnatishni tekshirish: .env, baza, papka huquqlari, HEMIS, Zoom, Telegram, navbat.
 * Server www-data nomidan ishlagani uchun shu foydalanuvchi bilan ishga tushiring:
 *
 *   sudo -u www-data php artisan app:check --url=https://mt.regofis.uz
 */
class CheckSetup extends Command
{
    protected $signature = 'app:check
        {--url= : Sayt brauzerda ochiladigan manzil (APP_URL shunga teng boʻlishi kerak)}
        {--frontend= : Yigʻilgan frontend papkasi (standart: ../frontend/dist)}';

    protected $description = 'Oʻrnatish sozlamalarini tekshirish (.env, baza, papkalar, HEMIS, Zoom, Telegram)';

    private int $errors = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->application();
        $database = $this->database();
        $this->directories();
        $this->hemis();
        if ($database) {
            $this->administrators();
            $this->zoom();
        }
        $this->telegram();
        if ($database) {
            $this->queue();
        }

        $this->newLine();
        if ($this->errors) {
            $this->line("<fg=red>Xatolar: {$this->errors}</>, ogohlantirishlar: {$this->warnings}. Xatolarni tuzatib, keyin: php artisan optimize");

            return self::FAILURE;
        }
        $this->line("<info>Xato yoʻq</info>, ogohlantirishlar: {$this->warnings}.");

        return self::SUCCESS;
    }

    private function application(): void
    {
        $this->section('Ilova');

        $appUrl = rtrim((string) config('app.url'), '/');
        $host = parse_url($appUrl, PHP_URL_HOST);
        $https = parse_url($appUrl, PHP_URL_SCHEME) === 'https';
        $frontendHost = parse_url((string) config('app.frontend_url'), PHP_URL_HOST);

        $env = $this->laravel->environmentFilePath();
        $this->check(is_readable($env), '.env faylini oʻqish mumkin', file_exists($env)
            ? '.env faylini oʻqib boʻlmaydi. Tuzatish: sudo chgrp www-data .env && sudo chmod 640 .env'
            : '.env fayli yoʻq. Namuna: cp .env.example .env');
        $this->line("  APP_URL: {$appUrl}");
        if ($expected = $this->option('url')) {
            $expectedHost = parse_url(str_contains($expected, '://') ? $expected : 'https://'.$expected, PHP_URL_HOST);
            $this->check($expectedHost === $host, 'APP_URL sayt manziliga mos', "APP_URL ({$host}) sayt manzili ({$expectedHost}) bilan bir xil emas. .env da APP_URL va FRONTEND_URL ni https://{$expectedHost} qiling.");
        }
        $this->check(filled(config('app.key')), 'APP_KEY oʻrnatilgan', 'APP_KEY boʻsh. Buyruq: php artisan key:generate');
        // Yo'lda ishlash (dist.karsu.uz/zoom): cookie yo'li sayt yo'liga mos bo'lishi kerak.
        $path = rtrim((string) parse_url($appUrl, PHP_URL_PATH), '/');
        if ($path !== '') {
            $this->line("  Yoʻl: {$path}");
            $this->check(rtrim((string) config('session.path'), '/') === $path, 'SESSION_PATH sayt yoʻliga mos', "Sayt {$path} yoʻlida, lekin SESSION_PATH=".config('session.path').". .env da SESSION_PATH={$path} qiling.", warning: true);
        }
        $this->frontendBuild($path);
        $this->proxy();
        $this->check($frontendHost === $host, 'FRONTEND_URL va APP_URL bir domenda', "FRONTEND_URL ({$frontendHost}) APP_URL ({$host}) bilan bir xil boʻlishi kerak: HEMIS orqali kirgandan keyin shu manzilga qaytiladi.");
        $this->check(! config('session.secure') || $https, 'Cookie sozlamasi APP_URL ga mos', 'SESSION_SECURE_COOKIE=true, lekin APP_URL https emas — brauzer cookie saqlamaydi va kirish ishlamaydi. SSL oʻrnating yoki SESSION_SECURE_COOKIE=false qiling.');
        $this->check($https || app()->isLocal(), 'HTTPS', 'APP_URL http:// bilan boshlanadi. Serverda SSL (https) oʻrnatish tavsiya etiladi.', warning: true);
        $this->check(! config('app.debug') || app()->isLocal(), 'APP_DEBUG=false', 'APP_DEBUG=true — xato tafsilotlari hammaga koʻrinadi. Serverda false qiling.', warning: true);
        $this->check(config('app.locale') === 'kaa', 'APP_LOCALE=kaa', 'APP_LOCALE='.config('app.locale').' — asosiy til qoraqalpoqcha boʻlishi uchun APP_LOCALE=kaa qiling.', warning: true);
    }

    /** dist.karsu.uz boshqa serverdan proksi qilsa: uning IP manzili TRUSTED_PROXIES da bo'lishi kerak. */
    private function proxy(): void
    {
        $proxies = (string) (is_array($value = config('trustedproxy.proxies')) ? implode(',', $value) : $value);
        $this->line('  TRUSTED_PROXIES: '.($proxies !== '' ? $proxies : '—'));

        try {
            $seen = Cache::get(DetectUntrustedProxy::CACHE_KEY);
        } catch (Throwable) {
            return;
        }
        if (! is_array($seen) || ! isset($seen['ip'])) {
            return;
        }
        $at = Carbon::parse((string) $seen['at'])->timezone(config('app.timezone'))->format('d.m.Y H:i');
        $this->check(false, '', "Proksi soʻrovlari {$seen['ip']} manzilidan kelgan ({$at}), lekin u TRUSTED_PROXIES da yoʻq — havolalar va kirish buziladi."
            ."\n    Bu dist.karsu.uz serveri boʻlsa: .env da TRUSTED_PROXIES={$seen['ip']}, keyin php artisan optimize");
    }

    /** Frontend APP_URL dagi yo'l uchun yig'ilganmi (aks holda sahifa oq ekran bo'lib qoladi). */
    private function frontendBuild(string $path): void
    {
        $dir = rtrim((string) ($this->option('frontend') ?: dirname(base_path()).'/frontend/dist'), '/');
        $index = @file_get_contents($dir.'/index.html');
        if ($index === false) {
            $this->check(false, '', "Frontend yigʻilmagan ({$dir}/index.html yoʻq). Buyruq: cd ../frontend && npm ci && npm run build", warning: true);

            return;
        }
        if (! preg_match('#\bsrc="([^"]*)/assets/[^"]+\.js"#', $index, $match)) {
            return;
        }
        $built = rtrim($match[1], '/');
        $this->check($built === $path, 'Frontend '.($path ?: '/').' yoʻli uchun yigʻilgan', 'Frontend '.($built ?: '/').' yoʻli uchun yigʻilgan, sayt esa '.($path ?: '/')
            .' da — sahifa ochilmaydi. Qayta yigʻing: deploy/deploy.sh yoʻlni APP_URL dan oʻzi oladi, qoʻlda: VITE_BASE_PATH='.($path ?: '').'/ npm run build');
    }

    private function database(): bool
    {
        $this->section('Baza');

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->check(false, '', 'Bazaga ulanib boʻlmadi: '.$e->getMessage().' (.env dagi DB_* qiymatlarini tekshiring)');

            return false;
        }
        $this->check(true, 'Bazaga ulanish ('.DB::connection()->getDriverName().')', '');

        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            $this->check(false, '', 'Jadvallar yaratilmagan. Buyruq: php artisan migrate --force');

            return false;
        }
        $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);
        $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());
        $this->check(! $pending, 'Barcha migratsiyalar bajarilgan', count($pending).' ta migratsiya bajarilmagan. Buyruq: php artisan migrate --force');

        return ! $pending;
    }

    private function directories(): void
    {
        $this->section('Papkalar ('.(function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '?') : get_current_user()).' nomidan)');

        $paths = [storage_path('logs'), storage_path('framework/cache'), storage_path('framework/sessions'), storage_path('framework/views'), base_path('bootstrap/cache')];
        $paths = [...$paths, ...array_map('strval', File::glob(storage_path('logs/*.log')))];
        $blocked = array_values(array_filter($paths, fn (string $path) => file_exists($path) && ! is_writable($path)));
        $missing = array_values(array_filter($paths, fn (string $path) => ! file_exists($path)));

        $this->check(! $missing, 'storage va bootstrap/cache papkalari mavjud', 'Papka yoʻq: '.implode(', ', $missing));
        $this->check(! $blocked, 'storage va bootstrap/cache yozish mumkin', "Yozib boʻlmaydi:\n      ".implode("\n      ", $blocked)
            ."\n    Tuzatish: sudo chown -R www-data:www-data storage bootstrap/cache"
            ."\n    Artisan buyruqlarini doim www-data nomidan ishga tushiring: sudo -u www-data php artisan ...");
    }

    private function hemis(): void
    {
        $this->section('HEMIS');

        $expected = rtrim((string) config('app.url'), '/').'/auth/hemis/callback';
        $this->line("  HEMIS'da roʻyxatdan oʻtkaziladigan Redirect URI: {$expected}");

        if (! HemisOAuth::make()->enabled()) {
            $this->check(false, '', 'HEMIS_BASE_URL, HEMIS_CLIENT_ID yoki HEMIS_CLIENT_SECRET boʻsh — HEMIS orqali kirish oʻchiq.', warning: true);

            return;
        }
        $this->check(true, 'HEMIS kalitlari kiritilgan', '');
        $actual = (string) config('services.hemis.redirect_uri');
        $this->check($actual === $expected, 'HEMIS_REDIRECT_URI toʻgʻri', "HEMIS_REDIRECT_URI={$actual} — {$expected} boʻlishi kerak.");
    }

    private function administrators(): void
    {
        $this->section('Administrator');

        $count = User::withRole(Role::SuperAdmin)->where('status', UserStatus::Active)->count();
        $this->check($count > 0, "Faol administrator: {$count}", 'Administrator yoʻq. Buyruq: php artisan app:create-admin');
    }

    private function zoom(): void
    {
        $this->section('Zoom');

        $count = ZoomAccount::where('is_active', true)->count();
        $this->check($count > 0, "Faol Zoom akkauntlar: {$count}", 'Faol Zoom akkaunt yoʻq — saytda: Sozlamalar → Zoom akkauntlar.', warning: true);

        // Boshlangan darslar har daqiqada tekshirilishi kerak — tekshirilmagan bo'lsa, cron ishlamayapti.
        $unchecked = Lesson::query()
            ->whereNotNull('zoom_meeting_id')
            ->where('status', '!=', LessonStatus::Cancelled->value)
            ->whereBetween('starts_at', [now()->subHours(6), now()->subMinutes(5)])
            ->whereNull('zoom_checked_at')
            ->count();
        $this->check($unchecked === 0, 'Zoom holati kuzatilmoqda (scheduler ishlayapti)', "{$unchecked} ta dars Zoom'da tekshirilmagan — scheduler (cron) ishlamayapti. Cron'ga qoʻshing: * * * * * www-data php ".base_path('artisan').' schedule:run', warning: true);

        $failed = Lesson::query()->whereNull('attendance_synced_at')->whereNotNull('attendance_error')->latest('ends_at')->first();
        $this->check(! $failed, 'Davomat Zoom hisobotidan olinmoqda', 'Davomat olinmadi ('.$failed?->starts_at->format('d.m H:i').'): '.$failed?->attendance_error, warning: true);
    }

    private function telegram(): void
    {
        $this->section('Telegram');

        if (! TelegramBot::make()->enabled()) {
            $this->check(false, '', 'TELEGRAM_BOT_TOKEN boʻsh — Telegram xabarlari yuborilmaydi.', warning: true);

            return;
        }
        $username = (string) config('services.telegram.bot_username');
        $this->check($username !== '' && ! str_starts_with($username, '@'), 'TELEGRAM_BOT_USERNAME toʻgʻri', 'TELEGRAM_BOT_USERNAME botning @ belgisiz nomi boʻlishi kerak (masalan: dist_karsu_uz_bot).');
        $this->check(filled(config('services.telegram.webhook_secret')), 'TELEGRAM_WEBHOOK_SECRET kiritilgan', 'TELEGRAM_WEBHOOK_SECRET boʻsh. Yaratish: openssl rand -hex 32, keyin: php artisan telegram:set-webhook');
    }

    private function queue(): void
    {
        $this->section('Navbat');

        if (config('queue.default') !== 'database') {
            $this->line('  QUEUE_CONNECTION='.config('queue.default'));

            return;
        }
        $stuck = DB::table('jobs')->where('created_at', '<', now()->subMinutes(5)->getTimestamp())->count();
        $this->check($stuck === 0, 'Navbatda kutib qolgan vazifa yoʻq', "{$stuck} ta vazifa 5 daqiqadan beri kutmoqda — navbat ishlovchisi (supervisor) ishlamayapti. Tekshirish: sudo supervisorctl status", warning: true);
        $failed = DB::table('failed_jobs')->count();
        $this->check($failed === 0, 'Bajarilmagan vazifa yoʻq', "{$failed} ta vazifa xato bilan tugagan. Koʻrish: php artisan queue:failed", warning: true);
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>");
    }

    private function check(bool $ok, string $success, string $failure, bool $warning = false): void
    {
        if ($ok) {
            $this->line("  <info>✓</info> {$success}");
        } elseif ($warning) {
            $this->warnings++;
            $this->line("  <fg=yellow>!</> {$failure}");
        } else {
            $this->errors++;
            $this->line("  <fg=red>✗</> {$failure}");
        }
    }
}
