<?php

namespace App\Http\Resources;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Group */
class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hemis_id' => $this->hemis_id,
            'course' => $this->course,
            'is_active' => $this->is_active,
            'zoom_user_email' => $this->zoom_user_email,
            'zoom_account' => $this->zoomAccount ? ['id' => $this->zoomAccount->id, 'name' => $this->zoomAccount->name] : null,
            'admin' => $this->admin ? ['id' => $this->admin->id, 'name' => $this->admin->name] : null,
            'lessons_count' => (int) ($this->lessons_count ?? 0),
            'live' => (bool) ($this->live_lessons_count ?? false),
        ];
    }
}
