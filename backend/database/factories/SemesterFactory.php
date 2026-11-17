<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Semester> */
class SemesterFactory extends Factory
{
    protected $model = Semester::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => fn () => AcademicYear::query()->firstOrCreate(['name' => '2026–2027'], ['status' => AcademicYear::ACTIVE])->id,
            'type' => Semester::AUTUMN,
            'name' => 'Kuzgi semestr',
            'starts_on' => today()->subMonths(2)->toDateString(),
            'ends_on' => today()->addMonths(2)->toDateString(),
        ];
    }
}
