<?php

namespace Tests\Feature;

use App\Actions\CreateLesson;
use App\Enums\LessonStatus;
use App\Jobs\SendTelegramMessage;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonSeries;
use App\Models\LessonSlot;
use App\Models\User;
use App\Models\ZoomHost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class LessonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(14, 0));
    }

    public function test_teacher_creates_lesson_for_slot(): void
    {
        Queue::fake();
        $this->fakeZoom();
        $teacher = User::factory()->create(['telegram_chat_id' => '555']);
        $group = Group::factory()->create(['zoom_user_email' => 'Zoom_1@karsu.uz']);
        $slot = LessonSlot::factory()->create(['starts_at' => '14:30', 'duration_minutes' => 80]);

        $response = $this->actingAsUser($teacher)->postJson('/api/lessons', [
            'group_id' => $group->id,
            'slot_id' => $slot->id,
            'topic' => 'Chiziqli algebra',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.topic', 'Chiziqli algebra')
            ->assertJsonPath('data.passcode', '654321')
            ->assertJsonPath('data.can.start', true)
            ->assertJsonMissingPath('data.start_url');

        $lesson = Lesson::firstOrFail();
        $this->assertSame('14:30', $lesson->starts_at->format('H:i'));
        $this->assertSame('15:50', $lesson->ends_at->format('H:i'));
        $this->assertSame('zoom_1@karsu.uz', $lesson->zoom_host_email);
        $this->assertStringContainsString('zak=first', $lesson->start_url);
        $this->assertStringNotContainsString('zak=first', (string) $lesson->getRawOriginal('start_url'), 'start_url shifrlangan holda saqlanishi kerak');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/users/zoom_1%40karsu.uz/meetings')
            && $request['type'] === 2
            && $request['duration'] === 80
            && $request['timezone'] === 'Asia/Tashkent'
            && $request['start_time'] === now()->format('Y-m-d').'T14:30:00');

        Queue::assertPushed(SendTelegramMessage::class, fn ($job) => $job->chatId === '555'
            && str_contains($job->html, $group->name)
            && str_contains($job->buttons[0][0]['url'], '/l/'.$lesson->id.'/start'));
    }

    public function test_unplanned_lesson_without_slot_is_rejected(): void
    {
        $this->fakeZoom();
        $group = Group::factory()->create();

        $this->actingAsUser(User::factory()->create())->postJson('/api/lessons', ['group_id' => $group->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slot_id');
        $this->assertSame(0, Lesson::count());
    }

    /** Soat 14:00: 13:30 juftligi boshlangan — qo'lda tanlanmaydi; haftalik dars esa kechikib bo'lsa ham yaratiladi. */
    public function test_started_and_finished_slots_are_closed_for_manual_creation(): void
    {
        $this->fakeZoom();
        $teacher = User::factory()->create();
        $group = Group::factory()->create();
        $running = LessonSlot::factory()->create(['starts_at' => '13:30', 'duration_minutes' => 80]);
        $finished = LessonSlot::factory()->create(['starts_at' => '12:00', 'duration_minutes' => 80]);
        $next = LessonSlot::factory()->create(['starts_at' => '14:30', 'duration_minutes' => 80]);

        $this->actingAsUser($teacher)->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $finished->id])
            ->assertStatus(422)->assertJsonValidationErrors('slot_id');
        $this->actingAsUser($teacher)->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $running->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.slot_id.0', fn (string $message) => str_contains($message, 'boshlanib boʻlgan'));
        $this->assertSame(0, Lesson::count());

        $options = $this->actingAsUser($teacher)->getJson('/api/lessons/options')->assertOk()->json('data.slots');
        $available = collect($options)->mapWithKeys(fn (array $slot) => [$slot['id'] => $slot['available']]);
        $this->assertFalse($available[$running->id]);
        $this->assertFalse($available[$finished->id]);
        $this->assertTrue($available[$next->id]);

        $this->actingAsUser($teacher)->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $next->id])->assertCreated();
        $this->assertSame('14:30', Lesson::firstOrFail()->starts_at->format('H:i'));

        // Haftalik dars: server kechiksa ham boshlangan juftlikka yaratiladi, vaqti hozirdan.
        $series = LessonSeries::create(['user_id' => $teacher->id, 'group_id' => $other = Group::factory()->create()->id, 'lesson_slot_id' => $running->id, 'weekday' => now()->isoWeekday(), 'starts_on' => now()->toDateString()]);
        $lesson = app(CreateLesson::class)->handle($teacher, Group::findOrFail($other), $running, null, seriesId: $series->id, allowStartedSlot: true);
        $this->assertSame('14:00', $lesson->starts_at->format('H:i'));
        $this->assertSame('14:50', $lesson->ends_at->format('H:i'));

        // Forma orqali "Har hafta takrorlash" bilan ham boshlangan juftlikni chetlab o'tib bo'lmaydi.
        $this->actingAsUser(User::factory()->create())->postJson('/api/lessons', ['group_id' => Group::factory()->create()->id, 'slot_id' => $running->id, 'repeat_weekly' => true])
            ->assertStatus(422)->assertJsonValidationErrors('slot_id');
        $this->assertSame(1, LessonSeries::count());
    }

    public function test_busy_group_cannot_be_booked_twice(): void
    {
        $this->fakeZoom();
        $group = Group::factory()->create();
        Lesson::factory()->for($group)->create();

        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $this->slotSoon()->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('group_id');

        $this->assertSame(1, Lesson::count());
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/meetings'));
    }

    /** Ikki o'qituvchi bir guruhga turli hostlar bilan bir vaqtda yaratsa: guruh qulfi ikkinchisini to'xtatadi. */
    public function test_group_lock_blocks_parallel_creation_for_same_group(): void
    {
        $this->fakeZoom();
        config(['lessons.lock_wait' => 0]);
        $group = Group::factory()->create();
        $lock = Cache::lock('lesson-create:group:'.$group->id, 30);
        $this->assertTrue($lock->get());

        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $this->slotSoon()->id])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Bu guruhga hozir boshqa dars yaratilmoqda. Bir necha soniyadan soʻng qayta urinib koʻring.');
        $this->assertSame(0, Lesson::count());
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/meetings'));

        $lock->release();
        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $this->slotSoon()->id])
            ->assertCreated();
    }

    public function test_busy_host_is_skipped_and_free_host_is_chosen(): void
    {
        $this->fakeZoom();
        $first = Group::factory()->create(['zoom_user_email' => 'zoom_1@karsu.uz']);
        $second = Group::factory()->create(['zoom_user_email' => 'zoom_1@karsu.uz', 'zoom_account_id' => $first->zoom_account_id]);
        Lesson::factory()->for($first)->create();
        $teacher = User::factory()->create();

        // Yagona host band — bo'sh host yo'q.
        $this->actingAsUser($teacher)->postJson('/api/lessons', ['group_id' => $second->id, 'slot_id' => $this->slotSoon()->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('host_id');

        // Boshqa host qo'shildi — dars avtomatik o'sha hostda yaratiladi.
        $free = ZoomHost::factory()->create(['zoom_account_id' => $first->zoom_account_id, 'email' => 'zoom_2@karsu.uz']);
        $this->actingAsUser($teacher)->postJson('/api/lessons', ['group_id' => $second->id, 'slot_id' => $this->slotSoon()->id])->assertCreated();
        $this->assertSame('zoom_2@karsu.uz', Lesson::latest('id')->value('zoom_host_email'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/users/zoom_2%40karsu.uz/meetings'));
        $this->assertTrue($free->is_active);
    }

    public function test_teacher_picks_host_explicitly_and_busy_host_is_rejected(): void
    {
        $this->fakeZoom();
        $group = Group::factory()->create();
        $busy = ZoomHost::factory()->create(['email' => 'busy@karsu.uz']);
        $chosen = ZoomHost::factory()->create(['email' => 'chosen@karsu.uz']);
        Lesson::factory()->create(['zoom_account_id' => $busy->zoom_account_id, 'zoom_host_email' => 'busy@karsu.uz']);
        $teacher = User::factory()->create();

        $this->actingAsUser($teacher)->getJson("/api/lessons/options?group_id={$group->id}")
            ->assertOk()
            ->assertJsonFragment(['email' => 'busy@karsu.uz', 'busy' => true])
            ->assertJsonFragment(['email' => 'chosen@karsu.uz', 'busy' => false]);

        $this->actingAsUser($teacher)->postJson('/api/lessons', ['group_id' => $group->id, 'host_id' => $busy->id, 'slot_id' => $this->slotSoon()->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.host_id.0', fn ($msg) => str_contains($msg, 'busy@karsu.uz'));

        $this->actingAsUser($teacher)->postJson('/api/lessons', ['group_id' => $group->id, 'host_id' => $chosen->id, 'slot_id' => $this->slotSoon()->id])->assertCreated();
        $this->assertSame('chosen@karsu.uz', Lesson::latest('id')->value('zoom_host_email'));
    }

    public function test_lesson_can_be_scheduled_for_another_day_with_slot(): void
    {
        $this->fakeZoom();
        $group = Group::factory()->create();
        $slot = LessonSlot::factory()->create(['starts_at' => '13:00', 'duration_minutes' => 80]);
        // Ertangi kun yakshanba (dam olish) bo'lsa — dushanba.
        $tomorrow = now()->addDays(now()->addDay()->isSunday() ? 2 : 1)->format('Y-m-d');

        $this->actingAsUser(User::factory()->create())->postJson('/api/lessons', ['group_id' => $group->id, 'date' => $tomorrow])
            ->assertStatus(422)->assertJsonValidationErrors('slot_id');
        $this->actingAsUser(User::factory()->create())->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $slot->id, 'date' => $tomorrow])
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled');
        $this->assertSame($tomorrow.' 13:00', Lesson::latest('id')->first()->starts_at->format('Y-m-d H:i'));

        // Administrator kalendarni taqiqlagan o'qituvchi faqat bugunga dars yaratadi.
        $restricted = User::factory()->create(['can_manage_calendar' => false]);
        $this->actingAsUser($restricted)->postJson('/api/lessons', ['group_id' => Group::factory()->create()->id, 'slot_id' => $slot->id, 'date' => $tomorrow])
            ->assertForbidden();
    }

    public function test_ended_lesson_frees_the_group(): void
    {
        $this->fakeZoom();
        $group = Group::factory()->create();
        Lesson::factory()->for($group)->create(['status' => LessonStatus::Finished, 'ended_at' => now()]);

        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $this->slotSoon()->id])
            ->assertCreated();
    }

    public function test_inactive_group_cannot_be_booked(): void
    {
        $this->fakeZoom();
        $group = Group::factory()->create(['is_active' => false]);

        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $this->slotSoon()->id])
            ->assertStatus(422);
    }

    public function test_zoom_failure_returns_readable_error_and_saves_nothing(): void
    {
        $this->fakeZoom(['api.zoom.us/v2/users/*/meetings' => Http::response(['code' => 1001, 'message' => 'User does not exist'], 404)]);
        $group = Group::factory()->create();

        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $this->slotSoon()->id])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($msg) => str_contains($msg, $group->zoom_user_email));

        $this->assertSame(0, Lesson::count());
    }

    public function test_options_mark_busy_groups(): void
    {
        $busy = Group::factory()->create();
        $free = Group::factory()->create();
        Group::factory()->create(['is_active' => false]);
        Lesson::factory()->for($busy)->create();

        $response = $this->actingAsUser(User::factory()->create())->getJson('/api/lessons/options?slot_id=now')->assertOk();

        $groups = collect($response->json('data.groups'))->keyBy('id');
        $this->assertCount(2, $groups);
        $this->assertTrue($groups[$busy->id]['busy']);
        $this->assertFalse($groups[$free->id]['busy']);
    }

    public function test_teacher_sees_only_own_lessons(): void
    {
        $teacher = User::factory()->create();
        $own = Lesson::factory()->create(['user_id' => $teacher->id]);
        Lesson::factory()->create();

        $this->actingAsUser($teacher)->getJson('/api/lessons')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_education_office_sees_all_lessons(): void
    {
        $education = User::factory()->education()->create();
        Lesson::factory()->create();
        Lesson::factory()->create();

        $this->actingAsUser($education)->getJson('/api/lessons')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_only_owner_gets_fresh_start_url(): void
    {
        $this->fakeZoom();
        $lesson = Lesson::factory()->create();

        $this->actingAsUser(User::factory()->create())->postJson("/api/lessons/{$lesson->id}/start")->assertForbidden();

        $this->actingAsUser($lesson->teacher)->postJson("/api/lessons/{$lesson->id}/start")
            ->assertOk()
            ->assertJsonPath('data.url', 'https://us06web.zoom.us/s/81234567890?zak=fresh');
    }

    public function test_other_teacher_and_education_cannot_end_lesson_but_administrator_can(): void
    {
        $this->fakeZoom();
        $admin = User::factory()->superAdmin()->create();
        $lesson = Lesson::factory()->create();

        $this->actingAsUser(User::factory()->create())->postJson("/api/lessons/{$lesson->id}/end")->assertForbidden();
        $this->actingAsUser(User::factory()->education()->create())->postJson("/api/lessons/{$lesson->id}/end")->assertForbidden();

        $this->actingAsUser($admin)->postJson("/api/lessons/{$lesson->id}/end")
            ->assertOk()
            ->assertJsonPath('data.status', 'finished');

        $lesson->refresh();
        $this->assertSame($admin->id, $lesson->ended_by_id);
        $this->assertNull($lesson->start_url);
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && str_ends_with($request->url(), "/meetings/{$lesson->zoom_meeting_id}/status"));
    }

    public function test_ending_a_future_lesson_cancels_it(): void
    {
        $this->fakeZoom();
        $lesson = Lesson::factory()->scheduled()->create();

        $this->actingAsUser($lesson->teacher)->postJson("/api/lessons/{$lesson->id}/end")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_finished_lesson_hides_join_url(): void
    {
        $lesson = Lesson::factory()->finished()->create();

        $this->actingAsUser($lesson->teacher)->getJson('/api/lessons')
            ->assertJsonPath('data.0.status', 'finished')
            ->assertJsonPath('data.0.join_url', null)
            ->assertJsonPath('data.0.can.start', false);
    }

    public function test_status_is_derived_from_time_even_without_scheduler(): void
    {
        $lesson = Lesson::factory()->create(['status' => LessonStatus::Live, 'starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()]);

        $this->actingAsUser($lesson->teacher)->getJson('/api/lessons?status=finished')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'finished');
    }

    public function test_sync_command_updates_statuses(): void
    {
        $over = Lesson::factory()->create(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()]);
        $started = Lesson::factory()->create(['status' => LessonStatus::Scheduled, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);

        $this->artisan('lessons:sync-statuses')->assertSuccessful();

        $this->assertSame(LessonStatus::Finished, $over->refresh()->status);
        $this->assertNull($over->start_url);
        $this->assertSame(LessonStatus::Live, $started->refresh()->status);
    }

    public function test_signed_telegram_launch_link(): void
    {
        $this->fakeZoom();
        $lesson = Lesson::factory()->create();

        $this->get("/l/{$lesson->id}/start")->assertForbidden();

        $url = URL::temporarySignedRoute('lessons.launch', $lesson->ends_at, ['lesson' => $lesson->id]);
        $this->get($url)->assertRedirect('https://us06web.zoom.us/s/81234567890?zak=fresh');

        $this->travelTo($lesson->ends_at->copy()->addMinute());
        $this->get($url)->assertForbidden();
    }

    public function test_xlsx_export_keeps_formulas_as_text(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Lesson::factory()->finished()->create(['topic' => '=HYPERLINK("http://evil")']);

        $sheets = $this->xlsx($this->actingAsUser($admin)->get('/api/lessons/export'));
        $rows = reset($sheets);

        $this->assertCount(2, $rows);
        $this->assertSame('=HYPERLINK("http://evil")', $rows[1][7]);
    }

    public function test_teacher_cannot_export(): void
    {
        $this->actingAsUser(User::factory()->create())->get('/api/lessons/export')->assertForbidden();
    }
}
