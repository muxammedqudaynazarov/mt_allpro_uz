<?php

namespace App\Console\Commands;

use App\Actions\SyncLessonAttendance;
use App\Exceptions\ExternalServiceException;
use App\Models\Lesson;
use App\Services\Zoom\MeetingTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Har daqiqada: darslarning Zoom'dagi holatini so'raydi (boshlandimi, host tugatdimi)
 * va tugagan darslar uchun Zoom hisobotidan davomatni oladi.
 */
class TrackZoomMeetings extends Command
{
    protected $signature = 'lessons:track-zoom';

    protected $description = 'Darslarning Zoom holatini kuzatish va davomatni olish';

    public function handle(MeetingTracker $tracker, SyncLessonAttendance $sync): int
    {
        $polled = 0;
        foreach (MeetingTracker::due()->with('zoomAccount')->get() as $lesson) {
            if (! $lesson->zoomAccount) {
                continue;
            }
            try {
                $tracker->poll($lesson);
                $polled++;
            } catch (ExternalServiceException $e) {
                // Har daqiqa takrorlanmasligi uchun bir akkaunt bo'yicha soatiga bir marta yoziladi.
                if (Cache::add('zoom:track-error:'.$lesson->zoom_account_id, true, 3600)) {
                    Log::warning('Zoom holatini olib boʻlmadi', ['lesson' => $lesson->id, 'error' => $e->getMessage()]);
                }
            }
        }

        // Juda uzoq "efirda" qolganlar (Zoom holati yangilanmay qolgan) — hisobotdan aniqlanadi.
        Lesson::query()
            ->where('zoom_state', Lesson::ZOOM_STARTED)
            ->where('starts_at', '<', now()->subHours(12))
            ->get()
            ->each(function (Lesson $lesson) use ($tracker) {
                $tracker->markEnded($lesson, now());
                $lesson->save();
            });

        $synced = 0;
        Lesson::query()
            ->whereNull('attendance_synced_at')
            ->whereNotNull('attendance_due_at')
            ->where('attendance_due_at', '<=', now())
            ->with('zoomAccount')
            ->orderBy('attendance_due_at')
            ->limit(10)
            ->get()
            ->each(function (Lesson $lesson) use ($sync, &$synced) {
                $synced += (int) $sync->attempt($lesson);
            });

        if ($this->output->isVerbose()) {
            $this->info("Tekshirildi: {$polled}, davomat olindi: {$synced}");
        }

        return self::SUCCESS;
    }
}
