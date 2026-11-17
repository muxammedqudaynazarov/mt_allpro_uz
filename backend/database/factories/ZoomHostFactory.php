<?php

namespace Database\Factories;

use App\Models\ZoomAccount;
use App\Models\ZoomHost;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ZoomHost> */
class ZoomHostFactory extends Factory
{
    protected $model = ZoomHost::class;

    public function definition(): array
    {
        $email = 'zoom_'.fake()->unique()->numberBetween(10000, 99999).'@karsu.uz';

        return [
            'zoom_account_id' => ZoomAccount::factory(),
            'zoom_user_id' => fake()->uuid(),
            'email' => $email,
            'name' => $email,
            'type' => ZoomHost::LICENSED,
            'is_active' => true,
        ];
    }
}
