<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramMessage;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\LessonSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->actingAsUser($teacher)->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $this->slotNow()->id], ['X-Locale' => 'uz'])->assertCreated();

        Queue::assertPushed(SendTelegramMessage::class, fn ($job) => str_contains($job->html, 'Sabaq jaratıldı')
            && $job->buttons[0][0]['text'] === 'Sabaqtı baslaw');
    }

    public function test_audit_log_is_shown_in_viewer_language(): void
    {
        $super = User::factory()->superAdmin()->create();
        $teacher = User::factory()->pending()->create(['name' => 'Test Xodim']);

        $this->actingAsUser($super)->patchJson("/api/users/{$teacher->id}", ['status' => 'active'])->assertOk();

        $this->actingAsUser($super)->getJson('/api/audit-logs', ['X-Locale' => 'kaa'])
            ->assertJsonPath('data.0.description', 'Paydalanıwshı maǵlıwmatları ózgertildi: Test Xodim (status)')
            ->assertJsonMissingPath('data.0.properties._t');
        $this->actingAsUser($super)->getJson('/api/audit-logs', ['X-Locale' => 'uz'])
            ->assertJsonPath('data.0.description', 'Foydalanuvchi maʼlumotlari oʻzgartirildi: Test Xodim (status)');
    }

    public function test_old_audit_entries_use_new_wording(): void
    {
        $super = User::factory()->superAdmin()->create();
        AuditLog::create([
            'action' => 'user.deleted',
            'description' => 'Xodim oʻchirildi: Ali',
            'properties' => ['_t' => ['key' => 'Xodim oʻchirildi: :name', 'replace' => ['name' => 'Ali']]],
        ]);

        $this->actingAsUser($super)->getJson('/api/audit-logs', ['X-Locale' => 'uz'])->assertJsonPath('data.0.description', 'Foydalanuvchi oʻchirildi: Ali');
        $this->actingAsUser($super)->getJson('/api/audit-logs', ['X-Locale' => 'kaa'])->assertJsonPath('data.0.description', 'Paydalanıwshı óshirildi: Ali');
    }

    public function test_user_without_chosen_locale_gets_null(): void
    {
        config(['app.locale' => 'uz']);

        $this->actingAsUser(User::factory()->create(['locale' => null]))->getJson('/api/me')->assertJsonPath('data.locale', null);
    }

    public function test_default_slot_names_follow_viewer_language(): void
    {
        $teacher = User::factory()->create();
        Group::factory()->create();
        LessonSlot::factory()->create(['name' => '2-juplıq', 'starts_at' => '23:00', 'sort' => 1]);
        LessonSlot::factory()->create(['name' => '3-para', 'starts_at' => '23:10', 'sort' => 2]);
        LessonSlot::factory()->create(['name' => 'Kechki', 'starts_at' => '23:20', 'sort' => 3]);

        $this->actingAsUser($teacher)->getJson('/api/lessons/options', ['X-Locale' => 'uz'])
            ->assertJsonPath('data.slots.0.name', '2-juftlik')
            ->assertJsonPath('data.slots.1.name', '3-juftlik')
            ->assertJsonPath('data.slots.2.name', 'Kechki');
        $this->actingAsUser($teacher)->getJson('/api/lessons/options', ['X-Locale' => 'kaa'])
            ->assertJsonPath('data.slots.0.name', '2-juplıq')
            ->assertJsonPath('data.slots.1.name', '3-juplıq');
    }

    public function test_migration_renames_old_default_slot_names(): void
    {
        DB::table('lesson_slots')->insert([
            ['name' => '1-para', 'starts_at' => '13:00', 'duration_minutes' => 80, 'sort' => 1, 'is_active' => true],
            ['name' => 'Kechki para', 'starts_at' => '19:00', 'duration_minutes' => 80, 'sort' => 2, 'is_active' => true],
        ]);

        (require database_path('migrations/2026_11_17_000200_rename_default_lesson_slots.php'))->up();

        $this->assertSame(['1-juplıq', 'Kechki para'], DB::table('lesson_slots')->orderBy('sort')->pluck('name')->all());
    }
}
