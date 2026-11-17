<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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
