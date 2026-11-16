<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->firstName(),
            'short_name' => $this->shortName(),
            'email' => $this->email,
            'phone' => $this->phone,
            'picture_url' => $this->picture_url,
            'department' => $this->department,
            'hemis_id' => $this->hemis_id,
            'status' => $this->status->value,
            'roles' => array_values($this->roles ?? []),
            'permissions' => $this->permissions(),
            'has_password' => $this->password !== null,
            'telegram' => [
                'linked' => $this->telegram_chat_id !== null,
                'username' => $this->telegram_username,
            ],
            'managed_by' => $this->managedBy ? ['id' => $this->managedBy->id, 'name' => $this->managedBy->name] : null,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
