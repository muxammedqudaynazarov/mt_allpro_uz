<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Foydalanuvchilarning onlayn/oflayn holati va kirish tarixi. */
class UserPresenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-27 09:00', 'Asia/Tashkent'));
    }

    public function test_visits_are_recorded_from_login_to_logout(): void
    {
        $admin = User::factory()->superAdmin()->create(['login' => 'admin', 'password' => 'secret-password-123']);

        $this->postJson('/api/auth/login', ['login' => 'admin', 'password' => 'secret-password-123'])->assertOk();
        $visit = UserVisit::sole();
        $this->assertSame('09:00', $visit->started_at->format('H:i'));
        $this->assertNull($visit->ended_at);
        $this->assertTrue($admin->fresh()->online_until->isFuture());

        // Sayt ochiq: har daqiqada ping — tashrif davom etadi.
        foreach ([1, 2, 3] as $minute) {
            $this->travelTo(Carbon::parse('2026-11-27 09:0'.$minute.':30', 'Asia/Tashkent'));
            $this->getJson('/api/auth/ping')->assertNoContent();
        }
        $this->assertSame('09:03', $visit->fresh()->last_seen_at->format('H:i'));

        $this->travelTo(Carbon::parse('2026-11-27 09:40', 'Asia/Tashkent'));
        $this->postJson('/api/auth/logout')->assertNoContent();
        $visit->refresh();
        // Oxirgi faollik 09:03 edi, lekin "Chiqish" tugmasi bosilgan vaqt yoziladi.
        $this->assertSame(['09:40', UserVisit::LOGOUT], [$visit->ended_at->format('H:i'), $visit->end_reason]);
        $this->assertFalse($admin->fresh()->online_until->isFuture());
    }

    public function test_long_break_starts_new_visit_and_online_status_expires(): void
    {
        $teacher = User::factory()->create();
        $viewer = User::factory()->education()->create();

        $this->actingAsUser($teacher)->getJson('/api/auth/ping')->assertNoContent();
        $this->travelTo(Carbon::parse('2026-11-27 09:10', 'Asia/Tashkent'));
        $this->actingAsUser($teacher)->getJson('/api/dashboard')->assertOk();

        $row = fn () => collect($this->actingAsUser($viewer)->getJson('/api/users')->json('data'))->firstWhere('id', $teacher->id);
        $this->assertTrue($row()['online']);

        // 4 daqiqa faollik yo'q — oflayn, oxirgi faollik ko'rinadi.
        $this->travelTo(Carbon::parse('2026-11-27 09:14', 'Asia/Tashkent'));
        $this->assertFalse($row()['online']);
        $this->assertSame('09:10', Carbon::parse($row()['last_seen_at'])->setTimezone('Asia/Tashkent')->format('H:i'));

        // Soat 11 da qaytdi — oldingi tashrif 09:10 da "faol emas" bilan yopiladi, yangisi ochiladi.
        $this->travelTo(Carbon::parse('2026-11-27 11:00', 'Asia/Tashkent'));
        $this->actingAsUser($teacher)->getJson('/api/auth/ping')->assertNoContent();
        $this->assertSame(2, UserVisit::where('user_id', $teacher->id)->count());

        $visits = $this->actingAsUser($viewer)->getJson("/api/users/{$teacher->id}/visits")->assertOk()->json();
        $this->assertTrue($visits['user']['online']);
        $this->assertSame([true, false], array_column($visits['data'], 'online'));
        $this->assertSame([null, UserVisit::IDLE], array_column($visits['data'], 'end_reason'));
        $this->assertSame(10, $visits['data'][1]['minutes']);

        // O'qituvchi boshqalarning tarixini ko'rmaydi.
        $this->actingAsUser($teacher)->getJson("/api/users/{$viewer->id}/visits")->assertForbidden();
    }

    public function test_presence_writes_are_throttled_and_do_not_touch_updated_at(): void
    {
        $teacher = User::factory()->create();
        $updated = $teacher->updated_at;

        $this->actingAsUser($teacher)->getJson('/api/auth/ping');
        $this->travelTo(Carbon::parse('2026-11-27 09:00:40', 'Asia/Tashkent'));
        $this->actingAsUser($teacher)->getJson('/api/auth/ping');

        $fresh = $teacher->fresh();
        $this->assertSame('09:00:00', $fresh->last_seen_at->setTimezone('Asia/Tashkent')->format('H:i:s'));
        $this->assertEquals($updated, $fresh->updated_at);
        $this->assertSame(1, UserVisit::count());
    }
}
