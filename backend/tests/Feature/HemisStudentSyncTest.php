<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HemisStudentSyncTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    private User $education;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.hemis.api_url' => 'https://student.test/rest', 'services.hemis.api_token' => 'hemis-api-token']);
        $this->group = Group::factory()->create();
        $this->education = User::factory()->education()->create();
    }

    private function fakeHemis(): void
    {
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $id = $query['search'] ?? '';
            $students = [
                '322201100001' => ['id' => 501, 'student_id_number' => '322201100001', 'full_name' => 'QUDAYBERGENOV AYBEK BAXTIYAROVICH', 'first_name' => 'AYBEK', 'second_name' => 'QUDAYBERGENOV', 'third_name' => 'BAXTIYAROVICH', 'image' => 'https://student.test/img/501.jpg', 'phone' => '+998901234567', 'passport_pin' => '123'],
                // Qidiruv o'xshash, lekin boshqa ID'li talabani ham qaytaradi.
                '322201100002' => ['id' => 502, 'student_id_number' => '322201100099', 'full_name' => 'BOSHQA TALABA'],
            ];

            return Http::response(['success' => true, 'data' => ['items' => isset($students[$id]) ? [$students[$id]] : []]]);
        });
    }

    public function test_hemis_ids_are_entered_and_only_changed_ones_are_synced(): void
    {
        $this->fakeHemis();
        $aybek = Student::create(['group_id' => $this->group->id, 'name' => 'Aybek (iPhone)']);
        $other = Student::create(['group_id' => $this->group->id, 'name' => 'Boshqa']);

        $this->actingAsUser($this->education)->patchJson("/api/students/{$aybek->id}", ['hemis_id' => '322201100001'])
            ->assertOk()->assertJsonPath('data.hemis_status', 'pending');
        $this->actingAsUser($this->education)->patchJson("/api/students/{$other->id}", ['hemis_id' => '322201100001'])
            ->assertStatus(422)->assertJsonValidationErrors('hemis_id');
        $this->actingAsUser($this->education)->patchJson("/api/students/{$other->id}", ['hemis_id' => '322201100002'])->assertOk();

        $this->actingAsUser($this->education)->postJson("/api/groups/{$this->group->id}/students/hemis-sync")
            ->assertOk()
            ->assertExactJson(['data' => ['synced' => 1, 'not_found' => 1, 'failed' => 0]]);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer hemis-api-token'));

        $aybek->refresh();
        // Faqat F.I.Sh. va rasm saqlanadi (telefon va boshqalar — yo'q).
        $this->assertSame(['Qudaybergenov Aybek Baxtiyarovich', 'https://student.test/img/501.jpg', 'synced'], [$aybek->name, $aybek->picture_url, $aybek->hemisStatus()]);
        $this->assertSame('not_found', $other->refresh()->hemisStatus(), 'Boshqa ID li talaba qabul qilinmaydi');
        $this->assertSame('Boshqa', $other->name);

        // Qayta bosilsa — HEMIS'ga so'rov ketmaydi.
        Http::fake();
        $this->actingAsUser($this->education)->postJson("/api/groups/{$this->group->id}/students/hemis-sync")
            ->assertOk()->assertJsonPath('data.synced', 0);
        Http::assertNothingSent();

        $this->actingAsUser($this->education)->getJson("/api/groups/{$this->group->id}/students")
            ->assertOk()
            ->assertJsonPath('data.hemis.enabled', true)
            ->assertJsonPath('data.hemis.pending', 0)
            ->assertJsonFragment(['hemis_id' => '322201100001', 'hemis_status' => 'synced']);
    }

    public function test_missing_or_rejected_token_gives_clear_error(): void
    {
        Student::create(['group_id' => $this->group->id, 'name' => 'Ali', 'hemis_id' => '1']);

        config(['services.hemis.api_token' => null]);
        $this->actingAsUser($this->education)->postJson("/api/groups/{$this->group->id}/students/hemis-sync")
            ->assertStatus(422)->assertJsonValidationErrors('hemis');

        config(['services.hemis.api_token' => 'wrong']);
        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);
        $this->actingAsUser($this->education)->postJson("/api/groups/{$this->group->id}/students/hemis-sync")
            ->assertStatus(422)->assertJsonPath('errors.hemis.0', fn (string $message) => str_contains($message, 'HEMIS_API_TOKEN'));

        $this->actingAsUser(User::factory()->create())->postJson("/api/groups/{$this->group->id}/students/hemis-sync")->assertForbidden();
    }
}
