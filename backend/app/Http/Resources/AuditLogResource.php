<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'user' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'properties' => $this->properties,
            'ip' => $this->ip,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
