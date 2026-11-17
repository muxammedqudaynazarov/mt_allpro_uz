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
            // Talabalar havolasi faqat dars davom etayotganda kerak.
            'join_url' => $open ? $this->join_url : null,
            'passcode' => $open ? $this->passcode : null,
            'group' => [
                'id' => $this->group->id,
                'name' => $this->group->name,
                'course' => $this->group->course,
            ],
            'teacher' => ['id' => $this->teacher->id, 'name' => $this->teacher->name],
            'slot' => $this->slot ? ['id' => $this->slot->id, 'name' => $this->slot->label(), 'starts_at' => $this->slot->starts_at] : null,
            'can' => [
                'start' => (bool) $user?->can('start', $this->resource),
                'end' => (bool) $user?->can('end', $this->resource),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
