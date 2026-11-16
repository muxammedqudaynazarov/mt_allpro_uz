<?php

namespace App\Jobs;

use App\Services\Telegram\TelegramBot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/** Telegram xabarini navbat (queue) orqali yuboradi — foydalanuvchi kutib qolmaydi. */
class SendTelegramMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /** @param list<list<array{text: string, url: string}>> $buttons */
    public function __construct(
        public readonly string $chatId,
        public readonly string $html,
        public readonly array $buttons = [],
    ) {}

    public function handle(TelegramBot $bot): void
    {
        if (! $bot->enabled()) {
            return;
        }
        if (! $bot->send($this->chatId, $this->html, $this->buttons)) {
            throw new RuntimeException('Telegram xabari yuborilmadi');
        }
    }
}
