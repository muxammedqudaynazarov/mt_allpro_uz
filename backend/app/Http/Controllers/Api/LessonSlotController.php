<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonSlotResource;
use App\Models\LessonSeries;
use App\Models\LessonSlot;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class LessonSlotController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return LessonSlotResource::collection(LessonSlot::orderBy('sort')->orderBy('starts_at')->get());
    }

    public function store(Request $request): LessonSlotResource
    {
        $slot = LessonSlot::create($this->validated($request));
        Audit::log('slot.created', 'Dars vaqti qoʻshildi: :name (:time)', $slot, replace: ['name' => $slot->name, 'time' => $slot->starts_at]);

        return new LessonSlotResource($slot);
    }

    public function update(Request $request, LessonSlot $lessonSlot): LessonSlotResource
    {
        $lessonSlot->update($this->validated($request));
        Audit::log('slot.updated', 'Dars vaqti tahrirlandi: :name', $lessonSlot, replace: ['name' => $lessonSlot->name]);

        return new LessonSlotResource($lessonSlot);
    }

    public function destroy(LessonSlot $lessonSlot): Response
    {
        if (LessonSeries::query()->where('lesson_slot_id', $lessonSlot->id)->exists()) {
            throw ValidationException::withMessages(['slot' => __('Bu juftlikda haftalik darslar bor. Oʻchirish oʻrniga juftlikni nofaol qiling.')]);
        }
        $lessonSlot->delete();
        Audit::log('slot.deleted', 'Dars vaqti oʻchirildi: :name', $lessonSlot, replace: ['name' => $lessonSlot->name]);

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'starts_at' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'between:10,240'],
            'sort' => ['sometimes', 'integer', 'between:0,1000'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'starts_at.date_format' => __('Vaqtni SS:DD koʻrinishida kiriting, masalan 14:30.'),
            'duration_minutes.between' => __('Davomiylik 10 dan 240 daqiqagacha boʻlishi kerak.'),
        ]);
    }
}
