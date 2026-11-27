<?php

namespace App\Console\Commands;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Support\Notifier;
use Illuminate\Console\Command;

/**
 * Kalendardagi darslar haqida o'qituvchiga Telegram eslatmasi: boshlanishidan 30 daqiqa oldin
 * (config lessons.reminder_minutes) — "Darsni boshlash", "Guruhga yuborish" tugmalari bilan.
 * Haftalik darslar uchun bu yagona xabar; oldindan rejalashtirilgan darslar uchun — eslatma.
 * Dars boshlanishiga oz qolganda yaratilgan bo'lsa (yaratilganda xabar borgan) — yuborilmaydi.
 */
class RemindLessons extends Command
{
    protected $signature = 'lessons:remind';

    protected $description = 'Darsdan 30 daqiqa oldin o‘qituvchiga Telegram eslatmasi';

    /** Qo'lda yaratilgan dars shundan oldinroq yaratilgan bo'lsagina eslatiladi. */
    private const PLANNED_AHEAD_MINUTES = 45;

    public function handle(Notifier $notifier): int
    {
        $minutes = (int) config('lessons.reminder_minutes', 30);
        if ($minutes <= 0) {
            return self::SUCCESS;
        }
        $now = now();

        $lessons = Lesson::query()
            ->whereNull('reminded_at')
            ->where('status', LessonStatus::Scheduled->value)
            // Hali boshlanmagan (o'qituvchi Zoom'ni ochmagan).
            ->where(fn ($q) => $q->whereNull('zoom_state')->orWhere('zoom_state', Lesson::ZOOM_WAITING))
            // Server biroz kechiksa ham (lekin dars boshlangach 5 daqiqadan keyin emas).
            ->whereBetween('starts_at', [$now->copy()->subMinutes(5), $now->copy()->addMinutes($minutes)])
            ->where(fn ($q) => $q
                ->whereNotNull('lesson_series_id')
                ->orWhereRaw('created_at <= '.$this->minus('starts_at', self::PLANNED_AHEAD_MINUTES)))
            ->with(['teacher', 'group', 'groups', 'slot'])
            ->get();

        foreach ($lessons as $lesson) {
            // Avval belgilanadi: ikki marta yuborilmasin.
            $updated = Lesson::query()->whereKey($lesson->id)->whereNull('reminded_at')->update(['reminded_at' => $now]);
            if ($updated) {
                $notifier->lessonReminder($lesson);
            }
        }

        return self::SUCCESS;
    }

    /** "ustun − N daqiqa" (SQLite va MySQL). */
    private function minus(string $column, int $minutes): string
    {
        return Lesson::query()->getConnection()->getDriverName() === 'sqlite'
            ? "datetime({$column}, '-{$minutes} minutes')"
            : "DATE_SUB({$column}, INTERVAL {$minutes} MINUTE)";
    }
}
