<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Jobs\SendTelegramMessage;
use App\Models\User;
use App\Services\Telegram\TelegramBot;
use App\Support\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** O'quv bo'limi yangi foydalanuvchini tasdiqlay oladi, lekin boshqa hech narsani o'zgartira olmaydi. */
class UserApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_education_can_approve_pending_user(): void
    {
        $education = User::factory()->education()->create();
        $pending = User::factory()->pending()->create();

        $this->actingAsUser($education)->patchJson("/api/users/{$pending->id}", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
        $this->assertSame(UserStatus::Active, $pending->fresh()->status);
    }

    public function test_education_cannot_change_anything_else(): void
    {
        $education = User::factory()->education()->create();
        $active = User::factory()->create(['roles' => ['teacher']]);
        $blocked = User::factory()->blocked()->create();
        $pending = User::factory()->pending()->create();

        $this->actingAsUser($education)->patchJson("/api/users/{$active->id}", ['status' => 'blocked'])->assertForbidden();
        $this->actingAsUser($education)->patchJson("/api/users/{$blocked->id}", ['status' => 'active'])->assertForbidden();
        $this->actingAsUser($education)->patchJson("/api/users/{$active->id}", ['roles' => ['teacher', 'super_admin']])->assertForbidden();
        $this->actingAsUser($education)->patchJson("/api/users/{$active->id}", ['first_name' => 'Boshqa'])->assertForbidden();
        $this->actingAsUser($education)->patchJson("/api/users/{$active->id}", ['can_manage_calendar' => false])->assertForbidden();
        $this->actingAsUser($education)->deleteJson("/api/users/{$active->id}")->assertForbidden();
        // Tasdiqlash bilan birga rol berib bo'lmaydi.
        $this->actingAsUser($education)->patchJson("/api/users/{$pending->id}", ['status' => 'active', 'roles' => ['super_admin']])->assertForbidden();

        $this->assertSame(['teacher'], $active->fresh()->roles);
        $this->assertSame(UserStatus::Blocked, $blocked->fresh()->status);
        $this->assertFalse($pending->fresh()->isSuperAdmin());
    }

    public function test_teacher_cannot_approve(): void
    {
        $pending = User::factory()->pending()->create();

        $this->actingAsUser(User::factory()->create())->patchJson("/api/users/{$pending->id}", ['status' => 'active'])->assertForbidden();
        $this->assertSame(UserStatus::Pending, $pending->fresh()->status);
    }

    public function test_education_gets_new_user_notification(): void
    {
        Queue::fake();
        config(['services.telegram.bot_token' => 'test-token']);
        $this->app->forgetInstance(TelegramBot::class);
        User::factory()->education()->create(['telegram_chat_id' => '501']);
        User::factory()->superAdmin()->create(['telegram_chat_id' => '502']);
        User::factory()->create(['telegram_chat_id' => '503']);

        app(Notifier::class)->userPending(User::factory()->pending()->create());

        Queue::assertPushed(SendTelegramMessage::class, fn ($job) => $job->chatId === '501');
        Queue::assertPushed(SendTelegramMessage::class, fn ($job) => $job->chatId === '502');
        Queue::assertNotPushed(SendTelegramMessage::class, fn ($job) => $job->chatId === '503');
    }
}
