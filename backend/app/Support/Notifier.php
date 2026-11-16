<?php

namespace App\Support;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Jobs\SendTelegramMessage;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Telegram\TelegramBot;
use Illuminate\Support\Facades\URL;

/** Telegram orqali yuboriladigan xabarlar matni bir joyda. */
class Notifier
{
    public function __construct(private readonly TelegramBot $bot) {}

    public function lessonCreated(Lesson $lesson): void
    {
        $teacher = $lesson->teacher;
        if (! $this->bot->enabled() || ! $teacher?->telegram_chat_id) {
            return;
        }

        $time = $lesson->starts_at->format('H:i').'–'.$lesson->ends_at->format('H:i');
        $day = $lesson->starts_at->isToday() ? 'bugun' : $lesson->starts_at->format('d.m.Y');
        $lines = [
            '<b>Dars yaratildi</b>',
            '',
            '<b>Guruh:</b> '.e($lesson->group->name),
            '<b>Vaqt:</b> '.$day.', '.$time,
            '<b>Mavzu:</b> '.e($lesson->topic),
        ];
        if ($lesson->passcode) {
            $lines[] = '<b>Kirish kodi:</b> <code>'.e($lesson->passcode).'</code>';
        }
        $lines[] = '';
        $lines[] = 'Talabalar uchun havola:';
        $lines[] = '<code>'.e((string) $lesson->join_url).'</code>';
        $lines[] = '';
        $lines[] = '«Guruhga yuborish» tugmasi orqali havolani guruh chatiga joʻnating.';

        // Darsni boshlash havolasi imzolangan va dars tugashi bilan eskiradi.
        // Zoom'ning start_url (ZAK) havolasi 2 soatda eskiradi, shuning uchun
        // u to'g'ridan-to'g'ri yuborilmaydi — har bosilganda yangisi olinadi.
        $launch = URL::temporarySignedRoute('lessons.launch', $lesson->ends_at, ['lesson' => $lesson->id]);
        $share = 'https://t.me/share/url?'.http_build_query(['url' => $lesson->join_url, 'text' => $lesson->group->name.', '.$time]);

        SendTelegramMessage::dispatch((string) $teacher->telegram_chat_id, implode("\n", $lines), [
            [['text' => 'Darsni boshlash', 'url' => $launch]],
            [['text' => 'Guruhga yuborish', 'url' => $share]],
        ]);
    }

    public function userPending(User $user): void
    {
        if (! $this->bot->enabled()) {
            return;
        }

        $admins = User::query()
            ->where('status', UserStatus::Active->value)
            ->whereNotNull('telegram_chat_id')
            ->where(fn ($q) => $q->whereJsonContains('roles', Role::Admin->value)->orWhereJsonContains('roles', Role::SuperAdmin->value))
            ->get();

        $text = "<b>Yangi xodim tasdiq kutmoqda</b>\n\n".e($user->name).($user->department ? "\n".e($user->department) : '');
        $url = rtrim((string) config('app.frontend_url'), '/').'/users?status=pending';
        foreach ($admins as $admin) {
            SendTelegramMessage::dispatch((string) $admin->telegram_chat_id, $text, [[['text' => 'Koʻrib chiqish', 'url' => $url]]]);
        }
    }

    public function userActivated(User $user): void
    {
        if (! $this->bot->enabled() || ! $user->telegram_chat_id) {
            return;
        }
        $url = rtrim((string) config('app.frontend_url'), '/');
        SendTelegramMessage::dispatch(
            (string) $user->telegram_chat_id,
            "<b>Hisobingiz faollashtirildi</b>\n\nEndi Masofaviy taʼlim tizimida dars yaratishingiz mumkin.",
            [[['text' => 'Tizimni ochish', 'url' => $url]]],
        );
    }
}
