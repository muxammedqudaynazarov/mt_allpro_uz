<?php

namespace App\Http\Controllers\Api;

use App\Actions\MergeStudents;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\ZoomParticipation;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Guruh talabalari va ularning davr bo'yicha davomati. Talabalar Zoom'dagi ismi
 * bo'yicha avtomatik paydo bo'ladi; administrator bir talabaning turli yozuvlarini
 * birlashtiradi, nomini to'g'rilaydi yoki "talaba emas" deb belgilaydi.
 */
class StudentController extends Controller
{
    public function index(Request $request, Group $group): JsonResponse
    {
        Gate::authorize('manageStudents', $group);
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->subDays(29)->startOfDay();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();

        $lessons = Lesson::query()
            ->where('group_id', $group->id)
            ->where('zoom_state', Lesson::ZOOM_ENDED)
            ->whereNotNull('attendance_synced_at')
            ->whereBetween('starts_at', [$from, $to])
            ->get(['id', 'zoom_minutes', 'zoom_started_at', 'zoom_ended_at']);
        $lessonIds = $lessons->pluck('id');
        $heldSeconds = $lessons->sum(fn (Lesson $lesson) => ($lesson->actualMinutes() ?? 0) * 60);
        $inPeriod = fn (Builder $q) => $q->whereIn('lesson_id', $lessonIds);

        $students = Student::query()
            ->where('group_id', $group->id)
            ->withCount(['attendances as lessons_attended' => $inPeriod])
            ->withSum(['attendances as seconds' => $inPeriod], 'seconds')
            ->withAvg(['attendances as avg_late_seconds' => $inPeriod], 'late_seconds')
            ->withMax(['attendances as last_seen_at' => $inPeriod], 'first_joined_at')
            ->get();

        $names = ZoomParticipation::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->select(['student_id', 'name'])
            ->distinct()
            ->get()
            ->groupBy('student_id');

        return response()->json(['data' => [
            'group' => ['id' => $group->id, 'name' => $group->name, 'course' => $group->course],
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'lessons_held' => $lessons->count(),
            'students' => $students
                ->map(fn (Student $student) => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'ignored' => $student->is_ignored,
                    'names' => $names->get($student->id, collect())->pluck('name')->unique()->values()->all(),
                    'lessons_attended' => (int) $student->lessons_attended,
                    'minutes' => (int) round(((int) $student->seconds) / 60),
                    'percent' => $heldSeconds > 0 ? min(100, (int) round(((int) $student->seconds) * 100 / $heldSeconds)) : null,
                    'avg_late_minutes' => $student->avg_late_seconds !== null ? (int) round($student->avg_late_seconds / 60) : null,
                    'last_seen_at' => $student->last_seen_at ? Carbon::parse($student->last_seen_at)->toIso8601String() : null,
                ])
                ->sortBy([['ignored', 'asc'], fn (array $a, array $b) => strcmp(mb_strtolower($a['name']), mb_strtolower($b['name']))])
                ->values(),
        ]]);
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        Gate::authorize('manageStudents', $student->group);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:1', 'max:120'],
            'ignored' => ['sometimes', 'boolean'],
        ]);

        $changes = [];
        if (array_key_exists('name', $data) && trim($data['name']) !== $student->name) {
            $student->name = trim($data['name']);
            $changes[] = 'name';
        }
        if (array_key_exists('ignored', $data) && $data['ignored'] !== $student->is_ignored) {
            $student->is_ignored = $data['ignored'];
            $changes[] = 'ignored';
        }
        if ($changes) {
            $student->save();
            Audit::log('student.updated', 'Talaba maʼlumotlari oʻzgartirildi: :name (:fields)', $student, replace: ['name' => $student->name, 'fields' => implode(', ', $changes)]);
        }

        return response()->json(['data' => ['id' => $student->id, 'name' => $student->name, 'ignored' => $student->is_ignored]]);
    }

    public function merge(Request $request, Group $group, MergeStudents $merge): JsonResponse
    {
        Gate::authorize('manageStudents', $group);
        $inGroup = Rule::exists('students', 'id')->where('group_id', $group->id);
        $data = $request->validate([
            'target_id' => ['required', 'integer', $inGroup],
            'source_ids' => ['required', 'array', 'min:1', 'max:50'],
            'source_ids.*' => ['integer', 'distinct', $inGroup],
        ]);

        $target = Student::findOrFail($data['target_id']);
        $sources = Student::query()->whereIn('id', $data['source_ids'])->where('id', '!=', $target->id)->get();
        abort_if($sources->isEmpty(), 422, __('Birlashtirish uchun kamida ikki talabani tanlang.'));

        $merge->handle($target, $sources, $request->user());

        return response()->json(['data' => ['id' => $target->id, 'name' => $target->name, 'merged' => $sources->count()]]);
    }
}
