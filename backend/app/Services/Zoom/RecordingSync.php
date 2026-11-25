<?php

namespace App\Services\Zoom;

use App\Exceptions\ExternalServiceException;
use App\Models\Lesson;
use App\Models\Recording;
use App\Models\ZoomAccount;
use App\Support\Audit;
use App\Support\RecordingSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Zoom bulutidagi video yozuvlarni serverga ko'chirish:
 *  1) ro'yxatga olish — recording.completed webhook'i yoki (webhook bo'lmasa) so'rab turish;
 *  2) yuklab olish — vaqtinchalik faylga, so'ng hajmi Zoom ko'rsatgan hajm bilan solishtiriladi;
 *  3) Zoom'dan o'chirish — uchrashuv nusxasining barcha kerakli fayllari serverda bo'lgachgina.
 */
class RecordingSync
{
    public function __construct(private readonly ZoomClient $zoom) {}

    /**
     * Webhook yoki API'dan kelgan yozuv fayllarini ro'yxatga oladi.
     *
     * @param  array{id?: mixed, uuid?: mixed, recording_files?: array}  $object
     * @return int yangi fayllar soni
     */
    public function register(ZoomAccount $account, array $object, ?string $downloadToken = null): int
    {
        $meetingId = (string) ($object['id'] ?? '');
        $uuid = (string) ($object['uuid'] ?? '');
        if ($meetingId === '' || $uuid === '') {
            return 0;
        }
        $lesson = Lesson::query()->where('zoom_account_id', $account->id)->where('zoom_meeting_id', $meetingId)->latest('starts_at')->first();
        // Tizim darsiga tegishli bo'lmagan uchrashuv (masalan, shu akkaunt bilan o'tkazilgan seminar) — tegilmaydi:
        // yuklab olinmaydi va Zoom bulutidan o'chirilmaydi.
        if (! $lesson) {
            Log::info('Zoom yozuvi tizim darsiga tegishli emas — oʻtkazib yuborildi', ['account' => $account->id, 'meeting_id' => $meetingId]);

            return 0;
        }

        $created = 0;
        foreach ((array) ($object['recording_files'] ?? []) as $file) {
            $fileId = (string) ($file['id'] ?? '');
            $type = strtoupper((string) ($file['file_type'] ?? ''));
            if ($fileId === '' || $type === '' || ($file['status'] ?? 'completed') !== 'completed') {
                continue;
            }
            $recording = Recording::firstOrNew(['zoom_file_id' => $fileId]);
            if (in_array($recording->status, [Recording::DOWNLOADED, Recording::DELETED], true)) {
                continue;
            }
            $isNew = ! $recording->exists;
            $recording->fill([
                'lesson_id' => $lesson?->id,
                'zoom_account_id' => $account->id,
                'zoom_meeting_id' => $meetingId,
                'zoom_meeting_uuid' => $uuid,
                'file_type' => $type,
                'file_extension' => strtolower((string) ($file['file_extension'] ?? '')) ?: null,
                'recording_type' => $file['recording_type'] ?? null,
                'recording_start' => self::time($file['recording_start'] ?? null),
                'recording_end' => self::time($file['recording_end'] ?? null),
                'size' => (int) ($file['file_size'] ?? 0),
                'download_url' => $file['download_url'] ?? null,
                // Zoom 24 soatga beradi; ozroq zaxira bilan.
                ...($downloadToken ? ['download_token' => $downloadToken, 'download_token_expires_at' => now()->addHours(23)] : []),
            ]);
            if ($isNew) {
                $recording->status = RecordingSettings::wanted($type) ? Recording::PENDING : Recording::SKIPPED;
                $created++;
            }
            $recording->save();
        }

        return $created;
    }

    /** Webhook kelmagan darslar uchun: tugagan darslarning yozuvlarini Zoom'dan so'raydi. */
    public function poll(?Carbon $now = null): int
    {
        $now ??= now();
        $found = 0;
        $lessons = Lesson::query()
            ->where('record_cloud', true)
            ->where('zoom_state', Lesson::ZOOM_ENDED)
            ->whereBetween('zoom_ended_at', [$now->copy()->subDays((int) config('recordings.poll_days')), $now->copy()->subMinutes(10)])
            ->whereDoesntHave('recordings')
            ->where(fn ($q) => $q->whereNull('recordings_checked_at')->orWhere('recordings_checked_at', '<', $now->copy()->subMinutes(30)))
            ->with('zoomAccount')
            ->limit(20)
            ->get();

        foreach ($lessons as $lesson) {
            $lesson->forceFill(['recordings_checked_at' => $now])->save();
            if (! $lesson->zoomAccount) {
                continue;
            }
            try {
                $instances = $this->zoom->pastInstances($lesson->zoomAccount, $lesson->zoom_meeting_id) ?: [['uuid' => $lesson->zoom_meeting_id]];
                foreach ($instances as $instance) {
                    $data = $this->zoom->meetingRecordings($lesson->zoomAccount, $instance['uuid']);
                    if ($data) {
                        $found += $this->register($lesson->zoomAccount, ['id' => $lesson->zoom_meeting_id, 'uuid' => $data['uuid'] ?: $instance['uuid'], 'recording_files' => $data['recording_files']]);
                    }
                }
            } catch (ExternalServiceException $e) {
                Log::info('Zoom yozuvlarini tekshirib boʻlmadi', ['lesson' => $lesson->id, 'error' => $e->getMessage()]);
            }
        }

        return $found;
    }

