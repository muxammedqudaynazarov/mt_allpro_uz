<?php

namespace App\Http\Controllers\Api;

use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Services\Zoom\ZoomClient;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;

/**
 * Telegram'dagi "Darsni boshlash" tugmasi. Havola imzolangan va dars tugashi
 * bilan eskiradi; har bosilganda Zoom'dan yangi host havolasi olinadi.
 */
class LessonLaunchController extends Controller
{
    public function __invoke(Lesson $lesson, ZoomClient $zoom): RedirectResponse
    {
        $front = rtrim((string) config('app.frontend_url'), '/');
        if (! $lesson->isOpen() || ! $lesson->zoomAccount || ! $lesson->zoom_meeting_id) {
            return redirect()->away($front.'/lessons');
        }

        $url = $zoom->freshStartUrl($lesson->zoomAccount, $lesson->zoom_meeting_id) ?? $lesson->start_url;
        if (! $url) {
            return redirect()->away($front.'/lessons');
        }

        if ($lesson->status === LessonStatus::Scheduled && $lesson->starts_at->isPast()) {
            $lesson->forceFill(['status' => LessonStatus::Live])->save();
        }
        Audit::log('lesson.launched', 'Dars Telegram orqali boshlandi: :group', $lesson, actor: $lesson->teacher, replace: ['group' => $lesson->group?->name]);

        return redirect()->away($url);
    }
}
