<?php

namespace Tests\Feature;

use App\Enums\LessonStatus;
use App\Jobs\SendTelegramMessage;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Telegram\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Kalendardagi dars haqida o'qituvchiga 30 daqiqa oldin Telegram eslatmasi. */
class LessonReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.bot_token' => 'test-token', 'services.telegram.bot_username' => 'karsu_bot']);
        $this->app->forgetInstance(TelegramBot::class);
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-27 13:00', 'Asia/Tashkent'));
    }

    private function lesson(string $starts, string $created, array $attributes = []): Lesson
    {
        $teacher = User::factory()->create(['telegram_chat_id' => (string) (900 + User::count())]);
        $lesson = Lesson::factory()->create($attributes + [
            'user_id' => $teacher->id,
            'status' => LessonStatus::Scheduled,
            'starts_at' => Carbon::parse($starts, 'Asia/Tashkent'),
            'ends_at' => Carbon::parse($starts, 'Asia/Tashkent')->addMinutes(80),
        ]);
        $lesson->forceFill(['created_at' => Carbon::parse($created, 'Asia/Tashkent')])->save();

        return $lesson;
    }

    public function test_planned_lesson_is_reminded_30_minutes_before_once(): void
    {
        $planned = $this->lesson('2026-11-27 14:30', '2026-11-25 10:00', ['topic' => 'Algebra', 'passcode' => '123456']);

        $this->travelTo(Carbon::parse('2026-11-27 13:59', 'Asia/Tashkent'));
        $this->artisan('lessons:remind')->assertSuccessful();
        Queue::assertNothingPushed();

        $this->travelTo(Carbon::parse('2026-11-27 14:00', 'Asia/Tashkent'));
        $this->artisan('lessons:remind')->assertSuccessful();
        $this->artisan('lessons:remind')->assertSuccessful();

        Queue::assertPushed(SendTelegramMessage::class, 1);
        Queue::assertPushed(SendTelegramMessage::class, fn (SendTelegramMessage $job) => $job->chatId === $planned->teacher->telegram_chat_id
            && str_contains($job->html, '30 daqiqadan')
            && str_contains($job->html, '14:30–15:50')
            && str_contains($job->html, 'Algebra')
            && str_contains($job->html, '123456')
            && str_contains($job->html, (string) $planned->join_url)
            && str_contains($job->buttons[0][0]['url'], '/l/'.$planned->id.'/start')
            && str_contains($job->buttons[1][0]['url'], 't.me/share'));
        $this->assertNotNull($planned->fresh()->reminded_at);
    }

    public function test_lessons_created_shortly_before_started_or_cancelled_are_not_reminded(): void
    {
        // Yarim soat oldin yaratilgan — yaratilganda xabar borgan.
        $this->lesson('2026-11-27 14:30', '2026-11-27 14:00');
        $this->lesson('2026-11-27 14:30', '2026-11-26 09:00', ['zoom_state' => Lesson::ZOOM_STARTED]);
        $this->lesson('2026-11-27 14:30', '2026-11-26 09:00', ['status' => LessonStatus::Cancelled]);
        // Uzoqroq — hali vaqti emas.
        $later = $this->lesson('2026-11-27 16:00', '2026-11-26 09:00');

        $this->travelTo(Carbon::parse('2026-11-27 14:05', 'Asia/Tashkent'));
        $this->artisan('lessons:remind')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertNull($later->fresh()->reminded_at);
    }

    public function test_teacher_without_telegram_is_skipped_quietly(): void
    {
        $lesson = $this->lesson('2026-11-27 14:30', '2026-11-25 10:00');
        $lesson->teacher->forceFill(['telegram_chat_id' => null])->save();

        $this->travelTo(Carbon::parse('2026-11-27 14:10', 'Asia/Tashkent'));
        $this->artisan('lessons:remind')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertNotNull($lesson->fresh()->reminded_at);
    }
}
