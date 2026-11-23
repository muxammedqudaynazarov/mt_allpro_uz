<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Lesson;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Zoom'ni avtomatik yakunlash: juftlikning foizi (0 — uzilmaydi, 50% — 80 daqiqadan 40). */
class ZoomAutoEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_sets_percent_between_0_and_100(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAsUser($admin)->putJson('/api/settings', ['zoom' => ['auto_end_percent' => 50]])
            ->assertOk()
            ->assertJsonPath('data.zoom.auto_end_percent', 50);
        $this->actingAsUser($admin)->putJson('/api/settings', ['zoom' => ['auto_end_percent' => 101]])->assertStatus(422)->assertJsonValidationErrors('zoom.auto_end_percent');
        $this->actingAsUser($admin)->putJson('/api/settings', ['zoom' => ['auto_end_percent' => -1]])->assertStatus(422);
        $this->actingAsUser(User::factory()->education()->create())->putJson('/api/settings', ['zoom' => ['auto_end_percent' => 10]])->assertForbidden();
        $this->assertSame(50, Setting::get('zoom')['auto_end_percent']);
    }

    public function test_lesson_is_created_for_part_of_slot_when_enabled(): void
    {
        $this->fakeZoom();
        Setting::put('zoom', ['auto_end_percent' => 50]);
        $slot = $this->slotSoon();

        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/lessons', ['group_id' => Group::factory()->create()->id, 'slot_id' => $slot->id])
            ->assertCreated();

        $lesson = Lesson::firstOrFail();
        $this->assertSame(40, (int) $lesson->starts_at->diffInMinutes($lesson->ends_at));
        $this->assertTrue($lesson->auto_end);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/meetings') && $request['duration'] === 40);

        // Dars yaratish oynasidagi oldindan ko'rish ham shu vaqtni ko'rsatadi.
        $preview = $this->actingAsUser(User::factory()->create())->getJson('/api/lessons/options?slot_id='.$slot->id)->assertOk()->json('data');
        $this->assertSame(40, (int) round((strtotime($preview['to']) - strtotime($preview['from'])) / 60));
    }

    public function test_ten_percent_is_eight_minutes_and_zero_keeps_full_slot(): void
    {
        $this->fakeZoom();
        Setting::put('zoom', ['auto_end_percent' => 10]);
        $this->actingAsUser(User::factory()->create())->postJson('/api/lessons', ['group_id' => Group::factory()->create()->id, 'slot_id' => $this->slotSoon()->id])->assertCreated();
        $short = Lesson::query()->latest('id')->firstOrFail();
        $this->assertSame(8, (int) $short->starts_at->diffInMinutes($short->ends_at));

        Setting::put('zoom', ['auto_end_percent' => 0]);
        $this->actingAsUser(User::factory()->create())->postJson('/api/lessons', ['group_id' => Group::factory()->create()->id, 'slot_id' => $this->slotSoon()->id])->assertCreated();
        $full = Lesson::query()->latest('id')->firstOrFail();
        $this->assertSame(80, (int) $full->starts_at->diffInMinutes($full->ends_at));
        $this->assertFalse($full->auto_end);
    }

    public function test_command_ends_only_running_auto_end_lessons_whose_time_came(): void
    {
        $this->fakeZoom(['api.zoom.us/v2/meetings/*/status' => Http::response(null, 204)]);
        $due = Lesson::factory()->create(['auto_end' => true, 'zoom_state' => Lesson::ZOOM_STARTED, 'zoom_meeting_id' => '111', 'starts_at' => now()->subMinutes(41), 'ends_at' => now()->subMinute()]);
        $notStarted = Lesson::factory()->create(['auto_end' => true, 'zoom_state' => Lesson::ZOOM_WAITING, 'zoom_meeting_id' => '222', 'starts_at' => now()->subMinutes(41), 'ends_at' => now()->subMinute()]);
        $manual = Lesson::factory()->create(['auto_end' => false, 'zoom_state' => Lesson::ZOOM_STARTED, 'zoom_meeting_id' => '333', 'starts_at' => now()->subMinutes(41), 'ends_at' => now()->subMinute()]);
        $early = Lesson::factory()->create(['auto_end' => true, 'zoom_state' => Lesson::ZOOM_STARTED, 'zoom_meeting_id' => '444', 'starts_at' => now()->subMinutes(10), 'ends_at' => now()->addMinutes(30)]);

        $this->artisan('lessons:auto-end')->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/meetings/111/status') && $request['action'] === 'end');
        foreach (['222', '333', '444'] as $id) {
            Http::assertNotSent(fn (Request $request) => str_contains($request->url(), "/meetings/{$id}/"));
        }
        $this->assertSame(Lesson::ZOOM_ENDED, $due->fresh()->zoom_state);
        $this->assertSame('finished', $due->fresh()->status->value);
        $this->assertSame(Lesson::ZOOM_STARTED, $manual->fresh()->zoom_state);
        $this->assertSame(Lesson::ZOOM_STARTED, $early->fresh()->zoom_state);
        $this->assertSame(Lesson::ZOOM_WAITING, $notStarted->fresh()->zoom_state);
    }
}
