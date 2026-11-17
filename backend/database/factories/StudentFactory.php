<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'name' => fake()->firstName().' '.fake()->lastName(),
            'is_ignored' => false,
        ];
    }
}
