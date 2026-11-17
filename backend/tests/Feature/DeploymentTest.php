<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->artisan('app:check', ['--url' => 'mt.regofis.uz'])
            ->expectsOutputToContain('APP_URL sayt manziliga mos')
            ->assertSuccessful();
    }
}
