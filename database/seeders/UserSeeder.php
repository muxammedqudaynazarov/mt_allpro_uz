<?php

namespace Database\Seeders;

use App\Models\Time;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $times = [
            1300 => '13:00',
            1430 => '14:30',
            1600 => '16:00',
            1730 => '17:30',
            1900 => '19:00',
        ];
        foreach ($times as $time => $value) {
            Time::create([
                'name' => $value,
                'code' => $time
            ]);
        }
        $users = [
            [
                'name' => 'QUDAYNAZAROV MUXAMMED',
                'username' => 'muxammed_qudaynazarov',
                'hemis_id' => 1568,
                'telegram_id' => 310857127,
                'current_role' => 'admin',
                'roles_list' => json_encode(['super_admin', 'admin', 'teacher']),
                'admin_id' => null,
                'phone' => '998999593938',
                'password' => Hash::make('password'),
            ]
        ];
        foreach ($users as $user) {
            $user_cr = User::create($user);
            $user_cr->assignRole('admin');
        }
    }
}
