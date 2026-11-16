<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Telegram Bot API bilan ishlash. Token faqat .env'da saqlanadi va so'rovlar
 * POST/JSON orqali yuboriladi (eski tizimda token va parollar GET URL'da ketardi).
 */
class TelegramBot
{
    public function __construct(
        private readonly ?string $token,
        private readonly ?string $username,
        private readonly string $apiUrl = 'https://api.telegram.org',
    ) {}

    public static function make(): self
    {
        return new self(
            config('services.telegram.bot_token'),
            config('services.telegram.bot_username'),
            rtrim((string) config('services.telegram.api_url', 'https://api.telegram.org'), '/'),
        );
    }

    public function enabled(): bool
    {
        return filled($this->token) && filled($this->username);
    }

    public function username(): ?string
    {
        return $this->username ? ltrim($this->username, '@') : null;
    }

    public function deepLink(string $payload): string
    {
        return 'https://t.me/'.$this->username().'?start='.$payload;
    }

    /**
     * @param  list<list<array{text: string, url: string}>>  $buttons
     */
    public function send(string|int $chatId, string $html, array $buttons = []): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
        ];
        if ($buttons) {
            $payload['reply_markup'] = ['inline_keyboard' => $buttons];
        }

        $response = $this->call('sendMessage', $payload);
        if (! $response['ok']) {
            Log::warning('Telegram xabari yuborilmadi', ['chat' => $chatId, 'error' => $response['description'] ?? null]);
        }

        return (bool) $response['ok'];
    }

    public function setWebhook(string $url, string $secret): bool
    {
        $response = $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
            'drop_pending_updates' => true,
        ]);

        return (bool) $response['ok'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $method, array $payload): array
    {
        if (! $this->token) {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN sozlanmagan.');
        }

        try {
            $response = Http::asJson()->acceptJson()->timeout(10)
                ->post("{$this->apiUrl}/bot{$this->token}/{$method}", $payload);
        } catch (ConnectionException $e) {
            Log::warning('Telegram API ulanmadi', ['method' => $method, 'error' => $e->getMessage()]);

            return ['ok' => false, 'description' => $e->getMessage()];
        }

        return (array) ($response->json() ?? ['ok' => false, 'description' => 'HTTP '.$response->status()]);
    }
}
