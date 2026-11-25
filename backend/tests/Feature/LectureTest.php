<?php

namespace Tests\Feature;

use App\Actions\MaterializeSeries;
use App\Actions\SyncLessonAttendance;
use App\Enums\LessonStatus;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\LessonSeries;
use App\Models\LessonSlot;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentAlias;
use App\Models\User;
use App\Models\ZoomParticipation;
use App\Support\StudentName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Ma'ruza: bir Zoom uchrashuvida bir nechta guruh; davomat va hisobot guruhlar bo'yicha. */
class LectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecture_is_one_meeting_for_several_groups(): void
    {
        $this->fakeZoom();
        $teacher = User::factory()->create();
        [$a, $b, $c] = [Group::factory()->create(['name' => '101', 'course' => 1]), Group::factory()->create(['name' => '102', 'course' => 1]), Group::factory()->create(['name' => '103', 'course' => 1])];
        $slot = $this->slotSoon();

        $response = $this->actingAsUser($teacher)
            ->postJson('/api/lessons', ['is_lecture' => true, 'group_ids' => [$b->id, $a->id, $c->id], 'slot_id' => $slot->id])
            ->assertCreated()
            ->assertJsonPath('data.is_lecture', true)
            ->assertJsonPath('data.group.id', $b->id)
            ->assertJsonCount(3, 'data.groups');
        $this->assertSame(['101', '102', '103'], array_column($response->json('data.groups'), 'name'));

        Http::assertSentCount(2); // OAuth token + bitta uchrashuv
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/meetings') && $request['topic'] === 'Maʼruza: 102, 101, 103');

        $lesson = Lesson::firstOrFail();
        foreach ([$a, $b, $c] as $group) {
            $this->assertTrue(Lesson::query()->forGroup($group->id)->whereKey($lesson->id)->exists());
            $this->actingAsUser($teacher)->getJson('/api/lessons?group_id='.$group->id)->assertJsonPath('data.0.id', $lesson->id);
        }
        // Shu vaqtda ma'ruzadagi har bir guruh band.
        $options = collect($this->actingAsUser($teacher)->getJson('/api/lessons/options?slot_id='.$slot->id)->json('data.groups'))->keyBy('id');
        $this->assertTrue($options[$a->id]['busy'] && $options[$c->id]['busy']);
    }

    public function test_group_conflicts_include_lectures(): void
    {
        $this->fakeZoom();
        $teacher = User::factory()->create();
        [$a, $b, $c] = [Group::factory()->create(['name' => '101']), Group::factory()->create(['name' => '102']), Group::factory()->create(['name' => '103'])];
        $slot = $this->slotSoon();

        $this->actingAsUser($teacher)->postJson('/api/lessons', ['group_id' => $b->id, 'slot_id' => $slot->id])->assertCreated();
        // 102 da shu vaqtda amaliy dars bor — ma'ruzaga qo'shib bo'lmaydi.
        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/lessons', ['is_lecture' => true, 'group_ids' => [$a->id, $b->id], 'slot_id' => $slot->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.group_id.0', fn (string $message) => str_contains($message, '102'));

        $this->actingAsUser(User::factory()->create())->postJson('/api/lessons', ['is_lecture' => true, 'group_ids' => [$a->id, $c->id], 'slot_id' => $slot->id])->assertCreated();
        // 103 ma'ruzada band — unga alohida dars ham yaratilmaydi.
        $this->actingAsUser(User::factory()->create())->postJson('/api/lessons', ['group_id' => $c->id, 'slot_id' => $slot->id])->assertStatus(422)->assertJsonValidationErrors('group_id');
    }

    public function test_lecture_input_is_validated(): void
    {
        $this->fakeZoom();
        $teacher = User::factory()->create();
        $slot = $this->slotSoon();
        $groups = Group::factory()->count(Lesson::MAX_LECTURE_GROUPS + 1)->create();

        $this->actingAsUser($teacher)->postJson('/api/lessons', ['is_lecture' => true, 'slot_id' => $slot->id])->assertStatus(422)->assertJsonValidationErrors('group_ids');
        $this->actingAsUser($teacher)->postJson('/api/lessons', ['is_lecture' => true, 'group_ids' => $groups->modelKeys(), 'slot_id' => $slot->id])->assertStatus(422)->assertJsonValidationErrors('group_ids');
        $this->actingAsUser($teacher)->postJson('/api/lessons', ['is_lecture' => true, 'group_ids' => [$groups[0]->id, $groups[0]->id], 'slot_id' => $slot->id])->assertStatus(422);
        // Oddiy darsda guruhlar ro'yxati qabul qilinmaydi — faqat bitta guruh.
        $this->actingAsUser($teacher)->postJson('/api/lessons', ['group_ids' => [$groups[0]->id, $groups[1]->id], 'slot_id' => $slot->id])->assertStatus(422)->assertJsonValidationErrors('group_id');
        $groups[1]->update(['is_active' => false]);
        $this->actingAsUser($teacher)->postJson('/api/lessons', ['is_lecture' => true, 'group_ids' => [$groups[0]->id, $groups[1]->id], 'slot_id' => $slot->id])->assertStatus(422)->assertJsonValidationErrors('group_id');
        $this->assertSame(0, Lesson::count());
    }

    public function test_attendance_matches_students_of_every_group_and_keeps_unknown_unassigned(): void
    {
        [$lesson, $a, $b] = $this->finishedLecture();
        $ali = $this->student($a, 'Ali Valiyev');
        $vali = $this->student($b, 'Vali Karimov');
        // HEMIS'dan olingan ism (alias hali yo'q) ham taniladi.
        $guli = Student::create(['group_id' => $b->id, 'name' => 'GULI SAIDOVA']);
        $this->fakeReport([
            $this->participant('Ali Valiyev', '09:33', '10:48'),
            $this->participant('vali karimov', '09:40', '10:40'),
            $this->participant('Guli Saidova', '09:35', '10:45'),
            $this->participant('Nomaʼlum talaba', '09:50', '10:30', 'zoom-x'),
        ]);
        $students = Student::count();

        $this->assertTrue(app(SyncLessonAttendance::class)->handle($lesson));

        $this->assertSame($students, Student::count(), 'Maʼruzada yangi talaba avtomatik yaratilmaydi');
        $this->assertEqualsCanonicalizing([$ali->id, $vali->id, $guli->id], LessonAttendance::where('lesson_id', $lesson->id)->pluck('student_id')->all());
        $this->assertNull(ZoomParticipation::where('name', 'Nomaʼlum talaba')->value('student_id'));

        $data = $this->actingAsUser($lesson->teacher)->getJson("/api/lessons/{$lesson->id}/attendance")->assertOk()->json('data');
        $this->assertCount(2, $data['groups']);
        $this->assertSame([$a->id, $b->id, $b->id], collect($data['students'])->sortBy('name')->pluck('group_id')->values()->all());
        $this->assertSame([['NOMAʼLUM TALABA', 'z:zoom-x', 40]], collect($data['unassigned'])->map(fn ($row) => [$row['name'], $row['key'], $row['minutes']])->all());
        $this->assertTrue($data['can_assign']);
        $this->assertSame(50, $data['presence_percent']);

        // Excel: guruh ustuni, aniqlanmaganlar oxirida.
        $sheets = $this->xlsx($this->actingAsUser($lesson->teacher)->get("/api/lessons/{$lesson->id}/attendance/export"));
        $sheet = collect(reset($sheets));
        $this->assertSame(['Talaba', 'Guruh'], array_slice($sheet->first(fn (array $row) => ($row[0] ?? null) === 'Talaba'), 0, 2));
        $this->assertSame(['NOMAʼLUM TALABA', 'Aniqlanmagan'], array_slice($sheet->last(), 0, 2));

        // O'qituvchi guruhni tanlaydi — talaba shu guruhda yaratiladi va davomatga qo'shiladi.
        $this->actingAsUser($lesson->teacher)->postJson("/api/lessons/{$lesson->id}/attendance/assign", ['key' => 'z:zoom-x', 'group_id' => $a->id])
            ->assertOk()
            ->assertJsonCount(0, 'data.unassigned')
            ->assertJsonCount(4, 'data.students');
        $created = Student::where('group_id', $a->id)->where('name', 'Nomaʼlum talaba')->firstOrFail();
        $this->assertDatabaseHas('lesson_attendances', ['lesson_id' => $lesson->id, 'student_id' => $created->id, 'seconds' => 40 * 60]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.assigned']);

        // Boshqa guruhga yoki qaytadan biriktirib bo'lmaydi; begona o'qituvchi umuman ko'rmaydi.
        $this->actingAsUser($lesson->teacher)->postJson("/api/lessons/{$lesson->id}/attendance/assign", ['key' => 'z:zoom-x', 'group_id' => $a->id])->assertNotFound();
        $this->actingAsUser($lesson->teacher)->postJson("/api/lessons/{$lesson->id}/attendance/assign", ['key' => 'z:zoom-x', 'group_id' => Group::factory()->create()->id])->assertStatus(422);
        $this->actingAsUser(User::factory()->create())->postJson("/api/lessons/{$lesson->id}/attendance/assign", ['key' => 'z:zoom-x', 'group_id' => $a->id])->assertForbidden();

        // Keyingi ma'ruzada Zoom ID bo'yicha avtomatik taniladi.
        [$next] = $this->finishedLecture([$a, $b], '2026-11-24');
        $this->fakeReport([$this->participant('Boshqa ism', '09:40', '10:40', 'zoom-x')], '2026-11-24');
        app(SyncLessonAttendance::class)->handle($next);
        $this->assertSame([$created->id], LessonAttendance::where('lesson_id', $next->id)->pluck('student_id')->all());
    }

    public function test_namesakes_in_two_groups_are_not_guessed(): void
    {
        [$lesson, $a, $b] = $this->finishedLecture();
        $this->student($a, 'Ali Valiyev');
        $this->student($b, 'Ali Valiyev');
        $this->fakeReport([$this->participant('Ali Valiyev', '09:33', '10:48')]);

        app(SyncLessonAttendance::class)->handle($lesson);

        $this->assertSame(0, LessonAttendance::where('lesson_id', $lesson->id)->count());
        $this->assertCount(1, $this->actingAsUser($lesson->teacher)->getJson("/api/lessons/{$lesson->id}/attendance")->json('data.unassigned'));
    }

    public function test_semester_report_shows_lecture_and_practice_separately(): void
    {
        Semester::factory()->create();
        $a = Group::factory()->create();
        $b = Group::factory()->create();
        $ali = Student::create(['group_id' => $a->id, 'name' => 'Ali Valiyev']);
        $vali = Student::create(['group_id' => $b->id, 'name' => 'Vali Karimov']);
        $held = fn (array $attributes) => Lesson::factory()->create($attributes + [
            'status' => LessonStatus::Finished, 'zoom_state' => Lesson::ZOOM_ENDED, 'zoom_minutes' => 80, 'attendance_synced_at' => now(),
        ]);
        $lecture1 = $held(['group_id' => $a->id, 'is_lecture' => true, 'starts_at' => now()->subDays(6)->setTime(8, 30), 'ends_at' => now()->subDays(6)->setTime(9, 50)]);
        $lecture1->syncGroups([$a->id, $b->id]);
        $lecture2 = $held(['group_id' => $a->id, 'is_lecture' => true, 'starts_at' => now()->subDays(5)->setTime(8, 30), 'ends_at' => now()->subDays(5)->setTime(9, 50)]);
        $lecture2->syncGroups([$a->id, $b->id]);
        $practice = $held(['group_id' => $a->id, 'starts_at' => now()->subDays(4)->setTime(10, 0), 'ends_at' => now()->subDays(4)->setTime(11, 20)]);
        $attend = fn (Student $student, Lesson $lesson) => LessonAttendance::create([
            'lesson_id' => $lesson->id, 'student_id' => $student->id, 'first_joined_at' => $lesson->starts_at, 'last_left_at' => $lesson->ends_at, 'seconds' => 4800, 'late_seconds' => 0, 'sessions' => 1,
        ]);
        $attend($ali, $lecture1);
        $attend($ali, $practice);
        $attend($vali, $lecture1);
        $attend($vali, $lecture2);
        $viewer = User::factory()->education()->create();

        $reportA = $this->actingAsUser($viewer)->getJson("/api/reports/semester?group_id={$a->id}")->assertOk()->json('data');
        $this->assertSame([3, 2, 1], [$reportA['totals']['held'], $reportA['totals']['held_lectures'], $reportA['totals']['held_practice']]);
        $this->assertSame(['attended' => 1, 'percent' => 50], $reportA['students'][0]['lectures']);
        $this->assertSame(['attended' => 1, 'percent' => 100], $reportA['students'][0]['practice']);
        $this->assertSame([true, true, false], array_column($reportA['lessons'], 'is_lecture'));

        // B guruhda faqat ma'ruzalar: shu guruh talabasi hisoblanadi, A guruh talabasi — yo'q.
        $reportB = $this->actingAsUser($viewer)->getJson("/api/reports/semester?group_id={$b->id}")->assertOk()->json('data');
        $this->assertSame([2, 2, 0], [$reportB['totals']['held'], $reportB['totals']['held_lectures'], $reportB['totals']['held_practice']]);
        $this->assertSame(['VALI KARIMOV'], array_column($reportB['students'], 'name'));
        $this->assertSame(['attended' => 2, 'percent' => 100], $reportB['students'][0]['lectures']);
        $this->assertSame(['attended' => 0, 'percent' => null], $reportB['students'][0]['practice']);

        $overview = collect($this->actingAsUser($viewer)->getJson('/api/reports/semester')->json('data.groups'))->keyBy('group.id');
        $this->assertSame(2, $overview[$b->id]['totals']['held_lectures']);

        // Excel: ma'ruza va amaliy alohida ustunlarda.
        $sheets = $this->xlsx($this->actingAsUser($viewer)->get("/api/reports/semester/export?group_id={$a->id}"));
        $rows = reset($sheets);
        $header = collect($rows)->first(fn (array $row) => ($row[1] ?? null) === 'Talaba');
        $this->assertContains('Maʼruza: qatnashgan', $header);
        $this->assertContains('Amaliy, %', $header);

        // Davr hisobotida ma'ruza har bir guruhda hisoblanadi, qatnashchilar — guruh bo'yicha.
        $summary = $this->actingAsUser($viewer)->getJson('/api/reports/summary?from='.now()->subDays(10)->toDateString().'&to='.now()->toDateString())->assertOk()->json('data');
        $byGroup = collect($summary['by_group'])->keyBy('group.id');
        $this->assertSame([3, 2], [$byGroup[$a->id]['lessons'], $byGroup[$a->id]['lectures']]);
        $this->assertSame([2, 2, 1], [$byGroup[$b->id]['lessons'], $byGroup[$b->id]['lectures'], $byGroup[$b->id]['avg_participants']]);
        $this->assertSame([3, 2], [$summary['totals']['lessons'], $summary['totals']['lectures']]);
    }

    public function test_weekly_lecture_creates_lecture_for_all_groups(): void
    {
        $this->fakeZoom();
        $this->travelTo(Carbon::parse('2026-11-23 13:50', 'Asia/Tashkent')); // dushanba
        $teacher = User::factory()->create();
        [$a, $b] = [Group::factory()->create(['name' => '101']), Group::factory()->create(['name' => '102'])];
        $slot = LessonSlot::factory()->create(['starts_at' => '14:30', 'duration_minutes' => 80]);

        $this->actingAsUser($teacher)->postJson('/api/series', ['is_lecture' => true, 'group_ids' => [$a->id, $b->id], 'lesson_slot_id' => $slot->id, 'weekday' => 1])
            ->assertCreated()
            ->assertJsonPath('data.is_lecture', true)
            ->assertJsonCount(2, 'data.groups');
        // Ikkinchi guruhda shu vaqtga haftalik dars qo'shib bo'lmaydi.
        $this->actingAsUser(User::factory()->create())->postJson('/api/series', ['group_id' => $b->id, 'lesson_slot_id' => $slot->id, 'weekday' => 1])->assertStatus(422)->assertJsonValidationErrors('group_id');
        $this->assertSame(1, LessonSeries::query()->forGroup($b->id)->count());

        app(MaterializeSeries::class)->run();

        $lesson = Lesson::firstOrFail();
        $this->assertTrue($lesson->is_lecture);
        $this->assertSame([$a->id, $b->id], $lesson->groupIds());
        $calendar = $this->actingAsUser($teacher)->getJson('/api/calendar?from=2026-11-23&to=2026-12-06&group_id='.$b->id)->assertOk()->json('data');
        $this->assertSame($lesson->id, $calendar['lessons'][0]['id']);
        $this->assertTrue($calendar['planned'][0]['is_lecture']);
        $this->assertCount(2, $calendar['planned'][0]['groups']);
    }

    /** @return array{0: Lesson, 1: Group, 2: Group} */
    private function finishedLecture(?array $groups = null, string $day = '2026-11-17'): array
    {
        $this->travelTo(Carbon::parse($day.' 16:30', 'Asia/Tashkent'));
        [$a, $b] = $groups ?? [Group::factory()->create(['name' => '101']), Group::factory()->create(['name' => '102'])];
        $lesson = Lesson::factory()->create([
            'group_id' => $a->id,
            'is_lecture' => true,
            'zoom_meeting_id' => '81234567890',
            'status' => LessonStatus::Finished,
            'zoom_state' => Lesson::ZOOM_ENDED,
            'starts_at' => Carbon::parse($day.' 14:30', 'Asia/Tashkent'),
            'ends_at' => Carbon::parse($day.' 15:50', 'Asia/Tashkent'),
        ]);
        $lesson->syncGroups([$a->id, $b->id]);

        return [$lesson->refresh(), $a, $b];
    }

    private function student(Group $group, string $name): Student
    {
        $student = Student::create(['group_id' => $group->id, 'name' => $name]);
        StudentAlias::create(['student_id' => $student->id, 'group_id' => $group->id, 'type' => StudentAlias::NAME, 'value' => StudentName::key($name)]);

        return $student;
    }

    /** @return array<string, string> */
    private function participant(string $name, string $from, string $to, string $id = ''): array
    {
        return ['id' => $id, 'name' => $name, 'user_email' => '', 'join_time' => '%DAY%T'.$from.':00Z', 'leave_time' => '%DAY%T'.$to.':00Z'];
    }

    /** @var array{day: string, participants: list<array<string, string>>}|null */
    private ?array $report = null;

    /** Zoom hisoboti (Http::fake bir marta ro'yxatga olinadi, javob — oxirgi berilgan hisobot). */
    private function fakeReport(array $participants, string $day = '2026-11-17'): void
    {
        $first = $this->report === null;
        $this->report = ['day' => $day, 'participants' => array_map(fn (array $row) => array_map(fn ($value) => str_replace('%DAY%', $day, $value), $row), $participants)];
        if (! $first) {
            return;
        }
        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'zoom-token', 'expires_in' => 3600]),
            'api.zoom.us/v2/past_meetings/*/instances' => fn () => Http::response(['meetings' => [['uuid' => 'abc==', 'start_time' => $this->report['day'].'T09:33:00Z']]]),
            'api.zoom.us/v2/past_meetings/abc%3D%3D' => fn () => Http::response(['start_time' => $this->report['day'].'T09:33:00Z', 'end_time' => $this->report['day'].'T10:53:00Z', 'host_id' => 'host1', 'participants_count' => 5]),
            'api.zoom.us/v2/report/meetings/*/participants*' => fn () => Http::response(['participants' => $this->report['participants'], 'next_page_token' => '']),
        ]);
    }
}
