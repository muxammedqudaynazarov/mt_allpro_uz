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
            'name' => $this->displayName(),
            'first_name' => $this->firstName(),
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'short_name' => $this->shortName(),
            'picture_url' => $this->picture_url,
            'hemis_id' => $this->hemis_id,
            'status' => $this->status->value,
            // Faqat foydalanuvchi o'zi tanlagan til; tanlamagan bo'lsa null — interfeys asosiy tilda ochiladi.
            'locale' => in_array($this->locale, (array) config('app.supported_locales'), true) ? $this->locale : null,
            'roles' => array_values($this->roles ?? []),
            'permissions' => $this->permissions(),
            'has_password' => $this->password !== null,
            'telegram' => [
                'linked' => $this->telegram_chat_id !== null,
                'username' => $this->telegram_username,
            ],
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
