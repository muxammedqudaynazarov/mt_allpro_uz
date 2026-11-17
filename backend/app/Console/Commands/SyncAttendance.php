<?php

namespace App\Console\Commands;

use App\Actions\SyncLessonAttendance;
use App\Enums\LessonStatus;
use App\Exceptions\ExternalServiceException;
use App\Models\Lesson;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Oldin o'tgan darslar uchun davomatni Zoom hisobotidan olish (masalan, tizim
 * yangilanishidan oldingi darslar). Zoom API ma'lumotni taxminan 6 oy saqlaydi.
 *
 *   php artisan lessons:sync-attendance --from=2026-11-01
 *   php artisan lessons:sync-attendance --from=2026-11-01 --force   (qayta olish)
 */
class SyncAttendance extends Command
{
    protected $signature = 'lessons:sync-attendance
        {--from= : Sanadan (Y-m-d), standart: 7 kun oldin}
        {--to= : Sanagacha (Y-m-d), standart: bugun}
        {--group= : Faqat shu guruh ID}
        {--force : Davomati olingan darslarni ham qayta olish}';

    protected $description = 'Oʻtgan darslar davomatini Zoom hisobotidan olish';

    public function handle(SyncLessonAttendance $sync): int
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : now()->subDays(7)->startOfDay();
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : now();
        if ($from->lessThan(now()->subMonths(6))) {
            $this->warn('Zoom API maʼlumotni taxminan 6 oy saqlaydi — undan eski darslar topilmasligi mumkin.');
        }

        $lessons = Lesson::query()
            ->whereNotNull('zoom_meeting_id')
            ->whereNotNull('zoom_account_id')
            ->where('status', '!=', LessonStatus::Cancelled->value)
            ->whereBetween('starts_at', [$from, $to])
            ->where('ends_at', '<', now())
            ->when($this->option('group'), fn ($q, $group) => $q->where('group_id', $group))
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('attendance_synced_at'))
            ->with('zoomAccount')
            ->orderBy('starts_at')
            ->get();

        if ($lessons->isEmpty()) {
            $this->info('Davomati olinmagan dars yoʻq.');

            return self::SUCCESS;
        }

        $done = $pending = $failed = 0;
        $this->withProgressBar($lessons, function (Lesson $lesson) use ($sync, &$done, &$pending, &$failed) {
            try {
                $sync->handle($lesson) ? $done++ : $pending++;
            } catch (ExternalServiceException $e) {
                $failed++;
                $lesson->forceFill(['attendance_error' => mb_substr($e->getMessage(), 0, 255)])->save();
            }
        });
        $this->newLine(2);
        $this->info("Olindi: {$done}, hisobot hali tayyor emas: {$pending}, xato: {$failed}");
        if ($failed) {
            $this->line('Xato sababi dars sahifasida koʻrinadi (masalan, Zoom ilovasida ruxsat yetishmaydi).');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
