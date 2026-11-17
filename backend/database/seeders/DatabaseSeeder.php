<?php

namespace Database\Seeders;

use App\Models\LessonSlot;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Ishga tushirishda faqat standart dars vaqtlari (juftliklar) qo'shiladi.
     * Administrator: php artisan app:create-admin
     * Sinov ma'lumotlari: php artisan db:seed --class=DemoSeeder (faqat local)
     */
    public function run(): void
    {
        $slots = ['13:00', '14:30', '16:00', '17:30', '19:00'];
        foreach ($slots as $index => $start) {
            LessonSlot::firstOrCreate(['starts_at' => $start], [
                'name' => LessonSlot::defaultName($index + 1),
                'duration_minutes' => 80,
                'sort' => $index + 1,
                'is_active' => true,
            ]);
        }
    }
}
