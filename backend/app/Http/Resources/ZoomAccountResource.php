<?php

namespace App\Http\Resources;

use App\Models\ZoomAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Client Secret hech qachon qaytarilmaydi — faqat saqlangan-saqlanmagani.
 *
 * @mixin ZoomAccount
 */
class ZoomAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'account_id' => $this->account_id,
            'client_id' => $this->client_id,
            'has_secret' => filled($this->getRawOriginal('client_secret')),
            'is_active' => $this->is_active,
            'groups_count' => (int) ($this->groups_count ?? 0),
            'hosts_count' => (int) ($this->hosts_count ?? 0),
            'has_webhook_secret' => filled($this->getRawOriginal('webhook_secret')),
            'webhook_url' => $this->webhookUrl(),
            'webhook_received_at' => $this->webhook_received_at?->toIso8601String(),
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'last_error' => $this->last_error,
        ];
    }
}
