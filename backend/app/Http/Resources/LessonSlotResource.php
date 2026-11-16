<?php

namespace App\Http\Resources;

use App\Models\LessonSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LessonSlot */
class LessonSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->endsAtLabel(),
            'duration_minutes' => $this->duration_minutes,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
        ];
    }
}
