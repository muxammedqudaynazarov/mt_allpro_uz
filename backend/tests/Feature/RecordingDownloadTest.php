<?php

namespace Tests\Feature;

use App\Exceptions\ExternalServiceException;
use App\Models\Lesson;
use App\Models\Recording;
use App\Models\ZoomAccount;
use App\Services\Zoom\RecordingSync;
use App\Services\Zoom\ZoomClient;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Zoom ruxsat bermasa 401 emas, 200 kodli HTML sahifa qaytaradi — bu fayl deb saqlanmasligi kerak. */
class RecordingDownloadTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://us06web.zoom.us/rec/download/abc?type=mp4';

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = tempnam(sys_get_temp_dir(), 'rec');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    /** @param  callable(Request): PromiseInterface  $download */
    private function fake(callable $download): void
    {
        Http::fake(fn (Request $request) => str_contains($request->url(), 'zoom.us/oauth/token')
            ? Http::response(['access_token' => 'zoom-token', 'expires_in' => 3600])
            : $download($request));
    }

    private function download(): int
    {
        return app(ZoomClient::class)->downloadRecording(ZoomAccount::factory()->create(), self::URL, $this->path);
    }

    public function test_falls_back_to_access_token_parameter_when_header_gets_login_page(): void
    {
        $this->fake(fn (Request $request) => str_contains($request->url(), 'access_token=zoom-token')
            ? Http::response(str_repeat('v', 2048), 200, ['Content-Type' => 'video/mp4'])
            : Http::response('<html>Sign in</html>', 200, ['Content-Type' => 'text/html; charset=utf-8']));

        $this->assertSame(2048, $this->download());
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer zoom-token') && ! str_contains($request->url(), 'access_token'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '?type=mp4&access_token=zoom-token'));
    }

    /** Serverdagi holat: sarlavha bilan 401 (yangi token bilan ham), ?access_token= bilan esa fayl keladi. */
    public function test_header_401_falls_back_to_access_token_parameter(): void
    {
        $this->fake(fn (Request $request) => str_contains($request->url(), 'access_token=zoom-token')
            ? Http::response(str_repeat('v', 1024), 200, ['Content-Type' => 'video/mp4'])
            : Http::response(['code' => 124, 'message' => 'Invalid access token.'], 401));

        $this->assertSame(1024, $this->download());
    }

    /** Webhook bergan download_token birinchi ishlatiladi — ilova tokeni (va uning ruxsatlari) kerak emas. */
    public function test_webhook_download_token_is_used_first(): void
    {
        $this->fake(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer webhook-token')
            ? Http::response(str_repeat('v', 512), 200, ['Content-Type' => 'video/mp4'])
            : Http::response('', 401));

        $bytes = app(ZoomClient::class)->downloadRecording(ZoomAccount::factory()->create(), self::URL, $this->path, 'webhook-token');

        $this->assertSame(512, $bytes);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'oauth/token'));
    }

    public function test_download_token_from_webhook_is_stored_encrypted_and_hidden(): void
    {
        $account = ZoomAccount::factory()->create();
        // Faqat tizim darslarining yozuvlari ro'yxatga olinadi.
        Lesson::factory()->finished()->create(['zoom_account_id' => $account->id, 'zoom_meeting_id' => '81234567890']);
        app(RecordingSync::class)->register($account, [
            'id' => 81234567890,
            'uuid' => 'abc==',
            'recording_files' => [['id' => 'f1', 'file_type' => 'MP4', 'file_size' => 100, 'download_url' => self::URL, 'status' => 'completed', 'recording_type' => 'shared_screen_with_speaker_view']],
        ], 'webhook-token');

        $recording = Recording::query()->firstOrFail();
        $this->assertSame('webhook-token', $recording->download_token);
        $this->assertTrue($recording->download_token_expires_at->isFuture());
        $this->assertNotSame('webhook-token', DB::table('recordings')->value('download_token'));
        $this->assertArrayNotHasKey('download_token', $recording->toArray());
    }

    public function test_login_page_is_reported_as_missing_permission(): void
    {
        $this->fake(fn () => Http::response('<html>Sign in</html>', 200, ['Content-Type' => 'text/html']));

        try {
            $this->download();
            $this->fail('Xato kutilgan edi');
        } catch (ExternalServiceException $e) {
            $this->assertStringContainsString('ruxsat yoʻq', $e->getMessage());
            $this->assertStringContainsString('cloud_recording:read:recording:admin', $e->getMessage());
        }
        $this->assertFileDoesNotExist($this->path);
    }

    public function test_zoom_json_error_text_is_kept(): void
    {
        $this->fake(fn () => Http::response(['code' => 124, 'message' => 'Invalid access token.'], 401, ['Content-Type' => 'application/json']));

        try {
            $this->download();
            $this->fail('Xato kutilgan edi');
        } catch (ExternalServiceException $e) {
            $this->assertStringContainsString('HTTP 401', $e->getMessage());
            $this->assertStringContainsString('Zoom: Invalid access token.', $e->getMessage());
        }
    }
}
