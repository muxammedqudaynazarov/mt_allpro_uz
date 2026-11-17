<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\Semester;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * O'quv yillari (har birida Kuzgi va Bahorgi semestr). Administrator yaratadi,
 * o'zgartiradi va holatini boshqaradi; O'quv bo'limi faqat ko'radi.
 */
class AcademicYearController extends Controller
{
    public function index(): JsonResponse
    {
        $years = AcademicYear::query()->with(['semesters' => fn ($q) => $q->withCount('lessons')])->orderByDesc('name')->get();
        $current = Semester::current();

        return response()->json(['data' => $years->map(fn (AcademicYear $year) => $this->present($year, $current))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $year = DB::transaction(function () use ($data) {
            $year = AcademicYear::create(['name' => $data['name'], 'status' => $data['status'] ?? AcademicYear::PLANNED]);
            $this->saveSemesters($year, $data);
            $this->applyStatus($year);

            return $year;
        });
        Audit::log('academic_year.created', 'Oʻquv yili qoʻshildi: :name', $year, replace: ['name' => $year->name]);

        return response()->json(['data' => $this->present($year->load(['semesters' => fn ($q) => $q->withCount('lessons')]))], 201);
    }

    public function update(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $data = $this->validated($request, $academicYear);
        DB::transaction(function () use ($academicYear, $data) {
            $academicYear->update(['name' => $data['name'], 'status' => $data['status'] ?? $academicYear->status]);
            $this->saveSemesters($academicYear, $data);
            $this->applyStatus($academicYear);
        });
        Audit::log('academic_year.updated', 'Oʻquv yili oʻzgartirildi: :name', $academicYear, replace: ['name' => $academicYear->name]);

        return response()->json(['data' => $this->present($academicYear->load(['semesters' => fn ($q) => $q->withCount('lessons')]))]);
    }

    /** Holatni o'zgartirish: joriy qilinsa, avvalgi joriy yil arxivga o'tadi. */
    public function status(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(AcademicYear::STATUSES)]]);
        DB::transaction(function () use ($academicYear, $data) {
            $academicYear->update(['status' => $data['status']]);
            $this->applyStatus($academicYear);
        });
        Audit::log('academic_year.status', 'Oʻquv yili holati: :name — :status', $academicYear, replace: ['name' => $academicYear->name, 'status' => $data['status']]);

        return response()->json(['data' => $this->present($academicYear->load(['semesters' => fn ($q) => $q->withCount('lessons')]))]);
    }

    public function destroy(AcademicYear $academicYear): Response
    {
        if (Lesson::query()->whereIn('semester_id', $academicYear->semesters()->pluck('id'))->exists()) {
            throw ValidationException::withMessages(['year' => __('Bu oʻquv yilida darslar bor — oʻchirish oʻrniga arxivlang.')]);
        }
        $academicYear->delete();
        Audit::log('academic_year.deleted', 'Oʻquv yili oʻchirildi: :name', $academicYear, replace: ['name' => $academicYear->name]);

        return response()->noContent();
    }

    private function applyStatus(AcademicYear $year): void
    {
        if ($year->status === AcademicYear::ACTIVE) {
            AcademicYear::query()->whereKeyNot($year->id)->where('status', AcademicYear::ACTIVE)->update(['status' => AcademicYear::ARCHIVED]);
        }
    }

    private function saveSemesters(AcademicYear $year, array $data): void
    {
        foreach ([Semester::AUTUMN, Semester::SPRING] as $type) {
            $semester = Semester::firstOrNew(['academic_year_id' => $year->id, 'type' => $type]);
            $semester->fill([
                'name' => $type === Semester::AUTUMN ? 'Kuzgi semestr' : 'Bahorgi semestr',
                'starts_on' => $data[$type]['starts_on'],
                'ends_on' => $data[$type]['ends_on'],
            ])->save();
            $semester->reassignLessons();
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?AcademicYear $year = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:32', 'regex:/^\d{4}\s*[–-]\s*\d{4}$/u', Rule::unique('academic_years', 'name')->ignore($year?->id)],
            'status' => ['nullable', Rule::in(AcademicYear::STATUSES)],
            'autumn.starts_on' => ['required', 'date_format:Y-m-d'],
            'autumn.ends_on' => ['required', 'date_format:Y-m-d', 'after:autumn.starts_on'],
            'spring.starts_on' => ['required', 'date_format:Y-m-d', 'after:autumn.ends_on'],
            'spring.ends_on' => ['required', 'date_format:Y-m-d', 'after:spring.starts_on'],
        ], [
            'name.regex' => __('Oʻquv yili nomi 2026–2027 koʻrinishida boʻlsin.'),
            'name.unique' => __('Bu oʻquv yili allaqachon bor.'),
            'autumn.ends_on.after' => __('Tugash sanasi boshlanishidan keyin boʻlishi kerak.'),
            'spring.starts_on.after' => __('Bahorgi semestr kuzgi semestrdan keyin boshlanishi kerak.'),
            'spring.ends_on.after' => __('Tugash sanasi boshlanishidan keyin boʻlishi kerak.'),
        ]);
        $data['name'] = preg_replace('/\s*[–-]\s*/u', '–', trim($data['name']));

        $own = $year?->semesters()->pluck('id') ?? collect();
        foreach ([Semester::AUTUMN, Semester::SPRING] as $type) {
            $overlap = Semester::query()
                ->overlapping(Carbon::parse($data[$type]['starts_on']), Carbon::parse($data[$type]['ends_on']))
                ->whereNotIn('id', $own)
                ->with('academicYear')
                ->first();
            if ($overlap) {
                throw ValidationException::withMessages([$type.'.starts_on' => __('Sanalar boshqa semestr (:name) bilan kesishadi.', ['name' => $overlap->fullLabel()])]);
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function present(AcademicYear $year, ?Semester $current = null): array
    {
        $current ??= Semester::current();

        return [
            'id' => $year->id,
            'name' => $year->name,
            'status' => $year->status,
            'semesters' => $year->semesters->map(fn (Semester $semester) => [
                'id' => $semester->id,
                'type' => $semester->type,
                'name' => $semester->label(),
                'starts_on' => $semester->starts_on->toDateString(),
                'ends_on' => $semester->ends_on->toDateString(),
                'lessons_count' => (int) ($semester->lessons_count ?? 0),
                'current' => $current?->is($semester) ?? false,
            ])->values(),
        ];
    }
}
