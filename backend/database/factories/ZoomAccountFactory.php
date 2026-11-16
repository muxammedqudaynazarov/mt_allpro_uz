<?php

namespace Database\Factories;

use App\Models\ZoomAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ZoomAccount> */
class ZoomAccountFactory extends Factory
{
    protected $model = ZoomAccount::class;

    public function definition(): array
    {
        return [
            'name' => 'Zoom '.fake()->unique()->word(),
            'account_id' => Str::random(22),
            'client_id' => Str::random(22),
            'client_secret' => Str::random(32),
            'is_active' => true,
        ];
    }
}
