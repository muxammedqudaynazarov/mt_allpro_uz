<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'name' => "{$last} {$first}",
            'first_name' => $first,
            'last_name' => $last,
            'hemis_id' => (string) fake()->unique()->numberBetween(1000, 999999),
            'roles' => [Role::Teacher->value],
            'status' => UserStatus::Active,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => UserStatus::Pending]);
    }

    public function blocked(): static
    {
        return $this->state(['status' => UserStatus::Blocked]);
    }

    /** O'quv bo'limi xodimi. */
    public function education(): static
    {
        return $this->state(['roles' => [Role::Education->value]]);
    }

    public function superAdmin(): static
    {
        return $this->state(['roles' => [Role::SuperAdmin->value, Role::Teacher->value]]);
    }

    public function withPassword(string $login, string $password = 'secret-password-123'): static
    {
        return $this->state(['login' => $login, 'password' => $password]);
    }
}
