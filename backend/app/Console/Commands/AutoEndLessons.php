<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use App\Services\Zoom\MeetingTracker;
use App\Services\Zoom\ZoomClient;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Har daqiqada: Zoom'ni avtomatik yakunlash yoqilgan holda yaratilgan va vaqti kelgan darslarni Zoom'da yopadi
 * (Umumiy sozlamalar -> Zoom darslari). Host uchrashuvni qayta ochsa ham, keyingi daqiqada yana yopiladi.
 */
class AutoEndLessons extends Command
{
    protected $signature = 'lessons:auto-end';

    protected $description = 'Vaqti kelgan darslarni Zoom\'da avtomatik yakunlaydi';

    public function handle(ZoomClient $zoom, MeetingTracker $tracker): int
    {
        $lessons = Lesson::query()
            ->where('auto_end', true)
            ->where('zoom_state', Lesson::ZOOM_STARTED)
            ->whereNotNull('zoom_meeting_id')
            ->where('ends_at', '<=', now())
            ->where('ends_at', '>', now()->subHours(6))
            ->with(['zoomAccount', 'group'])
            ->get();

        foreach ($lessons as $lesson) {
            try {
                if ($lesson->zoomAccount) {
                    $zoom->endMeeting($lesson->zoomAccount, (string) $lesson->zoom_meeting_id);
                }
                $tracker->markEnded($lesson, now());
                $lesson->save();
                Audit::log('lesson.auto_ended', 'Zoom avtomatik yakunlandi: :group', $lesson, replace: ['group' => $lesson->group?->name]);
                $this->info("Yakunlandi: #{$lesson->id}");
            } catch (Throwable $e) {
                // Keyingi daqiqada qayta urinadi.
                Log::warning('Zoom avtomatik yakunlanmadi', ['lesson' => $lesson->id, 'error' => $e->getMessage()]);
            }
        }

        return self::SUCCESS;
    }
}
