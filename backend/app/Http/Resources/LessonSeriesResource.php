<?php

namespace App\Http\Resources;

use App\Models\LessonSeries;
use App\Support\GroupRef;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LessonSeries */
class LessonSeriesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $today = today()->toDateString();

        return [
            'id' => $this->id,
            'weekday' => $this->weekday,
            'topic' => $this->topic,
            'group' => GroupRef::make($this->group),
            'is_lecture' => (bool) $this->is_lecture,
            'groups' => GroupRef::list($this->groupList()),
            'teacher' => ['id' => $this->teacher->id, 'name' => $this->teacher->displayName()],
            'slot' => ['id' => $this->slot->id, 'name' => $this->slot->label(), 'starts_at' => $this->slot->starts_at, 'ends_at' => $this->slot->endsAtLabel()],
            'host' => $this->host ? ['id' => $this->host->id, 'name' => $this->host->name, 'email' => $this->host->email] : null,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'is_active' => $this->is_active,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'next_date' => $this->is_active ? $this->nextDate()?->toDateString() : null,
            'skips' => $this->skips->map(fn ($skip) => $skip->date->toDateString())->filter(fn (string $date) => $date >= $today)->sort()->values(),
            'last_error' => $this->last_error_on?->isToday() ? $this->last_error : null,
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'cancel' => (bool) $user?->can('cancel', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
        ];
    }
}
