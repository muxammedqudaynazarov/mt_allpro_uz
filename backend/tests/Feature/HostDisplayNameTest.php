<?php

namespace Tests\Feature;

use App\Actions\SyncZoomHosts;
use App\Models\Lesson;
use App\Models\Setting;
use App\Models\User;
use App\Models\ZoomHost;
use App\Support\PersonName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Darsni boshlashda Zoom host ismi o'qituvchi nomiga o'zgaradi: "QUDAYNAZAROV M.S.". */
class HostDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_name_uses_surname_and_initials(): void
    {
        $this->assertSame('QUDAYNAZAROV M.S.', PersonName::short('Qudaynazarov', 'Muxammed', 'Salamatovich'));
        $this->assertSame('ALIYEV Sh.Ch.', PersonName::short('Aliyev', 'Shavkat', 'Chori oʻgʻli'));
        $this->assertSame('KARIMOVA O‘.G‘.', PersonName::short('Karimova', "O'g'iloy", 'G‘ayratovna'));
        $this->assertSame('TURSÍNBAEVA Í.', PersonName::short('Tursınbaeva', 'ılyas'));
        $this->assertSame('QUDAYNAZAROV M.S.', User::factory()->make(['first_name' => null, 'last_name' => null, 'name' => 'QUDAYNAZAROV MUXAMMED SALAMATOVICH'])->zoomName());
    }

    public function test_host_is_renamed_to_teacher_when_lesson_starts(): void
    {
        $this->fakeZoom();
        [$lesson, $host] = $this->lesson();

        $this->actingAsUser($lesson->teacher)->postJson("/api/lessons/{$lesson->id}/start")->assertOk();

        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/users/'.$host->zoom_user_id)
            && $request['display_name'] === 'QUDAYNAZAROV M.S.');
        $this->assertSame('QUDAYNAZAROV M.S.', $host->refresh()->renamed_to);

        // Ism allaqachon qo'yilgan — qayta so'ralmaydi (Telegram'dan boshlaganda ham).
        $this->get(URL::temporarySignedRoute('lessons.launch', $lesson->ends_at, ['lesson' => $lesson->id]))->assertRedirect();
        Http::assertSentCount(1 + 1 + 1 + 1); // token, PATCH, start URL (sayt), start URL (Telegram)

        // Hostlar ro'yxati Zoom'dan yangilanganda tizimdagi nom o'zgarmaydi.
        Http::fake(['api.zoom.us/v2/users*' => Http::response(['users' => [['id' => $host->zoom_user_id, 'email' => $host->email, 'display_name' => 'QUDAYNAZAROV M.S.', 'type' => 2]]])]);
        app(SyncZoomHosts::class)->handle($host->zoomAccount);
        $this->assertSame('KarSU Zoom 1', $host->refresh()->name);
    }

    public function test_failure_does_not_block_start_and_is_shown_in_settings(): void
    {
        $this->fakeZoom(['api.zoom.us/v2/users/*' => Http::response(['code' => 4711, 'message' => 'Invalid access token, does not contain scopes:[user:update:user:admin]'], 400)]);
        [$lesson, $host] = $this->lesson();

        $this->actingAsUser($lesson->teacher)->postJson("/api/lessons/{$lesson->id}/start")
            ->assertOk()
            ->assertJsonPath('data.url', 'https://us06web.zoom.us/s/81234567890?zak=fresh');
        $this->assertNull($host->refresh()->renamed_to);

        $admin = User::factory()->superAdmin()->create();
        $error = $this->actingAsUser($admin)->getJson('/api/settings')->json('data.zoom.rename_error');
        $this->assertStringContainsString('user:update:user:admin', $error);
    }

    public function test_admin_can_turn_renaming_off(): void
    {
        $this->fakeZoom();
        [$lesson] = $this->lesson();
        $admin = User::factory()->superAdmin()->create();
        Setting::put('zoom', ['auto_end_percent' => 50]);

        $this->actingAsUser($admin)->putJson('/api/settings', ['zoom' => ['rename_host' => false]])
            ->assertOk()
            ->assertJsonPath('data.zoom.rename_host', false)
            ->assertJsonPath('data.zoom.auto_end_percent', 50);

        $this->actingAsUser($lesson->teacher)->postJson("/api/lessons/{$lesson->id}/start")->assertOk();
        Http::assertNotSent(fn (Request $request) => $request->method() === 'PATCH');
    }

    /** @return array{0: Lesson, 1: ZoomHost} */
    private function lesson(): array
    {
        $teacher = User::factory()->create(['last_name' => 'Qudaynazarov', 'first_name' => 'Muxammed', 'middle_name' => 'Salamatovich']);
        $lesson = Lesson::factory()->create(['user_id' => $teacher->id]);
        $host = ZoomHost::query()->firstOrNew(['zoom_account_id' => $lesson->zoom_account_id, 'email' => $lesson->zoom_host_email]);
        $host->forceFill(['zoom_user_id' => 'zu-1', 'name' => 'KarSU Zoom 1', 'type' => ZoomHost::LICENSED, 'is_active' => true])->save();

        return [$lesson, $host];
    }
}
