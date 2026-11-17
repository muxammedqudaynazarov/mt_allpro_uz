<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\Student;
use App\Models\StudentAlias;
use App\Models\User;
use App\Models\ZoomParticipation;
use App\Support\AttendanceCalculator;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bir talabaning bir nechta yozuvini birlashtiradi ("Ali" va "Ali Valiyev").
 * Barcha kalitlar (ismlar, Zoom ID) asosiy yozuvga o'tadi — keyingi darslarda
 * u avtomatik tanib olinadi. Tegishli darslarning davomati qayta hisoblanadi.
 */
class MergeStudents
{
    public function __construct(private readonly AttendanceCalculator $calculator) {}

    /** @param Collection<int, Student> $sources */
    public function handle(Student $target, Collection $sources, User $actor): Student
    {
        $ids = $sources->pluck('id')->reject(fn (int $id) => $id === $target->id)->values();
        if ($ids->isEmpty()) {
            return $target;
        }

        DB::transaction(function () use ($target, $ids) {
            $lessonIds = ZoomParticipation::query()->whereIn('student_id', $ids)->distinct()->pluck('lesson_id');

            StudentAlias::query()->whereIn('student_id', $ids)->update(['student_id' => $target->id]);
            ZoomParticipation::query()->whereIn('student_id', $ids)->update(['student_id' => $target->id]);
            LessonAttendance::query()->whereIn('student_id', $ids)->delete();
            Student::query()->whereIn('id', $ids)->delete();

            Lesson::query()->whereIn('id', $lessonIds)->get()->each(fn (Lesson $lesson) => $this->calculator->rebuild($lesson));
        });

        Audit::log('student.merged', 'Talabalar birlashtirildi: :names → :name', $target, actor: $actor, replace: [
            'names' => $sources->reject(fn (Student $s) => $s->id === $target->id)->pluck('name')->implode(', '),
            'name' => $target->name,
        ]);

        return $target->refresh();
    }
}
