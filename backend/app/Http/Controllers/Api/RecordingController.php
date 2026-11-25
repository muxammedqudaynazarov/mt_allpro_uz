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
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/** Darslarning video yozuvlari (serverdagi nusxa). */
class RecordingController extends Controller
{
    /** Bitta darsning yozuvlari: darsni ko'ra oladigan har kim (o'qituvchi, O'quv bo'limi, Administrator). */
    public function lesson(Lesson $lesson): AnonymousResourceCollection
    {
        Gate::authorize('view', $lesson);

        return RecordingResource::collection($lesson->recordings()->visible()->orderBy('recording_start')->orderBy('id')->get());
    }

    /**
     * Yozuvlar ro'yxati: o'qituvchi — o'z darslariniki, O'quv bo'limi va Administrator — hammasi
     * (ular uchun server xotirasi holati ham).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'in:pending,downloaded,failed,unlinked']]);
        $user = $request->user();
        // "Video yozuvlar" sahifasi: faqat video va audio (chat va matnli fayllar dars sahifasida qoladi).
        // Darsga tegishli bo'lmagan uchrashuvlar yozuvlarini faqat Administrator ko'radi (va o'chiradi).
        $items = Recording::query()
            ->media()
            ->visible()
            ->when(! $user->canViewAll(), fn ($q) => $q->whereHas('lesson', fn ($lesson) => $lesson->where('user_id', $user->id)))
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->whereNotNull('lesson_id'))
            ->when($data['status'] ?? null, fn ($q, $status) => $status === 'unlinked' ? $q->whereNull('lesson_id') : $q->where('status', $status))
            ->with(['lesson.group', 'lesson.groups', 'lesson.teacher'])
            ->latest('id')
            ->paginate(30);

        return RecordingResource::collection($items)->additional(['meta' => [
            'storage' => $user->canViewAll() ? self::storage() : null,
            'unlinked' => $user->isSuperAdmin() ? Recording::query()->media()->visible()->whereNull('lesson_id')->count() : null,
        ]])->response();
    }

    /**
     * Yozuvni serverdan o'chirish (Administrator): masalan, tizim darsiga tegishli bo'lmagan uchrashuv
     * videosi. Zoom bulutiga tegilmaydi. Uchrashuv nusxasida video/audio qolmasa — uning chat va
     * matnli fayllari ham o'chiriladi.
     */
    public function destroy(Recording $recording): JsonResponse
    {
        abort_if(in_array($recording->status, [Recording::SKIPPED, Recording::DELETED], true), 404, __('Yozuv topilmadi.'));

        $files = collect([$recording]);
        $rest = Recording::query()->where('zoom_meeting_uuid', $recording->zoom_meeting_uuid)->whereKeyNot($recording->id)->visible()->get();
        if (! $rest->contains(fn (Recording $file) => in_array($file->file_type, Recording::MEDIA_TYPES, true))) {
            $files = $files->merge($rest);
        }
        $size = $files->where('status', Recording::DOWNLOADED)->sum('size');
        $files->each(fn (Recording $file) => $file->removeFromServer());

        Audit::log('recording.deleted', $recording->lesson ? 'Video yozuv serverdan oʻchirildi: :size MB' : 'Darsga tegishli boʻlmagan video yozuv serverdan oʻchirildi: :size MB', $recording->lesson, [
            'recording_ids' => $files->pluck('id')->all(),
            'zoom_meeting_id' => $recording->zoom_meeting_id,
            'uuid' => $recording->zoom_meeting_uuid,
        ], replace: ['size' => (string) round($size / 1024 ** 2, 1)]);

        return response()->json(['data' => ['deleted' => $files->count()]]);
    }

    /**
     * Faylni berish. Range so'rovlari qo'llab-quvvatlanadi — video brauzerda istalgan joyidan
     * ijro etiladi. ?download=1 — yuklab olish.
     */
    /** Nazorat fayli nomi (yozuvlar papkasida) — app:check nginx sozlamasini shu bilan tekshiradi. */
    public const ACCEL_CHECK_FILE = 'accel-check.txt';

    /**
     * nginx'ning ichki /_zoom_recordings/ manzili sozlanganmi: to'g'ri bo'lsa javob "ok",
     * aks holda nginx sayt sahifasini (index.html) qaytaradi — videolar o'rniga ham shu beriladi.
     */
    public function accelCheck(): Response
    {
        $accel = rtrim((string) config('recordings.accel_prefix'), '/');
        abort_if($accel === '', 404);
        $root = rtrim((string) config('recordings.path'), '/');
        if (! is_file($root.'/'.self::ACCEL_CHECK_FILE)) {
            @mkdir($root, 0750, true);
            @file_put_contents($root.'/'.self::ACCEL_CHECK_FILE, 'ok');
        }

        return new Response('', 200, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-store',
            'X-Accel-Redirect' => $accel.'/'.self::ACCEL_CHECK_FILE,
        ]);
    }

    public function file(Request $request, Recording $recording): BinaryFileResponse|Response
    {
        if ($recording->lesson) {
            Gate::authorize('view', $recording->lesson);
        } else {
            abort_unless($request->user()->isSuperAdmin(), 403);
        }
        $path = $recording->absolutePath();
        abort_unless($recording->status === Recording::DOWNLOADED && $path && is_file($path), 404, __('Fayl serverda topilmadi.'));

        $name = basename($recording->path);
        $disposition = HeaderUtils::makeDisposition(
            $request->boolean('download') ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE,
            $name,
            Str::ascii($name),
        );
        $headers = [
            'Content-Type' => $recording->mimeType(),
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];

        // nginx faylni o'zi beradi (Range ham nginx'da) — PHP jarayoni darhol bo'shaydi.
        $accel = rtrim((string) config('recordings.accel_prefix'), '/');
        if ($accel !== '') {
            $location = $accel.'/'.implode('/', array_map('rawurlencode', explode('/', $recording->path)));

            return new Response('', 200, $headers + ['X-Accel-Redirect' => $location]);
        }

        return new BinaryFileResponse($path, 200, $headers);
    }

    /** Xato bilan qolgan faylni qayta navbatga qo'yish. */
    public function retry(Recording $recording): RecordingResource
    {
        abort_unless($recording->status === Recording::FAILED && $recording->lesson_id, 422, __('Faqat xato bilan qolgan yozuvni qayta yuklash mumkin.'));
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
            // Navbatda / xato — ro'yxatdagi kabi faqat video va audio.
            'pending' => Recording::query()->media()->where('status', Recording::PENDING)->count(),
            'failed' => Recording::query()->media()->where('status', Recording::FAILED)->count(),
            'zoom_bytes' => (int) Recording::query()->whereNotNull('lesson_id')->whereNull('zoom_deleted_at')->sum('size'),
            'free_bytes' => $free === false ? null : (int) $free,
            'min_free_gb' => (int) config('recordings.min_free_gb'),
        ];
    }
}
