<?php

namespace App\Http\Resources;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lesson */
class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $status = $this->effectiveStatus();
        $open = $status->isOpen();

        return [
            'id' => $this->id,
            'topic' => $this->topic,
            'status' => $status->value,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            // Talabalar havolasi faqat dars davom etayotganda kerak (Zoom'da jadvaldan uzoqroq davom etsa ham).
            'join_url' => $open || $this->zoom_state === Lesson::ZOOM_STARTED ? $this->join_url : null,
            'passcode' => $open ? $this->passcode : null,
            'group' => [
                'id' => $this->group->id,
                'name' => $this->group->name,
                'course' => $this->group->course,
            ],
            'teacher' => ['id' => $this->teacher->id, 'name' => $this->teacher->displayName()],
            'host' => $this->zoom_host_email,
            // Har haftalik jadvaldan yaratilgan bo'lsa.
            'series_id' => $this->lesson_series_id,
            // Uchrashuv Zoom bulutiga yozish bilan yaratilganmi.
            'record' => (bool) $this->record_cloud,
            'slot' => $this->slot ? ['id' => $this->slot->id, 'name' => $this->slot->label(), 'starts_at' => $this->slot->starts_at] : null,
            // Zoom'dagi haqiqiy holat: host qachon boshlagan/tugatgan, nechta talaba qatnashgan.
            'tracking' => [
                'state' => $this->zoom_state,
                'started_at' => $this->zoom_started_at?->toIso8601String(),
                'ended_at' => $this->zoom_ended_at?->toIso8601String(),
                'start_delay_minutes' => $this->startDelayMinutes(),
                'minutes' => $this->actualMinutes(),
                'participants' => $this->attendance_synced_at ? $this->resource->getAttribute('participants_count') : null,
                'synced' => $this->attendance_synced_at !== null,
                // Hozir darsda (webhook orqali; faqat monitoring so'rovida hisoblanadi).
                'present' => $this->resource->getAttribute('present_count'),
                'error' => $this->attendance_synced_at ? null : $this->attendance_error,
            ],
            'can' => [
                'start' => (bool) $user?->can('start', $this->resource),
                'end' => (bool) $user?->can('end', $this->resource),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
