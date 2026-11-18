<?php

namespace App\Http\Resources;

use App\Models\Recording;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Recording */
class RecordingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ready = $this->status === Recording::DOWNLOADED;

        return [
            'id' => $this->id,
            'file_type' => $this->file_type,
            'recording_type' => $this->recording_type,
            'extension' => $this->file_extension,
            'size' => $this->size,
            'status' => $this->status,
            'error' => $this->status === Recording::DOWNLOADED ? null : $this->error,
            'attempts' => $this->attempts,
            'max_attempts' => (int) config('recordings.max_attempts'),
            'recording_start' => $this->recording_start?->toIso8601String(),
            'recording_end' => $this->recording_end?->toIso8601String(),
            'downloaded_at' => $this->downloaded_at?->toIso8601String(),
            'zoom_deleted_at' => $this->zoom_deleted_at?->toIso8601String(),
            'url' => $ready ? url('/api/recordings/'.$this->id.'/file') : null,
            'lesson' => $this->whenLoaded('lesson', fn () => $this->lesson ? [
                'id' => $this->lesson->id,
                'starts_at' => $this->lesson->starts_at->toIso8601String(),
                'group' => $this->lesson->group?->name,
                'teacher' => $this->lesson->teacher?->displayName(),
            ] : null),
        ];
    }
}
