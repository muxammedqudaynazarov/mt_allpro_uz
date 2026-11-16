<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Muhim amallarni (kirish, dars yaratish, rol o'zgartirish va h.k.) jurnalga yozadi.
 * Jurnalga yozishdagi xato asosiy amalni to'xtatmasligi kerak.
 */
class Audit
{
    /** @param array<string, mixed> $properties */
    public static function log(string $action, string $description, ?Model $subject = null, array $properties = [], ?User $actor = null): void
    {
        try {
            // Konsol buyruqlarida (artisan) HTTP so'rov yo'q — IP va brauzer yozilmaydi.
            $request = request();
            if ($request->route() === null) {
                $request = null;
            }
            AuditLog::create([
                'user_id' => ($actor ?? $request?->user())?->getKey(),
                'action' => $action,
                'description' => Str::limit($description, 490),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'properties' => $properties ?: null,
                'ip' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
            ]);
        } catch (Throwable $e) {
            Log::warning('Audit log yozilmadi', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
