<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionAndStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_use_fallback_password_login(): void
    {
        $admin = User::factory()->superAdmin()->withPassword('admin')->create();

        $this->postJson('/api/auth/login', ['login' => 'ADMIN', 'password' => 'secret-password-123'])
            ->assertOk()
            ->assertJsonPath('data.id', $admin->id);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_teacher_cannot_use_password_login(): void
    {
        User::factory()->withPassword('teacher')->create();

        $this->postJson('/api/auth/login', ['login' => 'teacher', 'password' => 'secret-password-123'])
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', 'Login yoki parol notoʻgʻri.');
        $this->assertGuest();
    }

    public function test_password_login_is_rate_limited(): void
    {
        User::factory()->superAdmin()->withPassword('admin')->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['login' => 'admin', 'password' => 'wrong'])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', ['login' => 'admin', 'password' => 'secret-password-123'])
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', fn ($message) => str_starts_with($message, 'Juda koʻp urinish'));
        $this->assertGuest();
    }

    public function test_guest_gets_401_json(): void
    {
        $this->getJson('/api/me')->assertUnauthorized()->assertJsonPath('message', 'Tizimga kirish talab qilinadi.');
    }

    public function test_blocked_user_session_is_terminated(): void
    {
        $user = User::factory()->blocked()->create();

        $this->actingAsUser($user)->getJson('/api/me')->assertUnauthorized()->assertJsonPath('code', 'blocked');
    }

    public function test_pending_user_can_see_profile_but_not_work(): void
    {
        $user = User::factory()->pending()->create();

        $this->actingAsUser($user)->getJson('/api/me')->assertOk()->assertJsonPath('data.status', 'pending');
        $this->actingAsUser($user)->getJson('/api/dashboard')->assertForbidden()->assertJsonPath('code', 'pending');
        $this->actingAsUser($user)->getJson('/api/lessons/options')->assertForbidden();
    }

    public function test_me_exposes_roles_and_permissions_without_secrets(): void
    {
        $user = User::factory()->admin()->create(['telegram_chat_id' => '12345']);

        $this->actingAsUser($user)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.roles', ['admin', 'teacher'])
            ->assertJsonPath('data.telegram.linked', true)
            ->assertJsonMissingPath('data.telegram_chat_id')
            ->assertJsonMissingPath('data.password');
    }

    public function test_security_headers_are_present(): void
    {
        $this->getJson('/api/config')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertJsonPath('data.hemis_enabled', true)
            ->assertJsonPath('data.telegram_bot_username', 'mt_test_bot');
    }
}
