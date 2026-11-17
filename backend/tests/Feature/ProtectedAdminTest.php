<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** .env HEMIS_ADMIN_IDS: himoyalangan administrator rolini, holatini va hisobini yo'qotmaydi. */
class ProtectedAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.hemis.admin_ids' => ['1574']]);
    }

    private function hemisLogin(string $hemisId = '1574'): TestResponse
    {
        Http::fake([
            'hemis.test/oauth/access-token' => Http::response(['access_token' => 'hemis-token', 'token_type' => 'Bearer']),
            'hemis.test/oauth/api/user*' => Http::response([
                'id' => (int) $hemisId, 'type' => 'employee', 'name' => 'QUDAYNAZAROV MUXAMMED SADIQ ULI',
                'firstname' => 'MUXAMMED', 'surname' => 'QUDAYNAZAROV', 'patronymic' => 'SADIQ ULI', 'university_id' => 42,
            ]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        return $this->withSession(['hemis_oauth_state' => 'state-123'])->get('/auth/hemis/callback?code=abc&state=state-123');
    }

    public function test_role_status_and_account_are_restored_on_hemis_login(): void
    {
        $owner = User::factory()->create(['hemis_id' => '1574', 'roles' => ['teacher'], 'status' => UserStatus::Blocked]);
        $owner->delete();

        $this->hemisLogin()->assertRedirect('http://localhost/');

        $owner = User::query()->findOrFail($owner->id);
        $this->assertTrue($owner->isSuperAdmin());
        $this->assertContains('teacher', $owner->roles);
        $this->assertSame(UserStatus::Active, $owner->status);
        $this->assertAuthenticatedAs($owner);
        $this->assertTrue(AuditLog::query()->where('action', 'user.protected_restored')->exists());
    }

    public function test_first_login_creates_active_administrator(): void
    {
        config(['services.hemis.auto_register' => false]);

        $this->hemisLogin()->assertRedirect('http://localhost/');

        $owner = User::query()->where('hemis_id', '1574')->firstOrFail();
        $this->assertTrue($owner->isSuperAdmin());
        $this->assertSame(UserStatus::Active, $owner->status);
    }

    public function test_other_users_are_not_affected(): void
    {
        $other = User::factory()->create(['hemis_id' => '7777', 'roles' => ['teacher'], 'status' => UserStatus::Blocked]);

        $this->hemisLogin('7777')->assertRedirect('http://localhost/login?error=blocked');
        $this->assertFalse($other->fresh()->isSuperAdmin());
    }

    public function test_administrators_cannot_demote_block_delete_or_rebind_protected_admin(): void
    {
        $owner = User::factory()->superAdmin()->create(['hemis_id' => '1574']);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAsUser($admin)->patchJson("/api/users/{$owner->id}", ['roles' => ['teacher']])->assertStatus(422)->assertJsonValidationErrors('roles');
        $this->actingAsUser($admin)->patchJson("/api/users/{$owner->id}", ['status' => 'blocked'])->assertStatus(422)->assertJsonValidationErrors('status');
        $this->actingAsUser($admin)->patchJson("/api/users/{$owner->id}", ['hemis_id' => '9999'])->assertStatus(422)->assertJsonValidationErrors('hemis_id');
        $this->actingAsUser($admin)->deleteJson("/api/users/{$owner->id}")->assertStatus(422);

        $owner->refresh();
        $this->assertTrue($owner->isSuperAdmin());
        $this->assertSame(UserStatus::Active, $owner->status);
        $this->assertSame('1574', $owner->hemis_id);
        $this->assertFalse($owner->trashed());

        // Boshqa sozlamalar (rol qo'shish, kalendar) o'zgaraveradi; ro'yxatda belgilangan.
        $this->actingAsUser($admin)->patchJson("/api/users/{$owner->id}", ['roles' => ['teacher', 'super_admin']])->assertOk()->assertJsonPath('data.protected', true);
    }

    /** Hali kirmagan (Administrator bo'lmagan) himoyalangan hisobni tahrirlash va unga rol qo'shish mumkin. */
    public function test_protected_user_without_admin_role_can_still_be_edited(): void
    {
        $owner = User::factory()->create(['hemis_id' => '1574', 'roles' => ['teacher']]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAsUser($admin)->patchJson("/api/users/{$owner->id}", ['roles' => ['teacher'], 'can_manage_calendar' => false])->assertOk();
        $this->actingAsUser($admin)->patchJson("/api/users/{$owner->id}", ['roles' => ['teacher', 'super_admin']])->assertOk();
        $this->assertTrue($owner->fresh()->isSuperAdmin());
    }

    public function test_backup_password_login_restores_protected_admin(): void
    {
        $owner = User::factory()->create(['hemis_id' => '1574', 'login' => 'owner', 'password' => 'Secret-pass-2026', 'roles' => ['teacher'], 'status' => UserStatus::Blocked]);

        $this->withHeaders(['Referer' => 'http://localhost/login'])->postJson('/api/auth/login', ['login' => 'owner', 'password' => 'Secret-pass-2026'])->assertOk();

        $this->assertTrue($owner->fresh()->isSuperAdmin());
        $this->assertSame(UserStatus::Active, $owner->fresh()->status);
    }

    public function test_check_command_shows_protected_admin(): void
    {
        User::factory()->create(['hemis_id' => '1574', 'last_name' => 'Qudaynazarov', 'first_name' => 'Muxammed', 'roles' => ['teacher']]);

        Artisan::call('app:check');
        $this->assertStringContainsString('Himoyalangan administrator 1574 (QUDAYNAZAROV MUXAMMED) hozir faol Administrator emas', Artisan::output());
    }
}
