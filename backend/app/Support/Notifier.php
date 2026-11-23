<?php

namespace App\Support;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Jobs\SendTelegramMessage;
use App\Models\Lesson;
use App\Models\LessonSeries;
use App\Models\User;
use App\Services\Telegram\TelegramBot;
use Illuminate\Support\Carbon;
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

        $l = $teacher->preferredLocale();
        $time = $lesson->starts_at->format('H:i').'–'.$lesson->ends_at->format('H:i');
        $day = $lesson->starts_at->isToday() ? __('bugun', [], $l) : $lesson->starts_at->format('d.m.Y');
        $lines = [
            '<b>'.($lesson->lesson_series_id ? __('Haftalik dars: Zoom uchrashuvi tayyor', [], $l) : __('Dars yaratildi', [], $l)).'</b>',
            '',
            $lesson->is_lecture
                ? '<b>'.__('Maʼruza, guruhlar:', [], $l).'</b> '.e($lesson->groupsLabel())
                : '<b>'.__('Guruh:', [], $l).'</b> '.e($lesson->group->name),
            '<b>'.__('Vaqt:', [], $l).'</b> '.$day.', '.$time,
            '<b>'.__('Mavzu:', [], $l).'</b> '.e($lesson->topic),
        ];
        if ($lesson->passcode) {
            $lines[] = '<b>'.__('Kirish kodi:', [], $l).'</b> <code>'.e($lesson->passcode).'</code>';
        }
        $lines[] = '';
        $lines[] = __('Talabalar uchun havola:', [], $l);
        $lines[] = '<code>'.e((string) $lesson->join_url).'</code>';
        $lines[] = '';
        $lines[] = __('«Guruhga yuborish» tugmasi orqali havolani guruh chatiga joʻnating.', [], $l);
        if ($lesson->lesson_series_id) {
            $lines[] = __('Bu dars har hafta avtomatik yaratiladi. Bekor qilish uchun tizimdagi kalendarni oching.', [], $l);
        }

        // Darsni boshlash havolasi imzolangan va dars tugashi bilan eskiradi.
        // Zoom'ning start_url (ZAK) havolasi 2 soatda eskiradi, shuning uchun
        // u to'g'ridan-to'g'ri yuborilmaydi — har bosilganda yangisi olinadi.
        $launch = URL::temporarySignedRoute('lessons.launch', $lesson->ends_at, ['lesson' => $lesson->id]);
        $share = 'https://t.me/share/url?'.http_build_query(['url' => $lesson->join_url, 'text' => $lesson->groupsLabel().', '.$time]);

        SendTelegramMessage::dispatch((string) $teacher->telegram_chat_id, implode("\n", $lines), [
            [['text' => __('Darsni boshlash', [], $l), 'url' => $launch]],
            [['text' => __('Guruhga yuborish', [], $l), 'url' => $share]],
        ]);
    }

    /** Takroriy dars uchun uchrashuv yaratilmadi (masalan, bo'sh host yo'q). */
    public function seriesFailed(LessonSeries $series, Carbon $day, string $reason): void
    {
        $teacher = $series->teacher;
        if (! $this->bot->enabled() || ! $teacher?->telegram_chat_id) {
            return;
        }
        $l = $teacher->preferredLocale();
        $time = $series->slot->startOn($day)->format('H:i').'–'.$series->slot->endOn($day)->format('H:i');
        $text = implode("\n", [
            '<b>'.__('Haftalik dars yaratilmadi', [], $l).'</b>',
            '',
            $series->is_lecture
                ? '<b>'.__('Maʼruza, guruhlar:', [], $l).'</b> '.e($series->groupsLabel())
                : '<b>'.__('Guruh:', [], $l).'</b> '.e($series->group->name),
            '<b>'.__('Vaqt:', [], $l).'</b> '.__('bugun', [], $l).', '.$time,
            '<b>'.__('Sabab:', [], $l).'</b> '.e($reason),
            '',
            __('Tizim dars tugashiga 10 daqiqa qolguncha qayta urinadi. Darsni qoʻlda ham yaratishingiz mumkin.', [], $l),
        ]);
        $url = rtrim((string) config('app.frontend_url'), '/').'/lessons/new';
        SendTelegramMessage::dispatch((string) $teacher->telegram_chat_id, $text, [[['text' => __('Dars yaratish', [], $l), 'url' => $url]]]);
    }

    public function userPending(User $user): void
    {
        if (! $this->bot->enabled()) {
            return;
        }

        $admins = User::query()
            ->where('status', UserStatus::Active->value)
            ->whereNotNull('telegram_chat_id')
            // Tasdiqlay oladiganlar: Administrator va O'quv bo'limi.
            ->where(fn ($query) => $query->whereJsonContains('roles', Role::SuperAdmin->value)->orWhereJsonContains('roles', Role::Education->value))
            ->get();

        $url = rtrim((string) config('app.frontend_url'), '/').'/users?status=pending';
        foreach ($admins as $admin) {
            $l = $admin->preferredLocale();
            $text = '<b>'.__('Yangi foydalanuvchi tasdiq kutmoqda', [], $l)."</b>\n\n".e($user->displayName());
            SendTelegramMessage::dispatch((string) $admin->telegram_chat_id, $text, [[['text' => __('Koʻrib chiqish', [], $l), 'url' => $url]]]);
        }
    }

    public function userActivated(User $user): void
    {
        if (! $this->bot->enabled() || ! $user->telegram_chat_id) {
            return;
        }
        $url = rtrim((string) config('app.frontend_url'), '/');
        $l = $user->preferredLocale();
        SendTelegramMessage::dispatch(
            (string) $user->telegram_chat_id,
            '<b>'.__('Hisobingiz faollashtirildi', [], $l)."</b>\n\n".__('Endi Masofaviy taʼlim tizimida dars yaratishingiz mumkin.', [], $l),
            [[['text' => __('Tizimni ochish', [], $l), 'url' => $url]]],
        );
    }
}
