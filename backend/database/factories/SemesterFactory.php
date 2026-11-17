<?php

namespace Database\Factories;

use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Semester> */
class SemesterFactory extends Factory
{
    protected $model = Semester::class;

    public function definition(): array
    {
        return [
            'name' => '2026–2027, 1-semestr',
            'starts_on' => today()->subMonths(2)->toDateString(),
            'ends_on' => today()->addMonths(2)->toDateString(),
        ];
    }
}
