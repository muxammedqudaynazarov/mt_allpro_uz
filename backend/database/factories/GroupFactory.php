<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\ZoomAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Group> */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        $course = fake()->numberBetween(1, 4);

        return [
            'name' => $course.'-'.fake()->randomElement(['A', 'B', 'C']).fake()->numberBetween(1, 3).' '.fake()->randomElement(['Iqtisodiyot', 'Yurisprudensiya', 'Kompyuter ilmlari']),
            'hemis_id' => (string) fake()->unique()->numberBetween(1000, 99999),
            'course' => $course,
            'zoom_account_id' => ZoomAccount::factory(),
            'zoom_user_email' => 'zoom_'.fake()->unique()->numberBetween(1, 9999).'@karsu.uz',
            'is_active' => true,
        ];
    }
}
