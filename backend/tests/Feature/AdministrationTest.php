<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Jobs\SendTelegramMessage;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\User;
use App\Models\ZoomAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_admin_cannot_manage_zoom_or_slots(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsUser($admin)->getJson('/api/zoom-accounts')->assertForbidden();
        $this->actingAsUser($admin)->postJson('/api/lesson-slots', ['name' => 'X', 'starts_at' => '10:00', 'duration_minutes' => 80])->assertForbidden();
        $this->actingAsUser($admin)->getJson('/api/audit-logs')->assertForbidden();
    }

    public function test_admin_sees_and_toggles_only_own_groups(): void
    {
        $admin = User::factory()->admin()->create();
        $own = Group::factory()->create(['admin_id' => $admin->id]);
        $foreign = Group::factory()->create();

        $this->actingAsUser($admin)->getJson('/api/groups')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->actingAsUser($admin)->postJson("/api/groups/{$own->id}/toggle")->assertOk()->assertJsonPath('data.is_active', false);
        $this->actingAsUser($admin)->postJson("/api/groups/{$foreign->id}/toggle")->assertForbidden();
        $this->actingAsUser($admin)->putJson("/api/groups/{$own->id}", ['name' => 'X'])->assertForbidden();
    }

    public function test_admin_approves_pending_teacher_and_becomes_responsible(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->pending()->create(['telegram_chat_id' => '777']);

        $this->actingAsUser($admin)->getJson('/api/users?status=pending')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAsUser($admin)->patchJson("/api/users/{$pending->id}", ['status' => 'active'])->assertOk();

        $pending->refresh();
        $this->assertSame(UserStatus::Active, $pending->status);
        $this->assertSame($admin->id, $pending->managed_by_id);
        Queue::assertPushed(SendTelegramMessage::class, fn ($job) => $job->chatId === '777');
    }

    public function test_admin_cannot_touch_other_admins_or_unrelated_teachers_or_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $foreignTeacher = User::factory()->create();
        $ownTeacher = User::factory()->create(['managed_by_id' => $admin->id]);

        $this->actingAsUser($admin)->patchJson("/api/users/{$otherAdmin->id}", ['status' => 'blocked'])->assertForbidden();
        $this->actingAsUser($admin)->patchJson("/api/users/{$foreignTeacher->id}", ['status' => 'blocked'])->assertForbidden();
        $this->actingAsUser($admin)->patchJson("/api/users/{$ownTeacher->id}", ['roles' => ['super_admin']])->assertForbidden();
        $this->actingAsUser($admin)->patchJson("/api/users/{$admin->id}", ['status' => 'blocked'])->assertForbidden();
        $this->actingAsUser($admin)->deleteJson("/api/users/{$ownTeacher->id}")->assertForbidden();

        $this->actingAsUser($admin)->patchJson("/api/users/{$ownTeacher->id}", ['status' => 'blocked'])->assertOk();
        $this->assertSame(UserStatus::Blocked, $ownTeacher->refresh()->status);
    }

    public function test_super_admin_changes_roles_but_not_own(): void
    {
        $super = User::factory()->superAdmin()->create();
        $teacher = User::factory()->create();

        $this->actingAsUser($super)->patchJson("/api/users/{$teacher->id}", ['roles' => ['admin', 'teacher']])
            ->assertOk()->assertJsonPath('data.roles', ['admin', 'teacher']);
        $this->actingAsUser($super)->patchJson("/api/users/{$super->id}", ['roles' => ['teacher']])->assertForbidden();
        $this->actingAsUser($super)->patchJson("/api/users/{$teacher->id}", ['roles' => ['hacker']])->assertStatus(422);
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

    public function test_super_admin_creates_group_with_admin_only(): void
    {
        $super = User::factory()->superAdmin()->create();
        $account = ZoomAccount::factory()->create();
        $teacher = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $payload = ['name' => '1-A1 Tarix', 'course' => 1, 'zoom_account_id' => $account->id, 'zoom_user_email' => 'Zoom_9@KarSU.uz'];

        $this->actingAsUser($super)->postJson('/api/groups', $payload + ['admin_id' => $teacher->id])->assertStatus(422)->assertJsonValidationErrors('admin_id');
        $this->actingAsUser($super)->postJson('/api/groups', $payload + ['admin_id' => $admin->id])
            ->assertCreated()
            ->assertJsonPath('data.zoom_user_email', 'zoom_9@karsu.uz')
            ->assertJsonPath('data.admin.id', $admin->id);
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
