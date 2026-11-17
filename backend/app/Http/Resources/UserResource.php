<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'picture_url' => $this->picture_url,
            'hemis_id' => $this->hemis_id,
            'phone' => $this->phone,
            'department' => $this->department,
            'status' => $this->status->value,
            'roles' => array_values($this->roles ?? []),
            'has_password' => $this->password !== null,
            'telegram_linked' => $this->telegram_chat_id !== null,
            'can_manage_calendar' => (bool) $this->can_manage_calendar,
            'lessons_count' => (int) ($this->lessons_count ?? 0),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
