<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    private function translatedDescription(): string
    {
        $t = $this->properties['_t'] ?? null;

        return is_array($t) && isset($t['key']) ? __($t['key'], (array) ($t['replace'] ?? [])) : $this->description;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->translatedDescription(),
            'user' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->displayName()] : null,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'properties' => collect($this->properties ?? [])->except('_t')->all() ?: null,
            'ip' => $this->ip,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
