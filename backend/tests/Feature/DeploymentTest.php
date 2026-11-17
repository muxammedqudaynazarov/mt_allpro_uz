<?php

namespace Tests\Feature;

use App\Http\Middleware\DetectUntrustedProxy;
use App\Models\Lesson;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Serverdagi .env xatolari kirishni buzmasligi va app:check ularni topishi. */
class DeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_works_on_own_domain_even_if_env_lists_another(): void
    {
        // SANCTUM_STATEFUL_DOMAINS da faqat "localhost" (phpunit.xml), sayt esa boshqa domenda ochilgan.
        $this->assertNotContains('mt.regofis.uz', config('sanctum.stateful'));
        $admin = User::factory()->superAdmin()->withPassword('admin')->create();

        $this->postJson('https://mt.regofis.uz/api/auth/login', ['login' => 'admin', 'password' => 'secret-password-123'], [
            'Origin' => 'https://mt.regofis.uz',
            'Referer' => 'https://mt.regofis.uz/login',
        ])->assertOk()->assertJsonPath('data.id', $admin->id);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_from_foreign_site_gets_clear_error(): void
    {
        User::factory()->superAdmin()->withPassword('admin')->create();

        $this->postJson('https://mt.regofis.uz/api/auth/login', ['login' => 'admin', 'password' => 'secret-password-123'], [
            'Origin' => 'https://evil.example',
            'Referer' => 'https://evil.example/',
        ])->assertStatus(400)->assertJsonPath('message', 'Sessiya ochilmadi. Tizimni brauzerda oʻz manzili orqali oching.');

        $this->assertGuest('web');
    }

    public function test_check_command_reports_wrong_domain_and_cookie_settings(): void
    {
        User::factory()->superAdmin()->create();
        config([
            'app.url' => 'http://mt.allpro.uz',
            'app.frontend_url' => 'https://mt.allpro.uz',
            'session.secure' => true,
        ]);

        $this->artisan('app:check', ['--url' => 'https://mt.regofis.uz'])
            ->expectsOutputToContain('APP_URL (mt.allpro.uz) sayt manzili (mt.regofis.uz) bilan bir xil emas')
            ->expectsOutputToContain('SESSION_SECURE_COOKIE=true, lekin APP_URL https emas')
            ->expectsOutputToContain('Redirect URI: http://mt.allpro.uz/auth/hemis/callback')
            ->assertFailed();
    }

    public function test_check_command_passes_on_correct_setup(): void
    {
        // CI'da .env fayli yo'q: testning o'z vaqtinchalik .env fayli beriladi.
        $dir = sys_get_temp_dir().'/mt-env-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/.env', "APP_ENV=testing\n");
        $this->app->useEnvironmentPath($dir);
        $this->beforeApplicationDestroyed(function () use ($dir) {
            @unlink($dir.'/.env');
            @rmdir($dir);
        });

        User::factory()->superAdmin()->create();
        config([
            'app.url' => 'https://mt.regofis.uz',
            'app.frontend_url' => 'https://mt.regofis.uz',
            'services.hemis.redirect_uri' => 'https://mt.regofis.uz/auth/hemis/callback',
            'services.telegram.bot_username' => 'dist_karsu_uz_bot',
            'services.telegram.webhook_secret' => 'secret',
        ]);

        file_put_contents($dir.'/index.html', '<script type="module" crossorigin src="/assets/index-abc.js"></script>');
        $this->beforeApplicationDestroyed(fn () => @unlink($dir.'/index.html'));

        $this->artisan('app:check', ['--url' => 'mt.regofis.uz', '--frontend' => $dir])
            ->expectsOutputToContain('APP_URL sayt manziliga mos')
            ->expectsOutputToContain('Frontend / yoʻli uchun yigʻilgan')
            ->assertSuccessful();
    }

    /** dist.karsu.uz/zoom ga ko'chganda frontend eski (/) yo'l bilan qolib ketsa — xato. */
    public function test_check_command_reports_frontend_built_for_other_path(): void
    {
        $dir = sys_get_temp_dir().'/mt-dist-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/index.html', '<script type="module" crossorigin src="/assets/index-abc.js"></script>');
        $this->beforeApplicationDestroyed(function () use ($dir) {
            @unlink($dir.'/index.html');
            @rmdir($dir);
        });
        config(['app.url' => 'https://dist.karsu.uz/zoom', 'app.frontend_url' => 'https://dist.karsu.uz/zoom', 'session.path' => '/zoom']);

        $this->assertSame(1, Artisan::call('app:check', ['--frontend' => $dir]));
        $output = Artisan::output();
        $this->assertStringContainsString('Frontend / yoʻli uchun yigʻilgan, sayt esa /zoom da', $output);
        $this->assertStringContainsString('VITE_BASE_PATH=/zoom/ npm run build', $output);

        file_put_contents($dir.'/index.html', '<script type="module" crossorigin src="/zoom/assets/index-abc.js"></script>');
        Artisan::call('app:check', ['--frontend' => $dir]);
        $output = Artisan::output();
        $this->assertStringContainsString('✓ Frontend /zoom yoʻli uchun yigʻilgan', $output);
        $this->assertStringNotContainsString('sahifa ochilmaydi', $output);
    }

    /** dist.karsu.uz/zoom, 1) nginx shu serverda: SCRIPT_NAME=/zoom/index.php — marshrutlar /zoom ostida ishlaydi. */
    public function test_app_works_under_path_prefix(): void
    {
        $this->call('GET', '/zoom/api/config', [], [], [], [
            'SCRIPT_NAME' => '/zoom/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
            'HTTP_ACCEPT' => 'application/json',
        ])->assertOk()->assertJsonStructure(['data' => ['app_name']]);
    }

    /**
     * 2) Proksi: prefiks olib tashlanib keladi, X-Forwarded-Prefix bilan — yaratilgan havolalar /zoom bilan.
     * (Alohida test: test muhitida bitta testdagi ikkinchi prefiksli so'rov holat saqlab qoladi;
     * haqiqiy HTTP'da har so'rov mustaqil — tekshirilgan.)
     */
    /** Proksi orqali kelgan so'rov: yozuvlar ro'yxatidagi video havolasini qaytaradi. */
    private function recordingUrlVia(string $remoteAddr): string
    {
        $teacher = User::factory()->create();
        $lesson = Lesson::factory()->finished()->create(['user_id' => $teacher->id]);
        Recording::create([
            'lesson_id' => $lesson->id, 'zoom_meeting_id' => '1', 'zoom_meeting_uuid' => 'u', 'zoom_file_id' => 'f',
            'file_type' => 'MP4', 'status' => Recording::DOWNLOADED, 'path' => 'x.mp4', 'size' => 1,
        ]);

        return (string) $this->actingAsUser($teacher)
            ->withServerVariables(['REMOTE_ADDR' => $remoteAddr])
            ->withHeaders(['X-Forwarded-Prefix' => '/zoom', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'dist.karsu.uz'])
            ->getJson("/api/lessons/{$lesson->id}/recordings")
            ->assertOk()
            ->json('data.0.url');
    }

    /** TRUSTED_PROXIES konfiguratsiyadan o'qiladi (config keshlanganda ham — env() emas). */
    public function test_trusted_proxy_ip_comes_from_config(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.5']);

        $this->assertStringStartsWith('https://dist.karsu.uz/zoom/api/recordings/', $this->recordingUrlVia('10.0.0.5'));
        $this->assertNull(Cache::get(DetectUntrustedProxy::CACHE_KEY));
    }

    /** dist.karsu.uz IP'si TRUSTED_PROXIES da bo'lmasa: sarlavhalarga ishonilmaydi, app:check aniq IP'ni aytadi. */
    public function test_untrusted_proxy_is_reported_by_check_command(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.5']);

        $this->assertStringNotContainsString('dist.karsu.uz', $this->recordingUrlVia('10.0.0.9'));
        $this->assertSame('10.0.0.9', Cache::get(DetectUntrustedProxy::CACHE_KEY)['ip'] ?? null);

        $this->assertSame(1, Artisan::call('app:check'));
        $output = Artisan::output();
        $this->assertStringContainsString('Proksi soʻrovlari 10.0.0.9 manzilidan kelgan', $output);
        $this->assertStringContainsString('TRUSTED_PROXIES=10.0.0.9', $output);
    }

    /**
     * .env faqat config/ fayllarida o'qilishi kerak: `php artisan optimize` dan keyin boshqa joydagi
     * env() null qaytaradi (TRUSTED_PROXIES shu sababli jimgina 127.0.0.1 ga qaytgan edi).
     */
    /** Toza klonda `php artisan optimize` (view:cache) resources/views papkasini talab qiladi. */
    public function test_views_directory_is_tracked_in_git(): void
    {
        $this->assertFileExists(resource_path('views/.gitkeep'));
    }

    public function test_env_is_read_only_in_config_files(): void
    {
        $offenders = [];
        foreach (['app', 'bootstrap', 'routes', 'database'] as $dir) {
            foreach (File::allFiles(base_path($dir)) as $file) {
                if ($file->getExtension() === 'php' && ! str_contains($file->getPathname(), '/cache/') && preg_match('/(?<![\w>:$])env\(/', $file->getContents())) {
                    $offenders[] = $file->getRelativePathname();
                }
            }
        }
        $this->assertSame([], $offenders);
    }

    public function test_generated_urls_keep_forwarded_prefix(): void
    {
        $teacher = User::factory()->create();
        $lesson = Lesson::factory()->finished()->create(['user_id' => $teacher->id]);
        Recording::create([
            'lesson_id' => $lesson->id, 'zoom_meeting_id' => '1', 'zoom_meeting_uuid' => 'u', 'zoom_file_id' => 'f',
            'file_type' => 'MP4', 'status' => Recording::DOWNLOADED, 'path' => 'x.mp4', 'size' => 1,
        ]);
        $this->actingAsUser($teacher)
            ->withHeaders(['X-Forwarded-Prefix' => '/zoom', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'dist.karsu.uz'])
            ->getJson("/api/lessons/{$lesson->id}/recordings")
            ->assertOk()
            ->assertJsonPath('data.0.url', fn (string $url) => str_starts_with($url, 'https://dist.karsu.uz/zoom/api/recordings/'));
    }
}
