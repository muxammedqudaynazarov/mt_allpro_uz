<?php

namespace Tests\Feature;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Models\User;
use App\Models\ZoomAccount;
use App\Models\ZoomHost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ZoomWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-secret-token';

    private function webhook(ZoomAccount $account, array $data, ?int $timestamp = null, ?string $secret = null): TestResponse
    {
        $body = json_encode($data);
        $timestamp ??= time();
        $signature = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", $secret ?? self::SECRET);

        return $this->call('POST', "/api/zoom/webhook/{$account->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ZM_REQUEST_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_ZM_SIGNATURE' => $signature,
        ], $body);
    }

    private function participant(Lesson $lesson, string $event, string $uuid, string $name, array $extra = []): array
    {
        return ['event' => $event, 'payload' => ['account_id' => 'acc', 'object' => [
            'id' => $lesson->zoom_meeting_id,
            'host_id' => 'host-zoom-id',
            'participant' => ['participant_uuid' => $uuid, 'user_id' => '1677', 'user_name' => $name] + $extra,
        ]]];
    }

    public function test_signature_is_required_for_every_request(): void
    {
        $account = ZoomAccount::factory()->create(['webhook_secret' => self::SECRET]);
        $validation = ['event' => 'endpoint.url_validation', 'payload' => ['plainToken' => 'abc']];

        $this->webhook($account, $validation, secret: 'wrong')->assertStatus(401);
        $this->webhook($account, $validation, timestamp: time() - 600)->assertStatus(401);
        $this->webhook($account, $validation)
            ->assertOk()
            ->assertExactJson(['plainToken' => 'abc', 'encryptedToken' => hash_hmac('sha256', 'abc', self::SECRET)]);
        $this->assertNotNull($account->fresh()->webhook_received_at);

        // Secret Token kiritilmagan akkaunt webhook qabul qilmaydi.
        $other = ZoomAccount::factory()->create();
        $this->webhook($other, $validation)->assertNotFound();
    }

    public function test_participants_are_tracked_live_and_closed_when_meeting_ends(): void
    {
        $account = ZoomAccount::factory()->create(['webhook_secret' => self::SECRET]);
        $teacher = User::factory()->create();
        $lesson = Lesson::factory()->create(['zoom_account_id' => $account->id, 'user_id' => $teacher->id]);
        $joined = now()->subMinutes(5)->utc()->format('Y-m-d\TH:i:s\Z');

        $this->webhook($account, ['event' => 'meeting.started', 'payload' => ['object' => ['id' => $lesson->zoom_meeting_id, 'start_time' => $joined]]])->assertNoContent();
        $this->webhook($account, $this->participant($lesson, 'meeting.participant_joined', 'p-host', 'Teacher', ['id' => 'host-zoom-id', 'join_time' => $joined]))->assertNoContent();
        $this->webhook($account, $this->participant($lesson, 'meeting.participant_joined', 'p-ali', 'Ali Valiyev', ['join_time' => $joined]))->assertNoContent();
        // Zoom hodisani qayta yuborsa — ikki marta yozilmaydi.
        $this->webhook($account, $this->participant($lesson, 'meeting.participant_joined', 'p-ali', 'Ali Valiyev', ['join_time' => $joined]))->assertNoContent();
        $this->webhook($account, $this->participant($lesson, 'meeting.participant_joined', 'p-vali', 'Vali', ['join_time' => $joined]))->assertNoContent();
        $this->webhook($account, $this->participant($lesson, 'meeting.participant_left', 'p-vali', 'Vali', ['leave_time' => now()->subMinutes(2)->utc()->format('Y-m-d\TH:i:s\Z')]))->assertNoContent();

        $this->assertSame(Lesson::ZOOM_STARTED, $lesson->fresh()->zoom_state);

        $education = User::factory()->education()->create();
        $live = $this->actingAsUser($education)->getJson("/api/lessons/{$lesson->id}/live")->assertOk();
        $live->assertJsonPath('data.source', 'webhook')
            ->assertJsonPath('data.participants.0.name', 'TEACHER')
            ->assertJsonPath('data.participants.0.is_host', true)
            ->assertJsonPath('data.participants.1.name', 'ALI VALIYEV')
            ->assertJsonPath('data.participants.1.present', true)
            ->assertJsonPath('data.participants.2.name', 'VALI')
            ->assertJsonPath('data.participants.2.present', false)
            ->assertJsonPath('data.participants.2.minutes', 3)
            ->assertJsonCount(3, 'data.participants');

        $this->actingAsUser($education)->getJson('/api/monitor')
            ->assertOk()
            ->assertJsonPath('data.webhooks', true)
            ->assertJsonPath('data.live.0.id', $lesson->id)
            ->assertJsonPath('data.live.0.tracking.present', 1);

        // Darsning o'qituvchisi ko'ra oladi, boshqa o'qituvchi — yo'q.
        $this->actingAsUser($teacher)->getJson("/api/lessons/{$lesson->id}/live")->assertOk();
        $this->actingAsUser(User::factory()->create())->getJson("/api/lessons/{$lesson->id}/live")->assertForbidden();
        $this->actingAsUser($teacher)->getJson('/api/monitor')->assertForbidden();

        $this->webhook($account, ['event' => 'meeting.ended', 'payload' => ['object' => ['id' => $lesson->zoom_meeting_id, 'end_time' => now()->utc()->format('Y-m-d\TH:i:s\Z')]]])->assertNoContent();
        $lesson->refresh();
        $this->assertSame(Lesson::ZOOM_ENDED, $lesson->zoom_state);
        $this->assertSame(LessonStatus::Finished, $lesson->status);
        $this->assertNotNull($lesson->attendance_due_at);
        $this->assertSame(0, $lesson->liveParticipants()->whereNull('left_at')->count());
    }

    public function test_monitor_ignores_stale_started_state(): void
    {
        $stale = Lesson::factory()->finished()->create(['zoom_state' => Lesson::ZOOM_STARTED, 'starts_at' => now()->subHours(13), 'ends_at' => now()->subHours(12)]);
        $overtime = Lesson::factory()->create(['zoom_state' => Lesson::ZOOM_STARTED, 'starts_at' => now()->subHours(2), 'ends_at' => now()->subMinutes(30)]);

        $live = $this->actingAsUser(User::factory()->education()->create())->getJson('/api/monitor')->assertOk()->json('data.live');

        // Jadvaldan uzoqroq davom etayotgan dars — efirda; 12 soatdan eski "started" — yo'q.
        $this->assertSame([$overtime->id], array_column($live, 'id'));
        $this->assertNotContains($stale->id, array_column($live, 'id'));
    }

    public function test_unknown_meeting_is_ignored(): void
    {
        $account = ZoomAccount::factory()->create(['webhook_secret' => self::SECRET]);
        $this->webhook($account, ['event' => 'meeting.started', 'payload' => ['object' => ['id' => '999']]])->assertNoContent();
        $this->assertDatabaseCount('live_participants', 0);
    }

    public function test_dashboard_fallback_without_webhooks(): void
    {
        $account = ZoomAccount::factory()->create();
        $lesson = Lesson::factory()->create(['zoom_account_id' => $account->id, 'zoom_state' => Lesson::ZOOM_STARTED]);
        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            'api.zoom.us/v2/metrics/meetings/*' => Http::response(['code' => 200, 'message' => 'Dashboard feature is not enabled'], 400),
        ]);

        $this->actingAsUser(User::factory()->superAdmin()->create())->getJson("/api/lessons/{$lesson->id}/live")
            ->assertOk()
            ->assertJsonPath('data.source', 'none')
            ->assertJsonPath('data.reason', 'Dashboard feature is not enabled');
    }

    public function test_hosts_are_synced_from_zoom_and_basic_users_start_inactive(): void
    {
        $account = ZoomAccount::factory()->create();
        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            'api.zoom.us/v2/users*' => Http::response(['users' => [
                ['id' => 'u1', 'email' => 'Zoom_1@karsu.uz', 'display_name' => 'Zoom 1', 'type' => 2],
                ['id' => 'u2', 'email' => 'basic@karsu.uz', 'display_name' => 'Basic', 'type' => 1],
            ]]),
        ]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAsUser($admin)->postJson("/api/zoom-accounts/{$account->id}/sync-hosts")->assertOk()->assertJsonPath('data.users', 2);
        $this->assertTrue(ZoomHost::where('email', 'zoom_1@karsu.uz')->value('is_active'));
        $this->assertFalse(ZoomHost::where('email', 'basic@karsu.uz')->value('is_active'));

        $basic = ZoomHost::where('email', 'basic@karsu.uz')->first();
        $this->actingAsUser($admin)->patchJson("/api/zoom-hosts/{$basic->id}", ['is_active' => true])->assertOk()->assertJsonPath('data.is_active', true);
        $this->actingAsUser($admin)->getJson('/api/zoom-hosts')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAsUser(User::factory()->education()->create())->getJson('/api/zoom-hosts')->assertForbidden();
    }

    public function test_webhook_secret_is_write_only(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $account = ZoomAccount::factory()->create();

        $this->actingAsUser($admin)->putJson("/api/zoom-accounts/{$account->id}", [
            'name' => $account->name, 'account_id' => $account->account_id, 'client_id' => $account->client_id, 'webhook_secret' => self::SECRET,
        ])->assertOk()
            ->assertJsonPath('data.has_webhook_secret', true)
            ->assertJsonPath('data.webhook_url', url("/api/zoom/webhook/{$account->id}"))
            ->assertJsonMissing(['webhook_secret' => self::SECRET]);
        $this->assertSame(self::SECRET, $account->fresh()->webhook_secret);
    }
}
