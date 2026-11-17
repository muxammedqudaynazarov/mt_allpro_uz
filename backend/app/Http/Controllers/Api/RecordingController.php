<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecordingResource;
use App\Models\Lesson;
use App\Models\Recording;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/** Darslarning video yozuvlari (serverdagi nusxa). */
class RecordingController extends Controller
{
    /** Bitta darsning yozuvlari: darsni ko'ra oladigan har kim (o'qituvchi, O'quv bo'limi, Administrator). */
    public function lesson(Lesson $lesson): AnonymousResourceCollection
    {
        Gate::authorize('view', $lesson);

        return RecordingResource::collection($lesson->recordings()->where('status', '!=', Recording::SKIPPED)->orderBy('recording_start')->orderBy('id')->get());
    }

    /** Barcha yozuvlar va xotira holati (O'quv bo'limi, Administrator). */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'in:pending,downloaded,failed']]);
        $items = Recording::query()
            ->where('status', '!=', Recording::SKIPPED)
            ->when($data['status'] ?? null, fn ($q, $status) => $status === 'pending' ? $q->whereIn('status', [Recording::PENDING]) : $q->where('status', $status))
            ->with(['lesson.group', 'lesson.teacher'])
            ->latest('id')
            ->paginate(30);

        return RecordingResource::collection($items)->additional(['meta' => ['storage' => self::storage()]])->response();
    }

    /**
     * Faylni berish. Range so'rovlari qo'llab-quvvatlanadi — video brauzerda istalgan joyidan
     * ijro etiladi. ?download=1 — yuklab olish.
     */
    public function file(Request $request, Recording $recording): BinaryFileResponse
    {
        if ($recording->lesson) {
            Gate::authorize('view', $recording->lesson);
        } else {
            abort_unless($request->user()->isSuperAdmin(), 403);
        }
        $path = $recording->absolutePath();
        abort_unless($recording->status === Recording::DOWNLOADED && $path && is_file($path), 404, __('Fayl serverda topilmadi.'));

        $response = new BinaryFileResponse($path, 200, [
            'Content-Type' => $recording->mimeType(),
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $name = basename($recording->path);
        $response->setContentDisposition(
            $request->boolean('download') ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE,
            $name,
            Str::ascii($name),
        );

        return $response;
    }

    /** Xato bilan qolgan faylni qayta navbatga qo'yish. */
    public function retry(Recording $recording): RecordingResource
    {
        abort_unless($recording->status === Recording::FAILED, 422, __('Faqat xato bilan qolgan yozuvni qayta yuklash mumkin.'));
        $recording->forceFill(['status' => Recording::PENDING, 'attempts' => 0, 'error' => null])->save();
        Audit::log('recording.retry', 'Video yozuv qayta navbatga qoʻyildi', $recording->lesson);

        return new RecordingResource($recording);
    }

    /** @return array<string, int|string|null> */
    public static function storage(): array
    {
        $root = (string) config('recordings.path');
        $free = is_dir($root) ? @disk_free_space($root) : @disk_free_space(dirname($root));

        return [
            'path' => $root,
            'used_bytes' => (int) Recording::query()->where('status', Recording::DOWNLOADED)->sum('size'),
            'files' => Recording::query()->where('status', Recording::DOWNLOADED)->count(),
            'pending' => Recording::query()->where('status', Recording::PENDING)->count(),
            'failed' => Recording::query()->where('status', Recording::FAILED)->count(),
            'zoom_bytes' => (int) Recording::query()->whereNull('zoom_deleted_at')->sum('size'),
            'free_bytes' => $free === false ? null : (int) $free,
            'min_free_gb' => (int) config('recordings.min_free_gb'),
        ];
    }
}
