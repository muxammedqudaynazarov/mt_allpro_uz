<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpdateController extends Controller
{
    public function login()
    {
        $groups = [
            [
                'hemis_id' => '1305',
                'telegram_id' => '-1001805265893',
                'name' => '2-B1 - Kompyuter ilmlari va dasturlash texnologiyalari',
                'email' => 'kgu.zoom6@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1369',
                'telegram_id' => '-1001635820223',
                'name' => '2-A1 - Ekonomika',
                'email' => 'kgu.zoom1@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1499',
                'telegram_id' => '-1001787897914',
                'name' => '2-B1 - Iqsodiyot',
                'email' => 'kgu.zoom2@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1509',
                'telegram_id' => '-1001875015691',
                'name' => '2-A1 - Yurisprudenciya',
                'email' => 'kgu.zoom9@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1517',
                'telegram_id' => '-1001571434773',
                'name' => '2-B1 - Yurisprudensiya',
                'email' => 'kgu.berdaq1@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1519',
                'telegram_id' => '-1001882334241',
                'name' => '2-B2 - Kompyuter ilmlari va dasturlash texnologiyalari',
                'email' => 'kgu.zoom7@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1718',
                'telegram_id' => '-1001891592982',
                'name' => '2-C1 - Компьютерные науки и технологии программирования',
                'email' => 'kgu.zoom8@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1833',
                'telegram_id' => '-1001875015691',
                'name' => '2-A1 - Kompyuter ilimleri hám programmalastırıw texnologiyaları',
                'email' => 'kgu.zoom3@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1834',
                'telegram_id' => '-1001819473739',
                'name' => '2-A2 - Kompyuter ilimleri hám programmalastırıw texnologiyaları',
                'email' => 'kgu.zoom4@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1835',
                'telegram_id' => '-1001636907132',
                'name' => '2-A3 - Kompyuter ilimleri hám programmalastırıw texnologiyaları',
                'email' => 'kgu.zoom5@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '2534',
                'telegram_id' => '111',
                'name' => '1-A1 - Kompyuter ilimleri hám programmalastırıw texnologiyaları',
                'email' => 'kgu.zoom3@works.uz',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2535',
                'telegram_id' => '112',
                'name' => '1-A2 - Kompyuter ilimleri hám programmalastırıw texnologiyaları',
                'email' => 'kgu.zoom4@works.uz',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2536',
                'telegram_id' => '113',
                'name' => '1-A3 - Kompyuter ilimleri hám programmalastırıw texnologiyaları',
                'email' => 'kgu.zoom5@works.uz',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2537',
                'telegram_id' => '114',
                'name' => '1-B1 - Kompyuter ilmlari va dasturlash texnologiyalari',
                'email' => 'kgu.zoom6@works.uz',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2538',
                'telegram_id' => '115',
                'name' => '1-B2 - Kompyuter ilmlari va dasturlash texnologiyalari',
                'email' => 'kgu.zoom7@works.uz',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2539',
                'telegram_id' => '116',
                'name' => '1-C1 - Компьютерные науки и технологии программирования',
                'email' => 'kgu.zoom8@works.uz',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2541',
                'telegram_id' => '108',
                'name' => '1-B1 - Yurisprudensiya',
                'email' => 'kgu.berdaq1@works.uz',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2542',
                'telegram_id' => '109',
                'name' => '1-A1 - Ekonomika',
                'email' => 'kgu.zoom1@works.uz',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2543',
                'telegram_id' => '110',
                'name' => '1-B1 - Iqsodiyot',
                'email' => 'kgu.zoom2@works.uz',
                'active' => '1',
                'course' => '1'
            ]
        ];
        foreach ($groups as $group) {

        }
        DB::table('users')->insert([
            'name' => 'Mukhammed Qudaynazarov',
            'username' => 'qudaynazarov',
            'password' => Hash::make('12345678'),
            'telegram_id' => 310857127,
            'hemis_id' => 1568,
            'created_at' => DB::raw('NOW()'),
            'updated_at' => DB::raw('NOW()'),
        ]);
    }
}
