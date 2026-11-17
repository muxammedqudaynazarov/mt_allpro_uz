<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Jobs\SendTelegramMessage;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\User;
use App\Models\ZoomAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_cannot_reach_admin_endpoints(): void
    {
        $teacher = User::factory()->create();
        $group = Group::factory()->create();

        $this->actingAsUser($teacher)->getJson('/api/users')->assertForbidden();
        $this->actingAsUser($teacher)->getJson('/api/groups')->assertForbidden();
        $this->actingAsUser($teacher)->postJson("/api/groups/{$group->id}/toggle")->assertForbidden();
        $this->actingAsUser($teacher)->getJson('/api/zoom-accounts')->assertForbidden();
        $this->actingAsUser($teacher)->getJson('/api/reports/summary?from=2026-01-01&to=2026-01-31')->assertForbidden();
        $this->assertTrue($group->refresh()->is_active);
    }

    public function test_education_office_sees_everything_but_cannot_manage(): void
    {
        $education = User::factory()->education()->create();
        $teacher = User::factory()->create();
        $group = Group::factory()->create();
        $foreignGroup = Group::factory()->create();
        Lesson::factory()->create(['user_id' => $teacher->id, 'group_id' => $group->id]);
        Lesson::factory()->finished()->create(['group_id' => $foreignGroup->id]);

        // Ko'rish: barcha guruhlar, darslar, foydalanuvchilar, hisobotlar.
        $this->actingAsUser($education)->getJson('/api/groups')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAsUser($education)->getJson('/api/lessons')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAsUser($education)->getJson('/api/users')->assertOk();
        $this->actingAsUser($education)->getJson('/api/reports/summary?from=2026-01-01&to=2026-12-31')->assertOk();

        // Boshqarish: Zoom, juftliklar, guruh, foydalanuvchi, dars yaratish — yo'q.
        $this->actingAsUser($education)->getJson('/api/zoom-accounts')->assertForbidden();
        $this->actingAsUser($education)->postJson('/api/lesson-slots', ['name' => 'X', 'starts_at' => '10:00', 'duration_minutes' => 80])->assertForbidden();
        $this->actingAsUser($education)->getJson('/api/audit-logs')->assertForbidden();
        $this->actingAsUser($education)->postJson("/api/groups/{$group->id}/toggle")->assertForbidden();
        $this->actingAsUser($education)->putJson("/api/groups/{$group->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAsUser($education)->patchJson("/api/users/{$teacher->id}", ['status' => 'blocked'])->assertForbidden();
        $this->actingAsUser($education)->patchJson("/api/users/{$teacher->id}", ['last_name' => 'Boshqa'])->assertForbidden();
        $this->actingAsUser($education)->postJson('/api/lessons', ['group_id' => $group->id])->assertForbidden();
        $this->assertNotContains('lessons.create', $education->permissions());
    }

    public function test_only_administrator_approves_and_blocks_users(): void
    {
        Queue::fake();
        $super = User::factory()->superAdmin()->create();
        $pending = User::factory()->pending()->create(['telegram_chat_id' => '777']);

        $this->actingAsUser($super)->getJson('/api/users?status=pending')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAsUser($super)->patchJson("/api/users/{$pending->id}", ['status' => 'active'])->assertOk();
        $this->assertSame(UserStatus::Active, $pending->refresh()->status);
        Queue::assertPushed(SendTelegramMessage::class, fn ($job) => $job->chatId === '777');

        $this->actingAsUser($super)->patchJson("/api/users/{$pending->id}", ['status' => 'blocked'])->assertOk();
        $this->assertSame(UserStatus::Blocked, $pending->refresh()->status);
        $this->actingAsUser($super)->patchJson("/api/users/{$super->id}", ['status' => 'blocked'])->assertForbidden();
    }

    public function test_administrator_corrects_name_temporarily_until_next_hemis_login(): void
    {
        $super = User::factory()->superAdmin()->create();
        $teacher = User::factory()->create(['name' => 'Qudaynazarov Muxammed', 'last_name' => 'Qudaynazarov', 'first_name' => 'Muxammed']);

        $this->actingAsUser($super)->patchJson("/api/users/{$teacher->id}", ['last_name' => 'Qudaynazarov', 'first_name' => 'Muhammad', 'middle_name' => 'Aliyevich'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Qudaynazarov Muhammad Aliyevich');
    }

    public function test_administrator_controls_teacher_calendar_permission(): void
    {
        $super = User::factory()->superAdmin()->create();
        $teacher = User::factory()->create();
        $this->assertContains('calendar.manage', $teacher->permissions(), 'standart: ruxsat bor');

        $this->actingAsUser($super)->patchJson("/api/users/{$teacher->id}", ['can_manage_calendar' => false])
            ->assertOk()
            ->assertJsonPath('data.can_manage_calendar', false);
        $this->assertNotContains('calendar.manage', $teacher->refresh()->permissions());
    }

    public function test_administrator_changes_own_name_and_password(): void
    {
        $super = User::factory()->superAdmin()->withPassword('admin')->create();
        $teacher = User::factory()->create();

        $this->actingAsUser($super)->patchJson('/api/me', ['last_name' => 'Aliyev', 'first_name' => 'Vali'])->assertOk()->assertJsonPath('data.name', 'Aliyev Vali');
        $this->actingAsUser($teacher)->patchJson('/api/me', ['last_name' => 'Boshqa'])->assertForbidden();

        $this->actingAsUser($super)->putJson('/api/me/password', ['current_password' => 'wrong', 'password' => 'new-password-2026', 'password_confirmation' => 'new-password-2026'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->actingAsUser($super)->putJson('/api/me/password', ['current_password' => 'secret-password-123', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->actingAsUser($super)->putJson('/api/me/password', ['current_password' => 'secret-password-123', 'password' => 'new-password-2026', 'password_confirmation' => 'new-password-2026'])
            ->assertNoContent();
        $this->assertTrue(Hash::check('new-password-2026', $super->refresh()->password));
    }

    public function test_super_admin_changes_roles_but_not_own(): void
    {
        $super = User::factory()->superAdmin()->create();
        $teacher = User::factory()->create();

        $this->actingAsUser($super)->patchJson("/api/users/{$teacher->id}", ['roles' => ['education', 'teacher']])
            ->assertOk()->assertJsonPath('data.roles', ['education', 'teacher']);
        $this->actingAsUser($super)->patchJson("/api/users/{$super->id}", ['roles' => ['teacher']])->assertForbidden();
        $this->actingAsUser($super)->patchJson("/api/users/{$teacher->id}", ['roles' => ['admin']])->assertStatus(422);
    }

    public function test_deleting_user_keeps_lesson_history(): void
    {
        $super = User::factory()->superAdmin()->create();
        $teacher = User::factory()->create();
        Lesson::factory()->finished()->create(['user_id' => $teacher->id]);

        $this->actingAsUser($super)->deleteJson("/api/users/{$teacher->id}")->assertNoContent();

        $this->assertSoftDeleted($teacher);
        $this->assertSame(1, Lesson::count());
        $this->actingAsUser($super)->getJson('/api/lessons')->assertJsonPath('data.0.teacher.name', $teacher->name);
    }

    public function test_zoom_secret_is_encrypted_and_never_returned(): void
    {
        $super = User::factory()->superAdmin()->create();

        $response = $this->actingAsUser($super)->postJson('/api/zoom-accounts', [
            'name' => 'Asosiy', 'account_id' => 'acc', 'client_id' => 'cli', 'client_secret' => 'very-secret-value',
        ])->assertCreated();

        $this->assertStringNotContainsString('very-secret-value', $response->getContent());
        $response->assertJsonPath('data.has_secret', true);

        $account = ZoomAccount::firstOrFail();
        $this->assertNotSame('very-secret-value', $account->getRawOriginal('client_secret'));
        $this->assertSame('very-secret-value', $account->client_secret);

        // Bo'sh secret bilan tahrirlash eski qiymatni saqlaydi.
        $this->actingAsUser($super)->putJson("/api/zoom-accounts/{$account->id}", [
            'name' => 'Yangi nom', 'account_id' => 'acc', 'client_id' => 'cli', 'client_secret' => '',
        ])->assertOk();
        $this->assertSame('very-secret-value', $account->refresh()->client_secret);
    }

    public function test_zoom_account_with_groups_cannot_be_deleted(): void
    {
        $super = User::factory()->superAdmin()->create();
        $group = Group::factory()->create();

        $this->actingAsUser($super)->deleteJson("/api/zoom-accounts/{$group->zoom_account_id}")->assertStatus(422);
    }

    public function test_super_admin_creates_group(): void
    {
        $super = User::factory()->superAdmin()->create();
        $account = ZoomAccount::factory()->create();
        $payload = ['name' => '1-A1 Tarix', 'course' => 1, 'zoom_account_id' => $account->id, 'zoom_user_email' => 'Zoom_9@KarSU.uz'];

        $this->actingAsUser($super)->postJson('/api/groups', $payload)
            ->assertCreated()
            ->assertJsonPath('data.zoom_user_email', 'zoom_9@karsu.uz');
    }

    public function test_reports_summarise_lessons(): void
    {
        $super = User::factory()->superAdmin()->create();
        $teacher = User::factory()->create();
        Lesson::factory()->finished()->count(2)->create(['user_id' => $teacher->id]);
        Lesson::factory()->finished()->create(['status' => 'cancelled']);

        $from = now()->subDays(3)->format('Y-m-d');
        $to = now()->format('Y-m-d');
        $this->actingAsUser($super)->getJson("/api/reports/summary?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonPath('data.totals.lessons', 2)
            ->assertJsonPath('data.totals.minutes', 160)
            ->assertJsonPath('data.by_teacher.0.teacher.id', $teacher->id);
    }

    public function test_actions_are_written_to_audit_log(): void
    {
        $super = User::factory()->superAdmin()->create();
        $teacher = User::factory()->pending()->create();

        $this->actingAsUser($super)->patchJson("/api/users/{$teacher->id}", ['status' => 'active'])->assertOk();

        $this->actingAsUser($super)->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'user.updated')
            ->assertJsonPath('data.0.user.id', $super->id);
    }
}
