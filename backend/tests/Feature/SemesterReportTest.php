<?php

namespace Tests\Feature;

use App\Enums\LessonStatus;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemesterReportTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->semester = Semester::factory()->create();
        $this->group = Group::factory()->create(['name' => '3-B1 Iqtisodiyot']);

        // 3 ta o'tkazilgan (har biri 80 daqiqa) va 1 ta o'tkazilmagan dars.
        $lessons = collect(range(1, 3))->map(fn (int $day) => Lesson::factory()->for($this->group)->create([
            'status' => LessonStatus::Finished,
            'starts_at' => now()->subDays(10 - $day)->setTime(14, 30),
            'ends_at' => now()->subDays(10 - $day)->setTime(15, 50),
            'zoom_state' => Lesson::ZOOM_ENDED,
            'zoom_minutes' => 80,
            'attendance_synced_at' => now(),
        ]));
        Lesson::factory()->for($this->group)->create(['status' => LessonStatus::Finished, 'zoom_state' => Lesson::ZOOM_MISSED, 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addMinutes(80)]);
        // Semestrdan tashqaridagi dars hisobga olinmaydi.
        Lesson::factory()->for($this->group)->create(['status' => LessonStatus::Finished, 'zoom_state' => Lesson::ZOOM_ENDED, 'zoom_minutes' => 80, 'attendance_synced_at' => now(), 'starts_at' => now()->subYear(), 'ends_at' => now()->subYear()->addMinutes(80)]);

        $student = fn (string $name, bool $ignored = false) => Student::create(['group_id' => $this->group->id, 'name' => $name, 'is_ignored' => $ignored]);
        $attend = fn (Student $s, Lesson $l, int $minutes, int $late = 0) => LessonAttendance::create([
            'lesson_id' => $l->id, 'student_id' => $s->id, 'first_joined_at' => $l->starts_at, 'last_left_at' => $l->ends_at,
            'seconds' => $minutes * 60, 'late_seconds' => $late * 60, 'sessions' => 1,
        ]);

        $ali = $student('Ali Valiyev');
        $vali = $student('Vali Karimov');
        $guli = $student('Guli Saidova');
        $host = $student('iPhone', true);
        foreach ($lessons as $lesson) {
            $attend($ali, $lesson, 80, 2);
            $attend($host, $lesson, 80);
        }
        $attend($vali, $lessons[0], 40, 10);
        $attend($vali, $lessons[1], 40, 10);
        $attend($guli, $lessons[2], 20, 30);
    }

    private function report(?User $user = null): array
    {
        return $this->actingAsUser($user ?? User::factory()->education()->create())
            ->getJson("/api/reports/semester?group_id={$this->group->id}")
            ->assertOk()
            ->json('data');
    }

    public function test_group_report_totals_percentages_and_levels(): void
    {
        $data = $this->report();
        $rows = collect($data['students'])->keyBy('name');

        $this->assertSame(['held' => 3, 'missed' => 1, 'pending' => 0, 'zoom_minutes' => 240, 'avg_lesson_minutes' => 80, 'students' => 3, 'avg_students_per_lesson' => 1.7, 'avg_attendance_percent' => 56], $data['totals']);
        $this->assertSame([240, 100, 100, 'alo', 3], [$rows['ALI VALIYEV']['minutes'], $rows['ALI VALIYEV']['percent_time'], $rows['ALI VALIYEV']['percent_lessons'], $rows['ALI VALIYEV']['level'], $rows['ALI VALIYEV']['attended']]);
        // 80 / 240 = 33% → Sust (≥30). Darsning yarmida bo'lgan — qatnashgan hisoblanadi.
        $this->assertSame([80, 33, 67, 'sust', 2, 10], [$rows['VALI KARIMOV']['minutes'], $rows['VALI KARIMOV']['percent_time'], $rows['VALI KARIMOV']['percent_lessons'], $rows['VALI KARIMOV']['level'], $rows['VALI KARIMOV']['attended'], $rows['VALI KARIMOV']['avg_late_minutes']]);
        // 20 daqiqa (25%) — kirgan, lekin qatnashgan hisoblanmaydi.
        $this->assertSame([0, 1, 8, 'past', [0, 0, 20]], [$rows['GULI SAIDOVA']['attended'], $rows['GULI SAIDOVA']['joined'], $rows['GULI SAIDOVA']['percent_time'], $rows['GULI SAIDOVA']['level'], $rows['GULI SAIDOVA']['per_lesson']]);
        $this->assertArrayNotHasKey('iPhone', $rows->all());
        $this->assertSame(['past' => 1, 'sust' => 1, 'qoniqarli' => 0, 'ortacha' => 0, 'yaxshi' => 0, 'alo' => 1], $data['levels']);
        $this->assertSame([2, 2, 1], array_column($data['lessons'], 'present'));
    }

    public function test_admin_changes_criteria_and_levels_follow(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $payload = ['assessment' => ['basis' => 'lessons', 'presence_percent' => 50, 'thresholds' => ['sust' => 20, 'qoniqarli' => 40, 'ortacha' => 60, 'yaxshi' => 75, 'alo' => 90]]];

        $this->actingAsUser($admin)->putJson('/api/settings', $payload)->assertOk()->assertJsonPath('data.assessment.basis', 'lessons');
        // Darslar foizi: Vali 67% → O'rtacha (≥60).
        $this->assertSame('ortacha', collect($this->report()['students'])->firstWhere('name', 'VALI KARIMOV')['level']);

        // Chegaralar o'sib borishi shart; O'quv bo'limi sozlamani o'zgartira olmaydi.
        $bad = $payload;
        $bad['assessment']['thresholds']['yaxshi'] = 50;
        $this->actingAsUser($admin)->putJson('/api/settings', $bad)->assertStatus(422)->assertJsonValidationErrors('assessment.thresholds.yaxshi');
        $this->actingAsUser(User::factory()->education()->create())->putJson('/api/settings', $payload)->assertForbidden();
    }

    public function test_overview_lists_groups_and_teacher_has_no_access(): void
    {
        $this->actingAsUser(User::factory()->education()->create())->getJson('/api/reports/semester')
            ->assertOk()
            ->assertJsonPath('data.semester.id', $this->semester->id)
            ->assertJsonPath('data.groups.0.group.name', '3-B1 Iqtisodiyot')
            ->assertJsonPath('data.groups.0.totals.held', 3);

        $this->actingAsUser(User::factory()->create())->getJson('/api/reports/semester')->assertForbidden();
    }

    public function test_xlsx_exports_for_group_and_whole_semester(): void
    {
        $user = User::factory()->education()->create();

        $sheets = $this->xlsx($this->actingAsUser($user)->get("/api/reports/semester/export?group_id={$this->group->id}"));
        $this->assertSame(['Yakuniy', 'Darslar boʻyicha'], array_keys($sheets));
        $summary = collect($sheets['Yakuniy']);
        $ali = $summary->first(fn (array $row) => ($row[1] ?? null) === 'ALI VALIYEV');
        $this->assertSame([1, 'ALI VALIYEV', '3 / 3', 4, 100, 100], array_slice($ali, 0, 6));
        $this->assertSame('Aʼlo', end($ali));
        $matrix = collect($sheets['Darslar boʻyicha'])->first(fn (array $row) => $row[0] === 'GULI SAIDOVA');
        $this->assertSame(['GULI SAIDOVA', 0, 0, 20], $matrix);

        $all = $this->xlsx($this->actingAsUser($user)->get('/api/reports/semester/export'));
        $this->assertSame(['Guruhlar', '3-B1 Iqtisodiyot'], array_keys($all));
    }

    public function test_lessons_are_bound_to_semester_and_filtered_as_archive(): void
    {
        $lesson = Lesson::factory()->for($this->group)->create();
        $this->assertSame($this->semester->id, $lesson->semester_id);

        $old = Lesson::factory()->for($this->group)->finished()->create(['starts_at' => now()->subYear(), 'ends_at' => now()->subYear()->addMinutes(80)]);
        $this->assertNull($old->semester_id);

        $ids = collect($this->actingAsUser(User::factory()->education()->create())
            ->getJson("/api/lessons?semester_id={$this->semester->id}&per_page=100")->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($lesson->id));
        $this->assertFalse($ids->contains($old->id));
    }
}
