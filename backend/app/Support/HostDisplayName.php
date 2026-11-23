<?php

namespace App\Support;

use App\Exceptions\ExternalServiceException;
use App\Models\Lesson;
use App\Models\Setting;
use App\Models\ZoomHost;
use App\Services\Zoom\ZoomClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Darsni boshlashda Zoom host akkauntining ko'rinadigan ismini (display name) o'qituvchi
 * nomiga o'zgartiradi: "QUDAYNAZAROV M.S.". Bitta host bir vaqtda faqat bitta darsda
 * bo'ladi, shuning uchun ismlar aralashmaydi. Xato darsni boshlashga to'sqinlik qilmaydi —
 * sababi Umumiy sozlamalarda ko'rsatiladi (masalan, Zoom ilovasida ruxsat yo'q).
 */
final class HostDisplayName
{
    public const ERROR_CACHE = 'zoom:rename-host:error';

    public function __construct(private readonly ZoomClient $zoom) {}

    public static function enabled(): bool
    {
        return (bool) (((array) Setting::get('zoom', []))['rename_host'] ?? true);
    }

    public static function lastError(): ?string
    {
        return Cache::get(self::ERROR_CACHE);
    }

    public function apply(Lesson $lesson): void
    {
        if (! self::enabled() || ! $lesson->zoomAccount || ! $lesson->zoom_host_email || ! $lesson->teacher) {
            return;
        }
        $name = $lesson->teacher->zoomName();
        if ($name === '') {
            return;
        }
        $host = ZoomHost::query()->where('zoom_account_id', $lesson->zoom_account_id)->where('email', mb_strtolower($lesson->zoom_host_email))->first();
        if ($host && $host->renamed_to === $name) {
            return;
        }

        try {
            $this->zoom->setDisplayName($lesson->zoomAccount, $host?->zoom_user_id ?: $lesson->zoom_host_email, $name);
        } catch (ExternalServiceException $e) {
            Cache::put(self::ERROR_CACHE, mb_substr($e->getMessage(), 0, 300), now()->addDays(2));
            Log::warning('Zoom host ismini o‘zgartirib bo‘lmadi', ['host' => $lesson->zoom_host_email, 'error' => $e->getMessage()]);

            return;
        }

        Cache::forget(self::ERROR_CACHE);
        $host?->forceFill(['renamed_to' => $name, 'renamed_at' => now()])->save();
    }
}
