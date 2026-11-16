<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBot;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {--url= : Webhook manzili (standart: APP_URL/api/telegram/webhook)}';

    protected $description = 'Telegram botiga webhook manzilini oʻrnatadi';

    public function handle(TelegramBot $bot): int
    {
        $secret = (string) config('services.telegram.webhook_secret');
        if (! $bot->enabled() || $secret === '') {
            $this->error('TELEGRAM_BOT_TOKEN, TELEGRAM_BOT_USERNAME va TELEGRAM_WEBHOOK_SECRET .env faylida koʻrsatilishi kerak.');

            return self::FAILURE;
        }

        $url = $this->option('url') ?: rtrim((string) config('app.url'), '/').'/api/telegram/webhook';
        if (! str_starts_with($url, 'https://')) {
            $this->error('Telegram faqat HTTPS manzilni qabul qiladi: '.$url);

            return self::FAILURE;
        }

        if (! $bot->setWebhook($url, $secret)) {
            $this->error('Webhook oʻrnatilmadi. Token va internet aloqasini tekshiring (batafsil: storage/logs).');

            return self::FAILURE;
        }

        $this->info("Webhook oʻrnatildi: {$url}");

        return self::SUCCESS;
    }
}
