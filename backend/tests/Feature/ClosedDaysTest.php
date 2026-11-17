<?php

namespace Tests\Feature;

use App\Actions\MaterializeSeries;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonSeries;
use App\Models\LessonSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Yakshanba — dam olish kuni: dars yaratilmaydi, haftalik dars qo'shilmaydi, eski jadvallar o'tkazilmaydi. */
class ClosedDaysTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $saturday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saturday = now()->next(Carbon::SATURDAY)->setTime(9, 0);
        $this->travelTo($this->saturday);
    }

    public function test_lesson_cannot_be_created_for_sunday(): void
    {
        $this->fakeZoom();
        $slot = LessonSlot::factory()->create(['starts_at' => '13:00']);
        $group = Group::factory()->create();
        $sunday = $this->saturday->copy()->addDay()->toDateString();
        $monday = $this->saturday->copy()->addDays(2)->toDateString();

        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $slot->id, 'date' => $sunday])
            ->assertStatus(422)
            ->assertJsonValidationErrors('date')
            ->assertJsonPath('errors.date.0', 'Dam olish kuni (Yakshanba) — bu kunga dars yaratib boʻlmaydi.');

        // Haftalik takrorlash ham yakshanbadan boshlanmaydi.
        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $slot->id, 'date' => $sunday, 'repeat_weekly' => true])
            ->assertStatus(422);
        $this->assertSame(0, Lesson::count());
        $this->assertSame(0, LessonSeries::count());
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/meetings'));

        // Dushanba — odatdagidek.
        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/lessons', ['group_id' => $group->id, 'slot_id' => $slot->id, 'date' => $monday])
            ->assertCreated();
    }

    public function test_today_on_sunday_is_closed_and_options_show_it(): void
    {
        $this->fakeZoom();
        $this->travelTo($this->saturday->copy()->addDay()->setTime(9, 0));
        $slot = LessonSlot::factory()->create(['starts_at' => '13:00']);

        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/lessons', ['group_id' => Group::factory()->create()->id, 'slot_id' => $slot->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('date');

        $this->actingAsUser(User::factory()->create())->getJson('/api/lessons/options')
            ->assertOk()
            ->assertJsonPath('data.closed', true)
            ->assertJsonPath('data.slots.0.available', false);
        $this->getJson('/api/config')->assertJsonPath('data.closed_weekdays', [7]);
    }

    public function test_weekly_series_cannot_be_added_for_sunday(): void
    {
        $payload = ['group_id' => Group::factory()->create()->id, 'lesson_slot_id' => LessonSlot::factory()->create()->id, 'weekday' => 7];

        $this->actingAsUser(User::factory()->create())->postJson('/api/series', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('weekday');
        $this->assertSame(0, LessonSeries::count());
    }

    /** Qoida kiritilishidan oldin yakshanbaga qo'shilgan jadval: dars yaratilmaydi, o'qituvchiga xato xabari ham bormaydi. */
    public function test_existing_sunday_series_is_skipped_silently(): void
    {
        Http::fake();
        $series = LessonSeries::create([
            'user_id' => User::factory()->create(['telegram_chat_id' => '777'])->id,
            'group_id' => Group::factory()->create()->id,
            'lesson_slot_id' => LessonSlot::factory()->create(['starts_at' => '13:00'])->id,
            'weekday' => 7,
            'starts_on' => $this->saturday->toDateString(),
        ]);

        $this->travelTo($this->saturday->copy()->addDay()->setTime(12, 30));
        $this->assertSame(['created' => 0, 'failed' => 0], app(MaterializeSeries::class)->run());
        $this->assertSame(0, Lesson::count());
        $this->assertNull($series->fresh()->last_error);
        $this->assertSame([], $series->datesBetween(now()->startOfWeek(), now()->addWeeks(3)));
        Http::assertNothingSent();
    }
}
