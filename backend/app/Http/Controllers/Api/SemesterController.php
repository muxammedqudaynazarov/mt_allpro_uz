<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Support\Audit;
use App\Support\SemesterReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** O'quv semestrlari: ko'rish — O'quv bo'limi va Administrator, o'zgartirish — Administrator. */
class SemesterController extends Controller
{
    public function index(): JsonResponse
    {
        $current = Semester::current();

        return response()->json(['data' => Semester::query()->orderByDesc('starts_on')->get()->map(fn (Semester $semester) => [
            ...SemesterReport::semester($semester),
            'current' => $current?->is($semester) ?? false,
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $semester = Semester::create($this->validated($request));
        Audit::log('semester.created', 'Semestr qoʻshildi: :name', $semester, replace: ['name' => $semester->name]);

        return response()->json(['data' => SemesterReport::semester($semester)], 201);
    }

    public function update(Request $request, Semester $semester): JsonResponse
    {
        $semester->update($this->validated($request, $semester));
        Audit::log('semester.updated', 'Semestr oʻzgartirildi: :name', $semester, $semester->getChanges(), replace: ['name' => $semester->name]);

        return response()->json(['data' => SemesterReport::semester($semester)]);
    }

    public function destroy(Semester $semester): Response
    {
        $semester->delete();
        Audit::log('semester.deleted', 'Semestr oʻchirildi: :name', $semester, replace: ['name' => $semester->name]);

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Semester $semester = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
        ], [
            'name.required' => __('Semestr nomini kiriting.'),
            'ends_on.after' => __('Tugash sanasi boshlanishidan keyin boʻlishi kerak.'),
        ]);

        $overlap = Semester::query()->overlapping(Carbon::parse($data['starts_on']), Carbon::parse($data['ends_on']))
            ->when($semester, fn ($q) => $q->whereKeyNot($semester->id))->first();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_on' => __('Sanalar boshqa semestr (:name) bilan kesishadi.', ['name' => $overlap->name])]);
        }

        return $data;
    }
}
