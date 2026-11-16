<?php

namespace Database\Factories;

use App\Models\LessonSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LessonSlot> */
class LessonSlotFactory extends Factory
{
    protected $model = LessonSlot::class;

    public function definition(): array
    {
        return ['name' => '1-para', 'starts_at' => '14:30', 'duration_minutes' => 80, 'sort' => 1, 'is_active' => true];
    }
}
