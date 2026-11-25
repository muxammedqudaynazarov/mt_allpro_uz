<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Lesson;
use App\Models\Recording;
use App\Models\Setting;
use App\Models\User;
use App\Models\ZoomAccount;
use App\Services\Zoom\RecordingSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecordingTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-secret';

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/mt-recordings-'.uniqid();
        config(['recordings.path' => $this->root, 'recordings.min_free_gb' => 0]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function completed(Lesson $lesson, int $videoSize): array
    {
        return ['event' => 'recording.completed', 'payload' => ['account_id' => 'acc', 'object' => [
            'id' => (int) $lesson->zoom_meeting_id,
            'uuid' => '/abc//uuid==',
            'recording_files' => [
                ['id' => 'f-video', 'file_type' => 'MP4', 'file_extension' => 'MP4', 'file_size' => $videoSize, 'recording_type' => 'shared_screen_with_speaker_view', 'status' => 'completed', 'download_url' => 'https://zoom.us/rec/download/video', 'recording_start' => '2026-11-17T09:30:00Z'],
                ['id' => 'f-audio', 'file_type' => 'M4A', 'file_extension' => 'M4A', 'file_size' => 100, 'recording_type' => 'audio_only', 'status' => 'completed', 'download_url' => 'https://zoom.us/rec/download/audio'],
                ['id' => 'f-chat', 'file_type' => 'CHAT', 'file_extension' => 'TXT', 'file_size' => 12, 'recording_type' => 'chat_file', 'status' => 'completed', 'download_url' => 'https://zoom.us/rec/download/chat'],
                ['id' => 'f-timeline', 'file_type' => 'TIMELINE', 'file_extension' => 'JSON', 'file_size' => 5, 'status' => 'completed', 'download_url' => 'https://zoom.us/rec/download/timeline'],
            ],
        ]], 'download_token' => 'webhook-download-token'];
    }

    private function webhook(ZoomAccount $account, array $data): void
    {
        $body = json_encode($data);
        $ts = (string) time();
        $this->call('POST', "/api/zoom/webhook/{$account->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ZM_REQUEST_TIMESTAMP' => $ts, 'HTTP_X_ZM_SIGNATURE' => 'v0='.hash_hmac('sha256', "v0:{$ts}:{$body}", self::SECRET),
        ], $body)->assertNoContent();
    }

    public function test_meetings_are_recorded_to_cloud_when_enabled(): void
    {
        $this->fakeZoom();
        Setting::put('recording', ['enabled' => true, 'delete_from_zoom' => true, 'delete_action' => 'trash', 'include_audio' => false]);

        $this->actingAsUser(User::factory()->create())->postJson('/api/lessons', ['group_id' => Group::factory()->create()->id, 'slot_id' => $this->slotSoon()->id])->assertCreated();

        $this->assertTrue(Lesson::sole()->record_cloud);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/meetings') && $request['settings']['auto_recording'] === 'cloud');
    }

    public function test_completed_recording_is_downloaded_verified_and_removed_from_zoom(): void
    {
        $account = ZoomAccount::factory()->create(['webhook_secret' => self::SECRET]);
        $teacher = User::factory()->create();
        $lesson = Lesson::factory()->finished()->create(['zoom_account_id' => $account->id, 'user_id' => $teacher->id, 'record_cloud' => true]);
        $video = str_repeat('v', 2048);
        $retry = false;
        Http::fake(function (Request $request) use ($video, &$retry) {
            $url = $request->url();

            return match (true) {
                str_contains($url, 'oauth/token') => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
                str_ends_with($url, '/rec/download/video') => Http::response($video),
                str_ends_with($url, '/rec/download/chat'), str_ends_with($url, '/rec/download/chat2') => Http::response('Ali: salom!'),
                $request->method() === 'DELETE' => Http::response(null, 204),
                // Qayta urinishda Zoom yangi havola va to'g'ri hajm beradi.
                $retry && str_contains($url, '/recordings') => Http::response(['uuid' => '/abc//uuid==', 'id' => 1, 'recording_files' => [
                    ['id' => 'f-chat', 'file_type' => 'CHAT', 'file_size' => 11, 'status' => 'completed', 'download_url' => 'https://zoom.us/rec/download/chat2'],
                ]]),
                default => Http::response(['message' => 'unexpected'], 500),
            };
        });

        $this->webhook($account, $this->completed($lesson, strlen($video)));
        // Zoom bergan yuklash tokeni saqlanadi (birinchi usul).
        $this->assertSame('webhook-download-token', Recording::query()->where('zoom_file_id', 'f-video')->firstOrFail()->download_token);

        // Video va chat yuklanadi; alohida audio (sozlamada o'chiq) va timeline — yo'q.
        $this->assertSame(
            ['f-audio' => 'skipped', 'f-chat' => 'pending', 'f-timeline' => 'skipped', 'f-video' => 'pending'],
            Recording::query()->orderBy('zoom_file_id')->pluck('status', 'zoom_file_id')->all(),
        );

        $sync = app(RecordingSync::class);
        $this->assertSame(['downloaded' => 1, 'failed' => 1], $sync->download(5));
        // Chat fayli hajmi mos kelmadi (11 ≠ 12) — saqlanmaydi, Zoom'dan ham o'chirilmaydi.
        $chat = Recording::where('zoom_file_id', 'f-chat')->first();
        $this->assertSame([Recording::FAILED, 1], [$chat->status, $chat->attempts]);
        $this->assertSame(0, $sync->purge());
        Http::assertNotSent(fn (Request $request) => $request->method() === 'DELETE');

        $retry = true;
        $this->assertSame(['downloaded' => 1, 'failed' => 0], $sync->download(5));

        $videoFile = Recording::where('zoom_file_id', 'f-video')->first();
        $this->assertSame(Recording::DOWNLOADED, $videoFile->status);
        $this->assertSame($video, file_get_contents($videoFile->absolutePath()));
        $this->assertNull($videoFile->getRawOriginal('download_url'));

        // Hammasi serverda — Zoom'dan o'chiriladi (savatga). "/" bilan boshlangan UUID ikki marta kodlanadi.
        $this->assertSame(1, $sync->purge());
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/meetings/'.rawurlencode(rawurlencode('/abc//uuid==')).'/recordings?action=trash'));
        $this->assertNotNull($videoFile->fresh()->zoom_deleted_at);

        // Dars o'qituvchisi ko'radi va ijro etadi (Range), begona o'qituvchi — yo'q.
        $this->actingAsUser($teacher)->getJson("/api/lessons/{$lesson->id}/recordings")
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.status', 'downloaded');
        $this->actingAsUser($teacher)->get("/api/recordings/{$videoFile->id}/file", ['Range' => 'bytes=0-99'])
            ->assertStatus(206)->assertHeader('Content-Type', 'video/mp4');
        $this->actingAsUser(User::factory()->create())->get("/api/recordings/{$videoFile->id}/file")->assertForbidden();
        // Umumiy ro'yxatda faqat video (chat dars sahifasida qoladi).
        $this->actingAsUser($teacher)->getJson('/api/recordings')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.file_type', 'MP4');

        // nginx rejimi: PHP faqat ruxsatni tekshiradi, faylni nginx beradi.
        config(['recordings.accel_prefix' => '/_zoom_recordings/']);
        $accel = $this->actingAsUser($teacher)->get("/api/recordings/{$videoFile->id}/file?download=1")->assertOk();
        $this->assertSame('/_zoom_recordings/'.implode('/', array_map('rawurlencode', explode('/', $videoFile->path))), $accel->headers->get('X-Accel-Redirect'));
        $this->assertStringStartsWith('attachment;', (string) $accel->headers->get('Content-Disposition'));
        $this->assertSame('', $accel->getContent());
        $this->actingAsUser(User::factory()->create())->getJson('/api/recordings')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_admin_controls_recording_settings_and_sees_storage(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAsUser($admin)->putJson('/api/settings', ['recording' => ['enabled' => true, 'delete_from_zoom' => true, 'delete_action' => 'delete', 'include_audio' => true]])
            ->assertOk()
            ->assertJsonPath('data.recording.enabled', true)
            ->assertJsonPath('data.recording.delete_action', 'delete')
            ->assertJsonPath('data.storage.path', $this->root);

        $this->actingAsUser($admin)->putJson('/api/settings', ['recording' => ['enabled' => true, 'delete_from_zoom' => true, 'delete_action' => 'shred', 'include_audio' => false]])
            ->assertStatus(422);
        $this->actingAsUser(User::factory()->education()->create())->getJson('/api/recordings')->assertOk()->assertJsonPath('meta.storage.files', 0);
        // O'qituvchi faqat o'z darslarining yozuvlarini ko'radi, xotira holatini emas.
        $this->actingAsUser(User::factory()->create())->getJson('/api/recordings')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.storage', null);
    }

    public function test_recordings_are_polled_when_webhook_is_missing(): void
    {
        $account = ZoomAccount::factory()->create();
        $lesson = Lesson::factory()->finished()->create(['zoom_account_id' => $account->id, 'record_cloud' => true, 'zoom_state' => Lesson::ZOOM_ENDED, 'zoom_ended_at' => now()->subHour()]);
        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'api.zoom.us/v2/past_meetings/*/instances' => Http::response(['meetings' => [['uuid' => 'inst-1==', 'start_time' => '2026-11-17T09:30:00Z']]]),
            'api.zoom.us/v2/meetings/*/recordings' => Http::response(['uuid' => 'inst-1==', 'id' => (int) $lesson->zoom_meeting_id, 'recording_files' => [
                ['id' => 'p-video', 'file_type' => 'MP4', 'file_size' => 10, 'status' => 'completed', 'download_url' => 'https://zoom.us/rec/download/x'],
            ]]),
        ]);

        $this->assertSame(1, app(RecordingSync::class)->poll());
        $this->assertSame($lesson->id, Recording::sole()->lesson_id);
        // 30 daqiqa ichida qayta so'ralmaydi.
        $this->assertSame(0, app(RecordingSync::class)->poll());
    }

    public function test_meetings_outside_lessons_are_not_downloaded(): void
    {
        $account = ZoomAccount::factory()->create();
        // Shu akkaunt bilan o'tkazilgan PhD seminar: tizimda bunday dars yo'q.
        $created = app(RecordingSync::class)->register($account, ['id' => 99911122233, 'uuid' => 'seminar==', 'recording_files' => [
            ['id' => 's-video', 'file_type' => 'MP4', 'file_size' => 10, 'status' => 'completed', 'download_url' => 'https://zoom.us/rec/download/s'],
        ]]);

        $this->assertSame(0, $created);
        $this->assertSame(0, Recording::count());
    }

    public function test_admin_deletes_recordings_from_server(): void
    {
        Setting::put('recording', ['enabled' => true, 'delete_from_zoom' => true, 'delete_action' => 'trash', 'include_audio' => false]);
        $account = ZoomAccount::factory()->create();
        $lesson = Lesson::factory()->finished()->create(['zoom_account_id' => $account->id]);
        $file = function (string $id, string $type, ?Lesson $lesson, string $uuid) use ($account) {
            $path = '2026/11/'.$uuid.'/'.$id.'.bin';
            File::ensureDirectoryExists($this->root.'/2026/11/'.$uuid);
            file_put_contents($this->root.'/'.$path, str_repeat('x', 1024));

            return Recording::create([
                'lesson_id' => $lesson?->id, 'zoom_account_id' => $account->id, 'zoom_meeting_id' => '123', 'zoom_meeting_uuid' => $uuid, 'zoom_file_id' => $id,
                'file_type' => $type, 'size' => 1024, 'status' => Recording::DOWNLOADED, 'path' => $path,
            ]);
        };
        // Avvalroq yuklab olingan begona uchrashuv (seminar): video va chat.
        $seminar = $file('s-video', 'MP4', null, 'seminar');
        $seminarChat = $file('s-chat', 'CHAT', null, 'seminar');
        $lessonVideo = $file('l-video', 'MP4', $lesson, 'lesson');
        $lessonSpeaker = $file('l-speaker', 'MP4', $lesson, 'lesson');
        $lessonChat = $file('l-chat', 'CHAT', $lesson, 'lesson');
        $admin = User::factory()->superAdmin()->create();
        $education = User::factory()->education()->create();

        // Begona uchrashuv yozuvlarini faqat Administrator ko'radi; Zoom'dan o'chirilmaydi.
        $this->actingAsUser($admin)->getJson('/api/recordings?status=unlinked')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.unlinked', 1)->assertJsonPath('data.0.zoom_meeting_id', '123');
        $this->actingAsUser($education)->getJson('/api/recordings')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.unlinked', null);
        Http::fake(['zoom.us/oauth/token*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]), 'api.zoom.us/*' => Http::response(null, 204)]);
        app(RecordingSync::class)->purge();
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'seminar'));
        Http::fake();

        $this->actingAsUser($education)->deleteJson("/api/recordings/{$seminar->id}")->assertForbidden();
        $this->actingAsUser(User::factory()->create())->deleteJson("/api/recordings/{$seminar->id}")->assertForbidden();

        // Uchrashuvda boshqa video qolmadi — chat ham o'chadi.
        $this->actingAsUser($admin)->deleteJson("/api/recordings/{$seminar->id}")->assertOk()->assertJsonPath('data.deleted', 2);
        $this->assertFileDoesNotExist($this->root.'/2026/11/seminar/s-video.bin');
        $this->assertFileDoesNotExist($this->root.'/2026/11/seminar/s-chat.bin');
        $this->assertSame([Recording::DELETED, Recording::DELETED], [$seminar->fresh()->status, $seminarChat->fresh()->status]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'recording.deleted']);
        $this->actingAsUser($admin)->getJson('/api/recordings?status=unlinked')->assertJsonCount(0, 'data');
        $this->actingAsUser($admin)->deleteJson("/api/recordings/{$seminar->id}")->assertNotFound();

        // Dars yozuvlari o'chirilmaydi.
        $this->actingAsUser($admin)->deleteJson("/api/recordings/{$lessonVideo->id}")->assertStatus(422);
        $this->assertFileExists($this->root.'/2026/11/lesson/l-video.bin');
        $this->assertSame([Recording::DOWNLOADED, Recording::DOWNLOADED, Recording::DOWNLOADED], [$lessonVideo->fresh()->status, $lessonSpeaker->fresh()->status, $lessonChat->fresh()->status]);

        // Zoom webhook'i qayta kelsa ham o'chirilgan fayl tiklanmaydi.
        $seminar->forceFill(['lesson_id' => $lesson->id])->save();
        app(RecordingSync::class)->register($account, ['id' => 123, 'uuid' => 'seminar', 'recording_files' => [
            ['id' => 's-video', 'file_type' => 'MP4', 'file_size' => 1024, 'status' => 'completed', 'download_url' => 'https://zoom.us/rec/download/s'],
        ]]);
        $this->assertSame(Recording::DELETED, $seminar->fresh()->status);
    }
}
