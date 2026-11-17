<?php

namespace Database\Seeders;

use App\Enums\LessonStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonSlot;
use App\Models\User;
use App\Models\ZoomAccount;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Faqat ishlab chiqish uchun namunaviy ma'lumotlar. Production'da ishlamaydi. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder production muhitida ishlatilmaydi.');
        }

        $this->call(DatabaseSeeder::class);

        $super = User::updateOrCreate(['login' => 'admin'], [
            'name' => 'Qudaynazarov Muxammed', 'first_name' => 'Muxammed', 'last_name' => 'Qudaynazarov',
            'password' => 'demo-password-2026', 'status' => UserStatus::Active,
            'roles' => [Role::SuperAdmin->value, Role::Teacher->value],
            'hemis_id' => '1568',
        ]);
        $admin = User::updateOrCreate(['hemis_id' => '2001'], [
            'name' => 'Aytmuratova Gulnara Baxitovna', 'first_name' => 'Gulnara', 'last_name' => 'Aytmuratova',
            'status' => UserStatus::Active, 'roles' => [Role::Education->value],
        ]);

        $teachers = collect([
            ['Jumabaev Rustem Kenesbaevich', 'Rustem', 'Jumabaev', 'Matematika kafedrasi'],
            ['Seytmuratova Aziza Polatovna', 'Aziza', 'Seytmuratova', 'Huquq kafedrasi'],
            ['Kurbaniyazov Timur Asanovich', 'Timur', 'Kurbaniyazov', 'Axborot texnologiyalari kafedrasi'],
            ['Nurlibaeva Dilfuza Ermekovna', 'Dilfuza', 'Nurlibaeva', 'Iqtisodiyot kafedrasi'],
        ])->map(fn ($t, $i) => User::updateOrCreate(['hemis_id' => (string) (3001 + $i)], [
            'name' => $t[0], 'first_name' => $t[1], 'last_name' => $t[2],
            'status' => UserStatus::Active, 'roles' => [Role::Teacher->value],
            'telegram_chat_id' => $i % 2 ? (string) (900000 + $i) : null,
        ]));

        User::updateOrCreate(['hemis_id' => '4001'], [
            'name' => 'Abdiraxmanov Bekzat Oralovich', 'first_name' => 'Bekzat', 'last_name' => 'Abdiraxmanov', 'status' => UserStatus::Pending, 'roles' => [Role::Teacher->value],
        ]);
        User::updateOrCreate(['hemis_id' => '4002'], [
            'name' => 'Ismailova Zuhra Maratovna', 'first_name' => 'Zuhra', 'last_name' => 'Ismailova', 'status' => UserStatus::Pending, 'roles' => [Role::Teacher->value],
        ]);

        $account = ZoomAccount::firstOrCreate(['account_id' => 'demo-account'], [
            'name' => 'Asosiy akkaunt', 'client_id' => 'demo-client', 'client_secret' => 'demo-secret', 'last_checked_at' => now(),
        ]);
        $second = ZoomAccount::firstOrCreate(['account_id' => 'demo-account-2'], [
            'name' => 'Yuridik fakultet', 'client_id' => 'demo-client-2', 'client_secret' => 'demo-secret',
        ]);

        $groups = collect([
            ['3-B1 Kompyuter ilmlari va dasturlash texnologiyalari', 3, 'zoom_6@karsu.uz', $account],
            ['3-A1 Ekonomika', 3, 'zoom_1@karsu.uz', $account],
            ['3-B1 Iqtisodiyot', 3, 'zoom_2@karsu.uz', $account],
            ['3-A1 Yurisprudensiya', 3, 'zoom_9@karsu.uz', $second],
            ['2-B1 Iqtisodiyot', 2, 'zoom_3@karsu.uz', $account],
            ['2-A1 Kompyuter ilimleri ham programmalastırıw', 2, 'zoom_4@karsu.uz', $account],
            ['2-C1 Pedagogika', 2, 'zoom_5@karsu.uz', $second],
            ['1-A1 Yurisprudensiya', 1, 'zoom_7@karsu.uz', $second],
            ['1-B1 Iqtisodiyot', 1, 'zoom_8@karsu.uz', $account],
        ])->map(fn ($g, $i) => Group::updateOrCreate(['hemis_id' => (string) (1300 + $i)], [
            'name' => $g[0], 'course' => $g[1], 'zoom_user_email' => $g[2], 'zoom_account_id' => $g[3]->id,
            'is_active' => $i !== 8,
        ]));

        if (Lesson::exists()) {
            return;
        }

        $slots = LessonSlot::orderBy('sort')->get();
        $everyone = $teachers->push($super);

        // O'tgan 30 kunlik tarix
        for ($day = 30; $day >= 1; $day--) {
            $date = now()->subDays($day);
            if ($date->isSunday()) {
                continue;
            }
            foreach ($slots->random(min(3, $slots->count())) as $slot) {
                $group = $groups->random();
                $start = $slot->startOn($date);
                Lesson::create([
                    'user_id' => $everyone->random()->id, 'group_id' => $group->id, 'lesson_slot_id' => $slot->id,
                    'zoom_account_id' => $group->zoom_account_id, 'zoom_host_email' => $group->zoom_user_email,
                    'zoom_meeting_id' => (string) random_int(80_000_000_000, 89_999_999_999), 'topic' => fake()->randomElement([
                        'Makroiqtisodiyot asoslari', 'Fuqarolik huquqi', 'Maʼlumotlar tuzilmasi', 'Chiziqli algebra', 'Pedagogik mahorat',
                    ]),
                    'status' => LessonStatus::Finished, 'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes($slot->duration_minutes),
                    'ended_at' => $start->copy()->addMinutes($slot->duration_minutes),
                ]);
            }
        }

        // Hozir efirdagi va bugun rejalashtirilgan darslar
        $liveStart = now()->subMinutes(35)->startOfMinute();
        foreach ([[$super, $groups[0], 'Veb dasturlash: REST API'], [$teachers[0], $groups[3], 'Konstitutsiyaviy huquq']] as [$teacher, $group, $topic]) {
            Lesson::create([
                'user_id' => $teacher->id, 'group_id' => $group->id, 'zoom_account_id' => $group->zoom_account_id,
                'zoom_host_email' => $group->zoom_user_email, 'zoom_meeting_id' => (string) random_int(80_000_000_000, 89_999_999_999),
                'topic' => $topic, 'join_url' => 'https://us06web.zoom.us/j/81234567890?pwd=demo', 'start_url' => 'https://us06web.zoom.us/s/81234567890?zak=demo',
                'passcode' => '482913', 'status' => LessonStatus::Live, 'starts_at' => $liveStart, 'ends_at' => $liveStart->copy()->addMinutes(80),
            ]);
        }
        $later = now()->addHours(2)->startOfHour();
        Lesson::create([
            'user_id' => $super->id, 'group_id' => $groups[4]->id, 'zoom_account_id' => $groups[4]->zoom_account_id,
            'zoom_host_email' => $groups[4]->zoom_user_email, 'zoom_meeting_id' => (string) random_int(80_000_000_000, 89_999_999_999),
            'topic' => 'Moliya bozorlari', 'join_url' => 'https://us06web.zoom.us/j/81234567891?pwd=demo', 'start_url' => 'https://us06web.zoom.us/s/81234567891?zak=demo',
            'passcode' => '120455', 'status' => LessonStatus::Scheduled, 'starts_at' => $later, 'ends_at' => $later->copy()->addMinutes(80),
        ]);
    }
}
