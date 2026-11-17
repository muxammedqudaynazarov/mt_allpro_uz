<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $name, int $year): array
    {
        return [
            'name' => $name,
            'status' => 'planned',
            'autumn' => ['starts_on' => "{$year}-09-01", 'ends_on' => ($year + 1).'-01-20'],
            'spring' => ['starts_on' => ($year + 1).'-02-01', 'ends_on' => ($year + 1).'-06-30'],
        ];
    }

    public function test_admin_creates_year_with_two_semesters_and_existing_lessons_are_archived(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $lesson = Lesson::factory()->for(Group::factory())->finished()->create(['starts_at' => '2025-10-10 14:30', 'ends_at' => '2025-10-10 15:50']);

        $response = $this->actingAsUser($admin)->postJson('/api/academic-years', $this->payload('2025 - 2026', 2025))
            ->assertCreated()
            ->assertJsonPath('data.name', '2025–2026')
            ->assertJsonPath('data.semesters.0.type', 'autumn')
            ->assertJsonPath('data.semesters.0.lessons_count', 1)
            ->assertJsonPath('data.semesters.1.type', 'spring');

        $autumn = Semester::where('type', 'autumn')->first();
        $this->assertSame($autumn->id, $lesson->fresh()->semester_id);
        $this->assertSame('2025–2026, Kuzgi semestr', $autumn->fullLabel());

        // Kesishgan sanalar va takroriy nom rad etiladi.
        $this->actingAsUser($admin)->postJson('/api/academic-years', $this->payload('2025–2026', 2025))->assertStatus(422)->assertJsonValidationErrors('name');
        $overlap = $this->payload('2026–2027', 2026);
        $overlap['autumn']['starts_on'] = '2026-06-01';
        $this->actingAsUser($admin)->postJson('/api/academic-years', $overlap)->assertStatus(422)->assertJsonValidationErrors('autumn.starts_on');

        // Darsi bor yilni o'chirib bo'lmaydi — arxivlanadi.
        $id = $response->json('data.id');
        $this->actingAsUser($admin)->deleteJson("/api/academic-years/{$id}")->assertStatus(422);
        $this->actingAsUser($admin)->patchJson("/api/academic-years/{$id}/status", ['status' => 'archived'])->assertOk()->assertJsonPath('data.status', 'archived');
    }

    public function test_only_one_year_is_active_and_education_can_only_view(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $first = $this->actingAsUser($admin)->postJson('/api/academic-years', $this->payload('2025–2026', 2025))->json('data.id');
        $second = $this->actingAsUser($admin)->postJson('/api/academic-years', $this->payload('2026–2027', 2026))->json('data.id');

        $this->actingAsUser($admin)->patchJson("/api/academic-years/{$first}/status", ['status' => 'active'])->assertOk();
        $this->actingAsUser($admin)->patchJson("/api/academic-years/{$second}/status", ['status' => 'active'])->assertOk();
        $this->assertSame(['archived', 'active'], [AcademicYear::find($first)->status, AcademicYear::find($second)->status]);

        $education = User::factory()->education()->create();
        $this->actingAsUser($education)->getJson('/api/academic-years')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAsUser($education)->postJson('/api/academic-years', $this->payload('2027–2028', 2027))->assertForbidden();
        $this->actingAsUser($education)->patchJson("/api/academic-years/{$first}/status", ['status' => 'active'])->assertForbidden();
        $this->actingAsUser(User::factory()->create())->getJson('/api/academic-years')->assertForbidden();

        // Bo'sh yilni o'chirish mumkin.
        $this->actingAsUser($admin)->deleteJson("/api/academic-years/{$first}")->assertNoContent();
    }
}
