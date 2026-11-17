<?php

namespace Tests\Feature;

use App\Actions\SyncLessonAttendance;
use App\Enums\LessonStatus;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\Student;
use App\Models\StudentAlias;
use App\Models\User;
use App\Models\ZoomParticipation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Zoom holatini kuzatish (boshlandi / host tugatdi) va hisobotdan davomat.
 * Vaqtlar: dars 14:30–15:50 (Toshkent) = 09:30–10:50 UTC.
 */
class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-17 14:00', 'Asia/Tashkent'));
        $this->group = Group::factory()->create(['zoom_user_email' => 'zoom_1@karsu.uz']);
    }

    private function lesson(array $attributes = []): Lesson
    {
        return Lesson::factory()->create($attributes + [
            'group_id' => $this->group->id,
            'zoom_meeting_id' => '81234567890',
            'status' => LessonStatus::Scheduled,
            'starts_at' => Carbon::parse('2026-11-17 14:30', 'Asia/Tashkent'),
            'ends_at' => Carbon::parse('2026-11-17 15:50', 'Asia/Tashkent'),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function participants(): array
    {
        return [
            ['id' => 'host1', 'name' => 'Zoom 1', 'user_email' => 'zoom_1@karsu.uz', 'join_time' => '2026-11-17T09:33:00Z', 'leave_time' => '2026-11-17T10:48:00Z'],
            ['id' => '', 'name' => 'Ali Valiyev', 'user_email' => '', 'join_time' => '2026-11-17T09:33:00Z', 'leave_time' => '2026-11-17T10:00:00Z'],
            // O'sha talaba telefondan ham kirgan — ustma-ust 5 daqiqa bir marta hisoblanadi.
            ['id' => '', 'name' => 'ali valiev (iPhone)', 'user_email' => '', 'join_time' => '2026-11-17T09:55:00Z', 'leave_time' => '2026-11-17T10:48:00Z'],
            ['id' => '', 'name' => 'Vali', 'user_email' => '', 'join_time' => '2026-11-17T09:45:00Z', 'leave_time' => '2026-11-17T10:30:00Z'],
            ['id' => '', 'name' => 'Kutib qolgan', 'user_email' => '', 'join_time' => '2026-11-17T09:40:00Z', 'leave_time' => '2026-11-17T09:41:00Z', 'status' => 'in_waiting_room'],
        ];
    }

    private function fakeZoomReports(array $statuses = ['waiting'], ?array $participants = null, array $extra = []): void
    {
        $sequence = Http::sequence();
        foreach ($statuses as $status) {
            $sequence->push(['id' => 81234567890, 'status' => $status]);
        }
        $sequence->whenEmpty(Http::response(['id' => 81234567890, 'status' => end($statuses)]));

        Http::fake($extra + [
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'zoom-token', 'expires_in' => 3600]),
            'api.zoom.us/v2/past_meetings/*/instances' => Http::response(['meetings' => [['uuid' => 'abc==', 'start_time' => '2026-11-17T09:33:00Z']]]),
            'api.zoom.us/v2/past_meetings/abc%3D%3D/participants*' => Http::response(['code' => 3001, 'message' => 'Meeting does not exist'], 404),
            'api.zoom.us/v2/past_meetings/abc%3D%3D' => Http::response([
                'start_time' => '2026-11-17T09:33:00Z', 'end_time' => '2026-11-17T10:48:00Z', 'host_id' => 'host1', 'participants_count' => 4,
            ]),
            'api.zoom.us/v2/report/meetings/*/participants*' => Http::response(['participants' => $participants ?? $this->participants(), 'next_page_token' => '']),
            'api.zoom.us/v2/meetings/*' => $sequence,
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
    }

    private function at(string $time): void
    {
        $this->travelTo(Carbon::parse('2026-11-17 '.$time, 'Asia/Tashkent'));
    }

    public function test_tracker_detects_start_host_end_and_builds_attendance(): void
    {
        $this->fakeZoomReports(['waiting', 'started', 'started', 'waiting']);
        $lesson = $this->lesson();

        $this->at('14:25');
        $this->artisan('lessons:track-zoom')->assertSuccessful();
        $this->assertSame(Lesson::ZOOM_WAITING, $lesson->refresh()->zoom_state);

        $this->at('14:33');
        $this->artisan('lessons:track-zoom');
        $lesson->refresh();
        $this->assertSame(Lesson::ZOOM_STARTED, $lesson->zoom_state);
        $this->assertSame('14:33', $lesson->zoom_started_at->format('H:i'));

        $this->at('15:00');
        $this->artisan('lessons:track-zoom');
        $this->assertSame(Lesson::ZOOM_STARTED, $lesson->refresh()->zoom_state);

        // Host 15:48 da tugatdi — dars tizimda ham yakunlanadi, host bo'shaydi.
        $this->at('15:48');
        $this->artisan('lessons:track-zoom');
        $lesson->refresh();
        $this->assertSame(Lesson::ZOOM_ENDED, $lesson->zoom_state);
        $this->assertSame(LessonStatus::Finished, $lesson->status);
        $this->assertSame('15:48', $lesson->ended_at->format('H:i'));
        $this->assertNull($lesson->start_url);
        $this->assertNull($lesson->attendance_synced_at);

        // 3 daqiqadan keyin Zoom hisoboti olinadi.
        $this->at('15:52');
        $this->artisan('lessons:track-zoom');
        $lesson->refresh();
        $this->assertNotNull($lesson->attendance_synced_at);
        $this->assertSame(3, $lesson->startDelayMinutes());
        $this->assertSame(75, $lesson->actualMinutes());

        $ali = Student::where('name', 'Ali Valiyev')->firstOrFail();
        $vali = Student::where('name', 'Vali')->firstOrFail();
        $this->assertSame(2, Student::count(), 'host va kutish zalidagilar talaba emas, "ali valiev (iPhone)" — Ali Valiyev');

        $aliRow = LessonAttendance::where('student_id', $ali->id)->firstOrFail();
        $this->assertSame(75 * 60, $aliRow->seconds);
        $this->assertSame(2, $aliRow->sessions);
        $this->assertSame(0, $aliRow->late_seconds);

        $valiRow = LessonAttendance::where('student_id', $vali->id)->firstOrFail();
        $this->assertSame(45 * 60, $valiRow->seconds);
        $this->assertSame(12 * 60, $valiRow->late_seconds);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/report/meetings/abc%3D%3D/participants'));
    }

    public function test_lesson_that_never_started_is_marked_missed(): void
    {
        $this->fakeZoomReports(['waiting'], extra: [
            'api.zoom.us/v2/past_meetings/*/instances' => Http::response(['meetings' => []]),
        ]);
        $lesson = $this->lesson();

        $this->at('16:06');
        $this->artisan('lessons:track-zoom');
        $this->artisan('lessons:track-zoom');

        $lesson->refresh();
        $this->assertSame(Lesson::ZOOM_MISSED, $lesson->zoom_state);
        $this->assertNotNull($lesson->attendance_synced_at);
        $this->assertNull($lesson->startDelayMinutes());
    }

    public function test_students_are_recognised_across_lessons_by_name_and_zoom_id(): void
    {
        $meetings = [
            '81111111111' => ['uuid' => 'm1', 'day' => '2026-11-17', 'participants' => [
                ['id' => '', 'name' => 'Ali Valiyev', 'user_email' => '', 'join_time' => '2026-11-17T09:33:00Z', 'leave_time' => '2026-11-17T10:48:00Z'],
                ['id' => '', 'name' => 'Vali', 'user_email' => '', 'join_time' => '2026-11-17T09:45:00Z', 'leave_time' => '2026-11-17T10:30:00Z'],
            ]],
            '82222222222' => ['uuid' => 'm2', 'day' => '2026-11-18', 'participants' => [
                ['id' => '', 'name' => 'VALIYEV ALI', 'user_email' => '', 'join_time' => '2026-11-18T09:31:00Z', 'leave_time' => '2026-11-18T10:50:00Z'],
                // Vali bu safar Zoom'ga kirgan holda keldi — Zoom ID eslab qolinadi.
                ['id' => 'zu-7', 'name' => 'Vali', 'user_email' => 'vali@mail.uz', 'join_time' => '2026-11-18T09:31:00Z', 'leave_time' => '2026-11-18T10:00:00Z'],
            ]],
            '83333333333' => ['uuid' => 'm3', 'day' => '2026-11-19', 'participants' => [
                // Boshqa ism bilan, lekin o'sha Zoom hisobi — Vali deb tanilinadi.
                ['participant_user_id' => 'zu-7', 'id' => '', 'name' => 'Valijon Karimov', 'user_email' => '', 'join_time' => '2026-11-19T09:31:00Z', 'leave_time' => '2026-11-19T10:00:00Z'],
            ]],
        ];
        $byUuid = collect($meetings)->keyBy('uuid');

        Http::fake(function (Request $request) use ($meetings, $byUuid) {
            $url = $request->url();
            if (str_contains($url, 'oauth/token')) {
                return Http::response(['access_token' => 'zoom-token', 'expires_in' => 3600]);
            }
            if (preg_match('#/past_meetings/(\d+)/instances#', $url, $m)) {
                return Http::response(['meetings' => [['uuid' => $meetings[$m[1]]['uuid'], 'start_time' => $meetings[$m[1]]['day'].'T09:30:00Z']]]);
            }
            if (preg_match('#/report/meetings/(m\d)/participants#', $url, $m)) {
                return Http::response(['participants' => $byUuid[$m[1]]['participants']]);
            }
            if (preg_match('#/past_meetings/(m\d)$#', $url, $m)) {
                $day = $byUuid[$m[1]]['day'];

                return Http::response(['start_time' => $day.'T09:30:00Z', 'end_time' => $day.'T10:50:00Z', 'host_id' => 'host1']);
            }

            return Http::response([], 404);
        });

        foreach (array_keys($meetings) as $meetingId) {
            $lesson = $this->lesson(['zoom_meeting_id' => (string) $meetingId, 'zoom_state' => Lesson::ZOOM_ENDED, 'status' => LessonStatus::Finished]);
            $this->assertTrue(app(SyncLessonAttendance::class)->handle($lesson));
        }

        $this->assertSame(['Ali Valiyev', 'Vali'], Student::orderBy('name')->pluck('name')->all());
        $vali = Student::where('name', 'Vali')->firstOrFail();
        $this->assertSame(3, LessonAttendance::where('student_id', $vali->id)->count());
        $this->assertSame(2, LessonAttendance::where('student_id', Student::where('name', 'Ali Valiyev')->value('id'))->count());

        // Talabaning emaili ochiq saqlanmaydi: faqat tanish uchun xesh.
        $this->assertSame(0, ZoomParticipation::whereNotNull('email')->count());
        $this->assertSame(StudentAlias::emailKey('vali@mail.uz'), StudentAlias::where('type', StudentAlias::EMAIL)->value('value'));
        $this->assertDatabaseMissing('student_aliases', ['value' => 'vali@mail.uz']);
    }

    public function test_missing_report_scope_falls_back_to_past_meeting_participants(): void
    {
        $this->fakeZoomReports(extra: [
            'api.zoom.us/v2/report/meetings/*/participants*' => Http::response(['code' => 4711, 'message' => 'Invalid access token, does not contain scopes'], 400),
            'api.zoom.us/v2/past_meetings/abc%3D%3D/participants*' => Http::response(['participants' => $this->participants()]),
        ]);
        $lesson = $this->lesson(['zoom_state' => Lesson::ZOOM_ENDED, 'status' => LessonStatus::Finished]);

        $this->assertTrue(app(SyncLessonAttendance::class)->handle($lesson));
        $this->assertSame(2, LessonAttendance::where('lesson_id', $lesson->id)->count());
    }

    public function test_report_not_ready_is_retried_later(): void
    {
        $this->fakeZoomReports(extra: [
            'api.zoom.us/v2/report/meetings/*/participants*' => Http::response(['code' => 3001, 'message' => 'Meeting does not exist'], 404),
        ]);
        $lesson = $this->lesson(['zoom_state' => Lesson::ZOOM_ENDED, 'status' => LessonStatus::Finished, 'attendance_due_at' => now()]);

        $this->artisan('lessons:track-zoom');

        $lesson->refresh();
        $this->assertNull($lesson->attendance_synced_at);
        $this->assertSame(1, $lesson->attendance_attempts);
        $this->assertSame('Zoom hisoboti hali tayyor emas.', $lesson->attendance_error);
        $this->assertSame(now()->addMinutes(3)->format('H:i'), $lesson->attendance_due_at->format('H:i'));
    }

    public function test_ending_lesson_in_system_schedules_report(): void
    {
        $this->fakeZoomReports(extra: ['api.zoom.us/v2/meetings/*/status' => Http::response(null, 204)]);
        $teacher = User::factory()->create();
        $lesson = $this->lesson(['user_id' => $teacher->id, 'status' => LessonStatus::Live, 'zoom_state' => Lesson::ZOOM_STARTED, 'zoom_started_at' => now()]);
        $this->at('15:00');

        $this->actingAsUser($teacher)->postJson("/api/lessons/{$lesson->id}/end")->assertOk();

        $lesson->refresh();
        $this->assertSame(Lesson::ZOOM_ENDED, $lesson->zoom_state);
        $this->assertSame('15:00', $lesson->zoom_ended_at->format('H:i'));
        $this->assertSame('15:03', $lesson->attendance_due_at->format('H:i'));
    }

    public function test_attendance_endpoint_shows_students_to_lesson_owner_only(): void
    {
        $this->fakeZoomReports();
        $teacher = User::factory()->create();
        $lesson = $this->lesson(['user_id' => $teacher->id, 'zoom_state' => Lesson::ZOOM_ENDED, 'status' => LessonStatus::Finished]);
        app(SyncLessonAttendance::class)->handle($lesson);
        $this->at('16:30');

        $this->actingAsUser($teacher)->getJson("/api/lessons/{$lesson->id}/attendance")
            ->assertOk()
            ->assertJsonPath('data.lesson.tracking.state', 'ended')
            ->assertJsonPath('data.lesson.tracking.start_delay_minutes', 3)
            ->assertJsonPath('data.lesson.tracking.minutes', 75)
            ->assertJsonPath('data.lesson.tracking.participants', 2)
            ->assertJsonPath('data.students.0.name', 'ALI VALIYEV')
            ->assertJsonPath('data.students.0.minutes', 75)
            ->assertJsonPath('data.students.0.percent', 100)
            ->assertJsonPath('data.students.0.names', ['ALI VALIYEV', 'ALI VALIEV (IPHONE)'])
            ->assertJsonPath('data.students.0.segments', [['2026-11-17T14:33:00+05:00', '2026-11-17T15:48:00+05:00']])
            ->assertJsonPath('data.students.1.segments', [['2026-11-17T14:45:00+05:00', '2026-11-17T15:30:00+05:00']])
            ->assertJsonPath('data.students.1.name', 'VALI')
            ->assertJsonPath('data.students.1.late_minutes', 12)
            ->assertJsonPath('data.students.1.percent', 60)
            ->assertJsonPath('data.host.names', ['ZOOM 1'])
            ->assertJsonPath('data.can_manage_students', false);

        $this->actingAsUser(User::factory()->create())->getJson("/api/lessons/{$lesson->id}/attendance")->assertForbidden();

        $rows = collect($this->xlsx($this->actingAsUser($teacher)->get("/api/lessons/{$lesson->id}/attendance/export")))->first();
        $rows = collect($rows);
        $ali = $rows->first(fn (array $row) => $row[0] === 'ALI VALIYEV');
        $this->assertSame([75, 100], [$ali[5], $ali[6]]);
    }

    public function test_admin_merges_students_and_merge_is_remembered(): void
    {
        $this->fakeZoomReports();
        $admin = User::factory()->education()->create();
        $lesson = $this->lesson(['zoom_state' => Lesson::ZOOM_ENDED, 'status' => LessonStatus::Finished]);
        app(SyncLessonAttendance::class)->handle($lesson);
        $ali = Student::where('name', 'Ali Valiyev')->firstOrFail();
        $vali = Student::where('name', 'Vali')->firstOrFail();

        $this->actingAsUser($admin)->getJson("/api/groups/{$this->group->id}/students?from=2026-11-01&to=2026-11-30")
            ->assertOk()
            ->assertJsonPath('data.lessons_held', 1)
            ->assertJsonCount(2, 'data.students')
            ->assertJsonPath('data.students.0.lessons_attended', 1);

        $this->actingAsUser($admin)->postJson("/api/groups/{$this->group->id}/students/merge", [
            'target_id' => $ali->id,
            'source_ids' => [$vali->id],
        ])->assertOk()->assertJsonPath('data.merged', 1);

        $this->assertModelMissing($vali);
        $row = LessonAttendance::where('student_id', $ali->id)->firstOrFail();
        $this->assertSame(3, $row->sessions);
        $this->assertSame(75 * 60, $row->seconds, 'ustma-ust vaqt bir marta');

        // Keyingi sinxronlashda "Vali" endi Ali Valiyev deb tanilinadi.
        app(SyncLessonAttendance::class)->handle($lesson);
        $this->assertSame(1, Student::count());

        // O'qituvchi guruh talabalarini boshqara olmaydi.
        $this->actingAsUser(User::factory()->create())->getJson("/api/groups/{$this->group->id}/students")->assertForbidden();
    }

    public function test_ignored_student_is_not_counted(): void
    {
        $this->fakeZoomReports();
        $admin = User::factory()->superAdmin()->create();
        $lesson = $this->lesson(['zoom_state' => Lesson::ZOOM_ENDED, 'status' => LessonStatus::Finished]);
        app(SyncLessonAttendance::class)->handle($lesson);
        $vali = Student::where('name', 'Vali')->firstOrFail();

        $this->actingAsUser($admin)->patchJson("/api/students/{$vali->id}", ['ignored' => true, 'name' => 'Vali (oʻqituvchi)'])
            ->assertOk()
            ->assertJsonPath('data.ignored', true);

        $this->actingAsUser($admin)->getJson("/api/lessons/{$lesson->id}/attendance")
            ->assertJsonPath('data.lesson.tracking.participants', 1)
            ->assertJsonPath('data.can_manage_students', true);
    }

    public function test_report_shows_start_delay_and_missed_lessons(): void
    {
        $this->fakeZoomReports();
        $admin = User::factory()->superAdmin()->create();
        $teacher = User::factory()->create();
        $held = $this->lesson(['user_id' => $teacher->id, 'zoom_state' => Lesson::ZOOM_ENDED, 'status' => LessonStatus::Finished]);
        app(SyncLessonAttendance::class)->handle($held);
        $this->lesson(['user_id' => $teacher->id, 'zoom_meeting_id' => '86666666666', 'zoom_state' => Lesson::ZOOM_MISSED, 'status' => LessonStatus::Finished]);
        $this->at('18:00');

        $this->actingAsUser($admin)->getJson('/api/reports/summary?from=2026-11-17&to=2026-11-17')
            ->assertOk()
            ->assertJsonPath('data.totals.held', 1)
            ->assertJsonPath('data.totals.missed', 1)
            ->assertJsonPath('data.by_teacher.0.avg_start_delay', 3)
            ->assertJsonPath('data.by_teacher.0.actual_minutes', 75)
            ->assertJsonPath('data.by_group.0.avg_participants', 2);
    }
}
