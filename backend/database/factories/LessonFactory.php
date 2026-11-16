<?php

namespace Database\Factories;

use App\Enums\LessonStatus;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lesson> */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        $start = now()->subMinutes(10)->startOfMinute();

        return [
            'user_id' => User::factory(),
            'group_id' => Group::factory(),
            'zoom_account_id' => fn (array $attributes) => Group::find($attributes['group_id'])->zoom_account_id,
            'zoom_host_email' => fn (array $attributes) => Group::find($attributes['group_id'])->zoom_user_email,
            'zoom_meeting_id' => (string) fake()->unique()->numberBetween(10_000_000_000, 99_999_999_999),
            'topic' => fake()->sentence(3),
            'join_url' => 'https://zoom.us/j/123?pwd=abc',
            'start_url' => 'https://zoom.us/s/123?zak=secret',
            'passcode' => '123456',
            'status' => LessonStatus::Live,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(80),
        ];
    }

    public function finished(): static
    {
        return $this->state(fn () => [
            'status' => LessonStatus::Finished,
            'starts_at' => now()->subDay()->setTime(14, 30),
            'ends_at' => now()->subDay()->setTime(15, 50),
            'ended_at' => now()->subDay()->setTime(15, 50),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => LessonStatus::Scheduled,
            'starts_at' => now()->addHours(2)->startOfMinute(),
            'ends_at' => now()->addHours(2)->startOfMinute()->addMinutes(80),
        ]);
    }
}