    /** @return array{downloaded: int, failed: int} */
    public function download(int $limit = 3): array
    {
        $done = $failed = 0;
        $root = rtrim((string) config('recordings.path'), '/');
        if (! is_dir($root) && ! @mkdir($root, 0750, true) && ! is_dir($root)) {
            Log::error('Video yozuvlar papkasini yaratib boʻlmadi', ['path' => $root]);

            return ['downloaded' => 0, 'failed' => 0];
        }

        foreach (Recording::query()->due()->with(['zoomAccount', 'lesson.group'])->orderBy('id')->limit($limit)->get() as $recording) {
            $free = @disk_free_space($root);
            $needed = $recording->size + (int) config('recordings.min_free_gb') * 1024 ** 3;
            if ($free !== false && $free < $needed) {
                $recording->forceFill(['error' => __('Serverda joy yetarli emas.')])->save();
                break;
            }
            try {
                $this->downloadOne($recording, $root);
                $done++;
            } catch (\Throwable $e) {
                $failed++;
                $recording->forceFill([
                    'status' => Recording::FAILED,
                    'attempts' => $recording->attempts + 1,
                    'error' => mb_substr($e->getMessage(), 0, 255),
                ])->save();
            }
        }

        return ['downloaded' => $done, 'failed' => $failed];
    }

    private function downloadOne(Recording $recording, string $root): void
    {
        $account = $recording->zoomAccount ?? throw new ExternalServiceException(__('Zoom akkaunt topilmadi.'));
        $url = $recording->download_url;
        if (! $url || $recording->attempts > 0) {
            $url = $this->freshUrl($recording) ?? throw new ExternalServiceException(__('Zoom’da bu yozuv topilmadi (oʻchirilgan boʻlishi mumkin).'));
        }

        $date = ($recording->recording_start ?? $recording->created_at ?? now())->timezone(config('app.timezone'));
        $folder = $date->format('Y/m').'/'.($recording->lesson ? 'lesson-'.$recording->lesson_id : 'meeting-'.$recording->zoom_meeting_id);
        $name = Str::slug(($recording->lesson?->group?->name ?? 'zoom').' '.$date->format('Y-m-d Hi').' '.($recording->recording_type ?? $recording->file_type))
            .'-'.$recording->id.'.'.($recording->file_extension ?: 'bin');
        if (! is_dir($root.'/'.$folder)) {
            mkdir($root.'/'.$folder, 0750, true);
        }
        $part = $root.'/'.$folder.'/.'.$name.'.part';

        $token = $recording->download_token_expires_at?->isFuture() ? $recording->download_token : null;
        $bytes = $this->zoom->downloadRecording($account, $url, $part, $token);
        // Zoom ko'rsatgan hajm bilan solishtiriladi: chala yuklangan fayl saqlanmaydi.
        if ($bytes <= 0 || ($recording->size > 0 && $bytes !== $recording->size)) {
            @unlink($part);
            throw new ExternalServiceException(__('Fayl toʻliq yuklanmadi: :got / :expected bayt.', ['got' => $bytes, 'expected' => $recording->size]));
        }
        rename($part, $root.'/'.$folder.'/'.$name);

        $recording->forceFill([
            'status' => Recording::DOWNLOADED,
            'path' => $folder.'/'.$name,
            'size' => $bytes,
            'downloaded_at' => now(),
            'download_url' => null,
            'download_token' => null,
            'download_token_expires_at' => null,
            'error' => null,
        ])->save();
    }

    private function freshUrl(Recording $recording): ?string
    {
        $data = $this->zoom->meetingRecordings($recording->zoomAccount, $recording->zoom_meeting_uuid);
        foreach ((array) ($data['recording_files'] ?? []) as $file) {
            if ((string) ($file['id'] ?? '') === $recording->zoom_file_id) {
                if (! empty($file['file_size'])) {
                    $recording->size = (int) $file['file_size'];
                }

                return $file['download_url'] ?? null;
            }
        }

        return null;
    }

    /**
     * Uchrashuv nusxasining barcha kerakli fayllari serverda bo'lsa — Zoom bulutidan o'chiradi.
     *
     * @return int o'chirilgan nusxalar soni
     */
    public function purge(): int
    {
        $settings = RecordingSettings::get();
        if (! $settings['delete_from_zoom']) {
            return 0;
        }

        // Faqat tizim darslarining yozuvlari (boshqa uchrashuvlarniki Zoom'da qoladi).
        $uuids = Recording::query()
            ->whereNull('zoom_deleted_at')
            ->whereNotNull('lesson_id')
            ->where('status', Recording::DOWNLOADED)
            ->distinct()
            ->pluck('zoom_meeting_uuid');

        $purged = 0;
        foreach ($uuids as $uuid) {
            $files = Recording::query()->where('zoom_meeting_uuid', $uuid)->with('zoomAccount')->get();
            // Hali yuklanayotgan yoki xato bilan qolgan fayl bo'lsa — kutamiz.
            if ($files->contains(fn (Recording $file) => in_array($file->status, [Recording::PENDING, Recording::FAILED], true))) {
                continue;
            }
            $account = $files->first()->zoomAccount;
            if (! $account) {
                continue;
            }
            try {
                $this->zoom->deleteMeetingRecordings($account, $uuid, $settings['delete_action']);
            } catch (ExternalServiceException $e) {
                Recording::query()->where('zoom_meeting_uuid', $uuid)->update(['error' => mb_substr($e->getMessage(), 0, 255)]);

                continue;
            }
            Recording::query()->where('zoom_meeting_uuid', $uuid)->update(['zoom_deleted_at' => now(), 'download_url' => null]);
            Audit::log('recording.purged', 'Video yozuv Zoom bulutidan oʻchirildi: :size MB', $files->first()->lesson, [
                'uuid' => $uuid, 'action' => $settings['delete_action'],
            ], replace: ['size' => (string) round($files->sum('size') / 1024 ** 2)]);
            $purged++;
        }

        return $purged;
    }

    private static function time(mixed $value): ?Carbon
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
