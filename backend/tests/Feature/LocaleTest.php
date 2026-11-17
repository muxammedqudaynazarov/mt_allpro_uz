<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramMessage;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_messages_follow_x_locale_header(): void
    {
        $this->postJson('/api/auth/login', [], ['X-Locale' => 'kaa'])
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', 'login maydanı toltırılıwı shárt.');

        $this->postJson('/api/auth/login', [], ['X-Locale' => 'uz'])
            ->assertJsonPath('errors.login.0', 'login maydoni toʻldirilishi shart.');

        $this->getJson('/api/me', ['X-Locale' => 'kaa'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Sistemaǵa kiriw talap etiledi.');
    }

    public function test_unknown_locale_falls_back_to_default(): void
    {
        $this->getJson('/api/me', ['X-Locale' => 'xx'])->assertJsonPath('message', 'Tizimga kirish talab qilinadi.');
    }

    public function test_user_saves_preferred_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)->patchJson('/api/me', ['locale' => 'kaa'])->assertOk()->assertJsonPath('data.locale', 'kaa');
        $this->assertSame('kaa', $user->refresh()->locale);

        $this->actingAsUser($user)->patchJson('/api/me', ['locale' => 'ru'])->assertStatus(422);
    }

    public function test_saved_locale_is_used_without_header(): void
    {
        $user = User::factory()->create(['locale' => 'kaa']);

        $this->actingAsUser($user)->getJson('/api/zoom-accounts')
            ->assertForbidden()
            ->assertJsonPath('message', 'Bul háreket ushın ruqsatıńız joq.');
    }

    public function test_telegram_message_uses_recipient_language(): void
    {
        Queue::fake();
        $this->fakeZoom();
        $teacher = User::factory()->create(['telegram_chat_id' => '42', 'locale' => 'kaa']);
        $group = Group::factory()->create();

        // Sayt o'zbekchada ochilgan bo'lsa ham, Telegram xabari xodimning tilida keladi.
        $this->actingAsUser($teacher)->postJson('/api/lessons', ['group_id' => $group->id], ['X-Locale' => 'uz'])->assertCreated();

        Queue::assertPushed(SendTelegramMessage::class, fn ($job) => str_contains($job->html, 'Sabaq jaratıldı')
            && $job->buttons[0][0]['text'] === 'Sabaqtı baslaw');
    }

    public function test_audit_log_is_shown_in_viewer_language(): void
    {
        $super = User::factory()->superAdmin()->create();
        $teacher = User::factory()->pending()->create(['name' => 'Test Xodim']);

        $this->actingAsUser($super)->patchJson("/api/users/{$teacher->id}", ['status' => 'active'])->assertOk();

        $this->actingAsUser($super)->getJson('/api/audit-logs', ['X-Locale' => 'kaa'])
            ->assertJsonPath('data.0.description', 'Xızmetker maǵlıwmatları ózgertildi: Test Xodim (status)')
            ->assertJsonMissingPath('data.0.properties._t');
        $this->actingAsUser($super)->getJson('/api/audit-logs', ['X-Locale' => 'uz'])
            ->assertJsonPath('data.0.description', 'Xodim maʼlumotlari oʻzgartirildi: Test Xodim (status)');
    }
}
