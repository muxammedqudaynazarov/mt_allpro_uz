<?php

namespace Tests\Feature;

use App\Actions\MaterializeSeries;
use App\Enums\LessonStatus;
use App\Jobs\SendTelegramMessage;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonSeries;
use App\Models\LessonSlot;
use App\Models\User;
use App\Models\ZoomHost;
use App\Services\Telegram\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LessonSeriesTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $wednesday;

    protected function setUp(): void
    {
        parent::setUp();
        // Keyingi chorshanba, soat 10:00 (juftlik 13:00 da).
        $this->wednesday = now()->next(Carbon::WEDNESDAY)->setTime(10, 0);
        $this->travelTo($this->wednesday);
        config(['services.telegram.bot_token' => 'test-token', 'services.telegram.bot_username' => 'karsu_bot']);
        $this->app->forgetInstance(TelegramBot::class);
    }

    private function series(array $attributes = []): LessonSeries
    {
        $group = Group::factory()->create();
        $teacher = User::factory()->create(['telegram_chat_id' => (string) (777 + LessonSeries::count())]);

        return LessonSeries::create($attributes + [
            'user_id' => $teacher->id,
            'group_id' => $group->id,
            'lesson_slot_id' => LessonSlot::factory()->create(['starts_at' => '13:00'])->id,
            'weekday' => 3,
            'topic' => 'Algebra',
            'starts_on' => today()->toDateString(),
        ]);
    }

    public function test_teacher_creates_series_and_conflicts_are_rejected(): void
    {
        $teacher = User::factory()->create();
        $group = Group::factory()->create();
        $slot = LessonSlot::factory()->create();
        $payload = ['group_id' => $group->id, 'lesson_slot_id' => $slot->id, 'weekday' => 1];

        $this->actingAsUser($teacher)->postJson('/api/series', $payload)
            ->assertCreated()
            ->assertJsonPath('data.weekday', 1)
            ->assertJsonPath('data.can.delete', false);

        // Shu guruh, kun va juftlik — ikkinchi marta qo'shilmaydi (boshqa o'qituvchi ham).
        $this->actingAsUser(User::factory()->create())->postJson('/api/series', $payload)->assertStatus(422)->assertJsonValidationErrors('group_id');

        // Kalendar huquqi olingan o'qituvchi va O'quv bo'limi qo'sha olmaydi.
        $this->actingAsUser(User::factory()->create(['can_manage_calendar' => false]))->postJson('/api/series', [...$payload, 'weekday' => 2])->assertForbidden();
        $this->actingAsUser(User::factory()->education()->create())->postJson('/api/series', [...$payload, 'weekday' => 2])->assertForbidden();
    }

    public function test_meeting_is_created_one_hour_before_and_sent_to_teacher(): void
    {
        $this->fakeZoom();
        Queue::fake();
        $series = $this->series();
        $materialize = app(MaterializeSeries::class);

        $this->travelTo($this->wednesday->copy()->setTime(11, 59));
        $this->assertSame(['created' => 0, 'failed' => 0], $materialize->run());

        $this->travelTo($this->wednesday->copy()->setTime(12, 0));
        $this->assertSame(['created' => 1, 'failed' => 0], $materialize->run());
        $lesson = Lesson::sole();
        $this->assertSame($series->id, $lesson->lesson_series_id);
        $this->assertSame('13:00', $lesson->starts_at->format('H:i'));
        $this->assertSame(LessonStatus::Scheduled, $lesson->status);
        Queue::assertPushed(SendTelegramMessage::class, fn (SendTelegramMessage $job) => $job->chatId === '777' && str_contains($job->html, 'Haftalik dars'));

        // Qayta ishga tushsa — ikkinchi dars yaratilmaydi.
        $this->travelTo($this->wednesday->copy()->setTime(12, 1));
        $this->assertSame(['created' => 0, 'failed' => 0], $materialize->run());
        // Boshqa kun (payshanba) — hech narsa.
        $this->travelTo($this->wednesday->copy()->addDay()->setTime(12, 30));
        $materialize->run();
        $this->assertSame(1, Lesson::count());
    }

    public function test_skipped_date_is_not_created_and_skip_cancels_created_lesson(): void
    {
        $this->fakeZoom();
        Queue::fake();
        $series = $this->series();
        $teacher = $series->teacher;
        $nextWeek = $this->wednesday->copy()->addWeek();

        $this->actingAsUser($teacher)->postJson("/api/series/{$series->id}/skips", ['date' => $nextWeek->toDateString()])
            ->assertOk()->assertJsonPath('data.skips.0', $nextWeek->toDateString());

        // Bugungi uchrashuv yaratildi, so'ng o'qituvchi bugunni ham bekor qildi.
        $this->travelTo($this->wednesday->copy()->setTime(12, 10));
        app(MaterializeSeries::class)->run();
        $this->actingAsUser($teacher)->postJson("/api/series/{$series->id}/skips", ['date' => today()->toDateString()])->assertOk();
        $this->assertSame(LessonStatus::Cancelled, Lesson::sole()->status);
        $this->assertSame(['created' => 0, 'failed' => 0], app(MaterializeSeries::class)->run());

        // Bekor qilingan uchrashuvni "qaytarib" bo'lmaydi, kelasi haftani — bo'ladi.
        $this->actingAsUser($teacher)->deleteJson("/api/series/{$series->id}/skips", ['date' => today()->toDateString()])->assertStatus(422);
        $this->actingAsUser($teacher)->deleteJson("/api/series/{$series->id}/skips", ['date' => $nextWeek->toDateString()])->assertOk()->assertJsonPath('data.skips', [today()->toDateString()]);

        $this->travelTo($nextWeek->copy()->setTime(12, 5));
        $this->assertSame(['created' => 1, 'failed' => 0], app(MaterializeSeries::class)->run());
    }

    public function test_busy_preferred_host_falls_back_and_no_host_notifies_once(): void
    {
        $this->fakeZoom();
        Queue::fake();
        $preferred = ZoomHost::factory()->create(['email' => 'preferred@karsu.uz']);
        $series = $this->series(['zoom_host_id' => $preferred->id]);
        // Afzal host 13:00 da boshqa darsda band.
        Lesson::factory()->create(['zoom_account_id' => $preferred->zoom_account_id, 'zoom_host_email' => 'preferred@karsu.uz', 'starts_at' => $this->wednesday->copy()->setTime(12, 50), 'ends_at' => $this->wednesday->copy()->setTime(14, 10), 'status' => LessonStatus::Scheduled]);
        $this->travelTo($this->wednesday->copy()->setTime(12, 0));

        // Guruhning standart hosti bo'sh — o'sha olinadi.
        $this->assertSame(['created' => 1, 'failed' => 0], app(MaterializeSeries::class)->run());
        $this->assertSame($series->group->zoom_user_email, Lesson::where('lesson_series_id', $series->id)->value('zoom_host_email'));

        // Boshqa jadval: barcha hostlar band — xato saqlanadi, Telegram bir marta.
        $other = $this->series(['lesson_slot_id' => $series->lesson_slot_id]);
        ZoomHost::query()->whereNotIn('id', [$preferred->id])->update(['is_active' => false]);
        Queue::fake();
        $this->assertSame(['created' => 0, 'failed' => 1], app(MaterializeSeries::class)->run());
        $this->travelTo($this->wednesday->copy()->setTime(12, 1));
        app(MaterializeSeries::class)->run();
        Queue::assertPushed(SendTelegramMessage::class, 1);
        Queue::assertPushed(SendTelegramMessage::class, fn (SendTelegramMessage $job) => str_contains($job->html, 'yaratilmadi'));
        $this->assertNotNull($other->fresh()->last_error);
    }

    public function test_teacher_cancels_series_and_only_admin_deletes(): void
    {
        $series = $this->series();
        $teacher = $series->teacher;
        $teacher->forceFill(['can_manage_calendar' => false])->save();

        // Huquqi olingan bo'lsa ham o'z jadvalini to'xtata oladi, lekin o'zgartira olmaydi.
        $this->actingAsUser($teacher)->patchJson("/api/series/{$series->id}", ['topic' => 'Yangi'])->assertForbidden();
        $this->actingAsUser($teacher)->postJson("/api/series/{$series->id}/cancel")->assertOk()->assertJsonPath('data.is_active', false);
        $this->actingAsUser($teacher)->deleteJson("/api/series/{$series->id}")->assertForbidden();
        $this->actingAsUser(User::factory()->create())->postJson("/api/series/{$series->id}/cancel")->assertForbidden();

        $this->actingAsUser(User::factory()->superAdmin()->create())->deleteJson("/api/series/{$series->id}")->assertNoContent();
        $this->assertDatabaseCount('lesson_series', 0);
    }

    public function test_calendar_shows_lessons_and_planned_occurrences(): void
    {
        $series = $this->series();
        $teacher = $series->teacher;
        $series->skips()->create(['date' => $this->wednesday->copy()->addWeek()->toDateString()]);
        Lesson::factory()->create(['user_id' => $teacher->id, 'starts_at' => $this->wednesday->copy()->setTime(8, 30), 'ends_at' => $this->wednesday->copy()->setTime(9, 50), 'status' => LessonStatus::Finished]);
        $other = $this->series(['weekday' => 4]);

        $from = $this->wednesday->toDateString();
        $to = $this->wednesday->copy()->addDays(13)->toDateString();
        $response = $this->actingAsUser($teacher)->getJson("/api/calendar?from={$from}&to={$to}")->assertOk();

        $planned = collect($response->json('data.planned'));
        $this->assertSame([$series->id], $planned->pluck('series_id')->unique()->values()->all());
        $this->assertSame([false, true], $planned->pluck('skipped')->all());
        $this->assertCount(1, $response->json('data.lessons'));
        $this->assertTrue($response->json('data.can_manage'));

        // O'quv bo'limi hammasini ko'radi, lekin boshqara olmaydi.
        $all = $this->actingAsUser(User::factory()->education()->create())->getJson("/api/calendar?from={$from}&to={$to}")->assertOk();
        $this->assertEqualsCanonicalizing([$series->id, $other->id], collect($all->json('data.planned'))->pluck('series_id')->unique()->values()->all());
        $this->assertFalse($all->json('data.can_manage'));
    }

    public function test_lesson_with_repeat_weekly_creates_linked_series(): void
    {
        $this->fakeZoom();
        $teacher = User::factory()->create();
        $group = Group::factory()->create();
        $slot = LessonSlot::factory()->create(['starts_at' => '13:00']);

        $this->actingAsUser($teacher)->postJson('/api/lessons', ['group_id' => $group->id, 'repeat_weekly' => true])
            ->assertStatus(422)->assertJsonValidationErrors('slot_id');

        $this->actingAsUser($teacher)->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $slot->id, 'repeat_weekly' => true])->assertCreated();
        $series = LessonSeries::sole();
        $this->assertSame([3, $series->id], [$series->weekday, Lesson::sole()->lesson_series_id]);

        // Birinchi dars yaratilmasa (tanlangan host band), jadval ham saqlanmaydi.
        $busy = ZoomHost::factory()->create(['email' => 'busy@karsu.uz']);
        Lesson::factory()->create(['zoom_account_id' => $busy->zoom_account_id, 'zoom_host_email' => 'busy@karsu.uz', 'starts_at' => today()->setTime(16, 0), 'ends_at' => today()->setTime(17, 20), 'status' => LessonStatus::Scheduled]);
        $this->actingAsUser($teacher)->postJson('/api/lessons', [
            'group_id' => Group::factory()->create()->id, 'slot_id' => LessonSlot::factory()->create(['starts_at' => '16:00'])->id, 'host_id' => $busy->id, 'repeat_weekly' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('host_id');
        $this->assertSame(1, LessonSeries::count());
    }
}
