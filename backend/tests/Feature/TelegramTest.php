<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramTest extends TestCase
{
    use RefreshDatabase;

    private function update(string $text, int $chatId = 4242): array
    {
        return ['update_id' => 1, 'message' => [
            'message_id' => 1,
            'text' => $text,
            'chat' => ['id' => $chatId, 'type' => 'private'],
            'from' => ['id' => $chatId, 'username' => 'rustem_j'],
        ]];
    }

    public function test_webhook_requires_secret(): void
    {
        $this->postJson('/api/telegram/webhook', $this->update('/start'))->assertForbidden();
        $this->postJson('/api/telegram/webhook', $this->update('/start'), ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])->assertForbidden();
    }

    public function test_user_links_telegram_with_one_time_deep_link(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $user = User::factory()->pending()->create();

        $url = $this->actingAsUser($user)->postJson('/api/me/telegram')->assertOk()->json('data.url');
        $this->assertStringStartsWith('https://t.me/mt_test_bot?start=', $url);
        $token = substr($url, strpos($url, '=') + 1);

        $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret'];
        $this->postJson('/api/telegram/webhook', $this->update("/start {$token}"), $headers)->assertOk();

        $user->refresh();
        $this->assertSame('4242', $user->telegram_chat_id);
        $this->assertSame('rustem_j', $user->telegram_username);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/sendMessage') && str_contains($request['text'], 'Hisobingiz ulandi'));

        // Token bir martalik: ikkinchi marta boshqa chat ulana olmaydi.
        $this->postJson('/api/telegram/webhook', $this->update("/start {$token}", 9999), $headers)->assertOk();
        $this->assertSame('4242', $user->refresh()->telegram_chat_id);
    }

    public function test_chat_is_moved_from_previous_owner(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $old = User::factory()->create(['telegram_chat_id' => '4242']);
        $new = User::factory()->create();

        $url = $this->actingAsUser($new)->postJson('/api/me/telegram')->json('data.url');
        $token = substr($url, strpos($url, '=') + 1);
        $this->postJson('/api/telegram/webhook', $this->update("/start {$token}"), ['X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret'])->assertOk();

        $this->assertNull($old->refresh()->telegram_chat_id);
        $this->assertSame('4242', $new->refresh()->telegram_chat_id);
    }

    public function test_user_can_unlink(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '1']);

        $this->actingAsUser($user)->deleteJson('/api/me/telegram')->assertNoContent();
        $this->assertNull($user->refresh()->telegram_chat_id);
    }
}
