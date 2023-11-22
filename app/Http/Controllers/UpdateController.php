<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpdateController extends Controller
{

    public function generate_token()
    {
        $array = [
            [
                'config' => [
                    'account_id' => 'iJpqhQ0VS9G8RHe5gnoalg',
                    'client_id' => 'Dv8QWeWSTLidRi78BNYOxQ',
                    'client_secret' => 'uOLL9RQg0w0JV55V6wrze85osV7fghQz',
                ],
                'clients' => [
                    'kgu.berdaq1@works.uz',
                    'kgu.zoom1@works.uz',
                    'kgu.zoom2@works.uz',
                    'kgu.zoom3@works.uz',
                    'kgu.zoom4@works.uz',
                    'kgu.zoom5@works.uz',
                    'kgu.zoom6@works.uz',
                    'kgu.zoom7@works.uz',
                    'kgu.zoom8@works.uz',
                ]
            ],
            [
                'config' => [
                    'account_id' => 'vR5UUeuYS868LJuPFG2aUA',
                    'client_id' => '4A1IbioMSrmmJcUFsdqMVg',
                    'client_secret' => 'NFLkUJp481hgL47c95yBMBTb2Fgy2oOS',
                ],
                'clients' => [
                    'kgu.zoom10@works.uz',
                    'kgu.zoom11@works.uz',
                    'kgu.zoom12@works.uz',
                    'kgu.zoom13@works.uz',
                    'kgu.zoom14@works.uz',
                    'kgu.zoom15@works.uz',
                ]
            ],
            [
                'config' => [
                    'account_id' => 'DJfGBDZNQQO92BtyNZGoGw',
                    'client_id' => 'lkS28U66QgudQhroG_YJBQ',
                    'client_secret' => 'pU8ElEVW5aNS2gGHBs4uisjsG0SsDeRd',
                ],
                'clients' => [
                    'berdaquniversitet1@gmail.com',
                    'berdaquniversitet2@gmail.com',
                    'berdaquniversitet3@gmail.com',
                    'berdaquniversitet4@gmail.com',
                    'berdaquniversitet5@gmail.com',
                ]
            ],
        ];
        foreach ($array as $val) {
            $accountId = $val['config']['account_id'];
            $clientId = $val['config']['client_id'];
            $clientSecret = $val['config']['client_secret'];

            $oauthUrl = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . $accountId;
            try {
                $authHeader = 'Basic ' . base64_encode($clientId . ':' . $clientSecret);
                $ch = curl_init($oauthUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: ' . $authHeader));
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode == 200) {
                    foreach ($val['clients'] as $upd) {
                        DB::table('groups')
                            ->where('email', $upd)
                            ->update([
                                'api' => json_decode($response)->access_token,
                                'updated_at' => DB::raw('NOW()'),
                            ]);
                    }
                } else {
                    $error_no = 'API калитлар билан боүлиқ қандайдир муаммолар мавжуд';
                    break;
                }
            } catch (Exception $e) {
                $error_no = 'API калитларни янгилаб бўлмади';
                break;
            }
        }
    }

    public function teachers()
    {
        $groups = [
            [
                'hemis_id' => '1305',
                'telegram_id' => '-1001805265893',
                'name' => '2-B1 - Компьютер илмлари ва дастурлаш технологиялари',
                'email' => 'kgu.zoom6@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1369',
                'telegram_id' => '-1001635820223',
                'name' => '2-A1 - Экономика',
                'email' => 'kgu.zoom1@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1499',
                'telegram_id' => '-1001787897914',
                'name' => '2-B1 - Иқтисодиёт',
                'email' => 'kgu.zoom2@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1509',
                'telegram_id' => '-1001875015691',
                'name' => '2-A1 - Юриспруденция',
                'email' => 'berdaquniversitet4@gmail.com',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1517',
                'telegram_id' => '-1001571434773',
                'name' => '2-B1 - Юриспруденция',
                'email' => 'kgu.berdaq1@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1519',
                'telegram_id' => '-1001882334241',
                'name' => '2-B2 - Компьютер илмлари ва дастурлаш технологиялари',
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
                'name' => '2-A1 - Компьютер илимлери ҳам программаластырыў технологиялары',
                'email' => 'kgu.zoom3@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1834',
                'telegram_id' => '-1001819473739',
                'name' => '2-A2 - Компьютер илимлери ҳам программаластырыў технологиялары',
                'email' => 'kgu.zoom4@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '1835',
                'telegram_id' => '-1001636907132',
                'name' => '2-A3 - Компьютер илимлери ҳам программаластырыў технологиялары',
                'email' => 'kgu.zoom5@works.uz',
                'active' => '1',
                'course' => '2'
            ],
            [
                'hemis_id' => '2534',
                'telegram_id' => '111',
                'name' => '1-A1 - Компьютер илимлери ҳам программаластырыў технологиялары',
                'email' => 'kgu.zoom11@works.uz',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2535',
                'telegram_id' => '112',
                'name' => '1-A2 - Компьютер илимлери ҳам программаластырыў технологиялары',
                'email' => 'kgu.zoom12@works.uz',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2536',
                'telegram_id' => '113',
                'name' => '1-A3 - Компьютер илимлери ҳам программаластырыў технологиялары',
                'email' => 'kgu.zoom13@works.uz',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2537',
                'telegram_id' => '114',
                'name' => '1-B1 - Компьютер илмлари ва дастурлаш технологиялари',
                'email' => 'kgu.zoom14@works.uz',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2538',
                'telegram_id' => '115',
                'name' => '1-B2 - Компьютер илмлари ва дастурлаш технологиялари',
                'email' => 'kgu.zoom15@works.uz',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2539',
                'telegram_id' => '116',
                'name' => '1-C1 - Компьютерные науки и технологии программирования',
                'email' => 'berdaquniversitet1@gmail.com',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2541',
                'telegram_id' => '108',
                'name' => '1-B1 - Юриспруденция',
                'email' => 'berdaquniversitet2@gmail.com',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2542',
                'telegram_id' => '109',
                'name' => '1-A1 - Экономика',
                'email' => 'berdaquniversitet3@gmail.com',
                'active' => '1',
                'course' => '1'
            ],
            [
                'hemis_id' => '2543',
                'telegram_id' => '110',
                'name' => '1-B1 - Иқтисодиёт',
                'email' => 'berdaquniversitet5@gmail.com',
                'active' => '1',
                'course' => '1'
            ]
        ];
        foreach ($groups as $group) {
            DB::table('groups')->insert($group);
        }

        $teachers = [
            [
                'hemis_id' => '39',
                'name' => 'SHIXIYEV RAXIM MUXAMMETOVICH',
                'telegram_id' => '149523019',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:14',
                'updated_at' => '2023-01-28 05:55:14'
            ],
            [
                'hemis_id' => '1568',
                'name' => 'QUDAYNAZAROV MUXAMMED SADIQ ULI',
                'telegram_id' => '310857127',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '2557',
                'name' => 'GABBAROV SABIT NIETALI‘EVICH',
                'telegram_id' => '123',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '681',
                'name' => 'ADEMBAEV ARALBAY KURALBAEVICH',
                'telegram_id' => '124',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1057',
                'name' => 'ADILBAYEV BEKBOSIN ABATBAYEVICH',
                'telegram_id' => '125',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1231',
                'name' => 'AYMURZAYEVA GULNAZ PUXARBAYEVNA',
                'telegram_id' => '126',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '192',
                'name' => 'AYTMURATOVA JANILSIN JALGASOVNA',
                'telegram_id' => '127',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '183',
                'name' => 'BABADJANOV ELMUROD SATIMBAYEVICH',
                'telegram_id' => '128',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1347',
                'name' => 'BAUYETDINOV MAJIT JANIZAQOVICH',
                'telegram_id' => '129',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '2407',
                'name' => 'GULMIRZAYEVA GO‘ZAL ALISHER QIZI',
                'telegram_id' => '130',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '181',
                'name' => 'DJAYKOV GAFUR MURATBAYEVICH',
                'telegram_id' => '131',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '2528',
                'name' => 'YELMURATOV QUDIYAR QARJAO‘BAY ULI',
                'telegram_id' => '132',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '419',
                'name' => 'EREJEPOVA JIENGUL TAJETOVNA',
                'telegram_id' => '133',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '247',
                'name' => 'JUMABAEV DAULETBAY MARATOVICH',
                'telegram_id' => '134',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '2460',
                'name' => 'KALXANOV POLATBEK JUMABAEVICH',
                'telegram_id' => '135',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '2537',
                'name' => 'KAMALOV ULUG‘BEK POLAT ULI‘',
                'telegram_id' => '136',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1613',
                'name' => 'KARAMANOVA BENAZIR KARAMANOVNA',
                'telegram_id' => '137',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '258',
                'name' => 'KUTLI‘MURATOV BAYMURAT JIENBAEVICH',
                'telegram_id' => '138',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '143',
                'name' => 'MIRZANOV BERDAK JOLDASBAYEVICH',
                'telegram_id' => '139',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1044',
                'name' => 'ONGARBAYEV RAXIM OSERBAY ULI',
                'telegram_id' => '140',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1442',
                'name' => 'RAXIMBOYEV MUZAFFAR GULIMBOY O‘G‘LI',
                'telegram_id' => '141',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '86',
                'name' => 'RAXIMBERDIYEV QUVONCHBEK BAXTIYOROVICH',
                'telegram_id' => '142',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '5',
                'name' => 'SAMANDAROV BATIRBEK SATIMOVICH',
                'telegram_id' => '143',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '2566',
                'name' => 'SARSENBAEV KUWATBAY KUUANÍSHOVICH',
                'telegram_id' => '144',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '175',
                'name' => 'SEYDAMETOVA GULNARA UTARBAEVNA',
                'telegram_id' => '145',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '863',
                'name' => 'TEMIRXANOV ABDULLA UTEBAY-ULI',
                'telegram_id' => '146',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '182',
                'name' => 'TORENIYAZOV JALGAS YERJANOVICH',
                'telegram_id' => '147',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1700',
                'name' => 'TURIMBETOVA ELEONORA MAXSETBAY-QIZI',
                'telegram_id' => '148',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '7',
                'name' => 'BEKIYEV ASHIRMET BEKIYEVICH',
                'telegram_id' => '257149650',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '698',
                'name' => 'ABDREYMOV YUSUPBAY TURDIBAYEVICH',
                'telegram_id' => '257149650888',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '20',
                'name' => 'IBRAGIMOV KUANI‘SHBAY ISMAYLOVICH',
                'telegram_id' => '403806294',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '31',
                'name' => 'UTEMURATOV RUSTAM BAZARBAEVICH',
                'telegram_id' => '131872573',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:16',
                'updated_at' => '2023-01-28 05:55:16'],
            [
                'hemis_id' => '58',
                'name' => 'PIRNIYAZOVA GULZIRA JAMALOVNA',
                'telegram_id' => '5856695265',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:16',
                'updated_at' => '2023-01-28 05:55:16'],
            [
                'hemis_id' => '117',
                'name' => 'USENOVA KI‘ZLARGUL UBAYDULLAEVNA',
                'telegram_id' => '641354121',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:16',
                'updated_at' => '2023-01-28 05:55:16'],
            [
                'hemis_id' => '124',
                'name' => 'TURSI‘NBAEV ALLABAY XXX',
                'telegram_id' => '1720525912',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:16',
                'updated_at' => '2023-01-28 05:55:16'],
            [
                'hemis_id' => '137',
                'name' => 'BERDIBOYEV ULUG‘BEK SOATBOY O‘G‘LI',
                'telegram_id' => '267563856',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '190',
                'name' => 'KURBANOV BUXARBAY TURGANBAYEVICH',
                'telegram_id' => '769160397',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '237',
                'name' => 'NARBAYEVA RAZIYA DAULETBAYEVNA',
                'telegram_id' => '491705124',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '320',
                'name' => 'SEIDULLAYEV ABAT KAMALOVICH',
                'telegram_id' => '705095036',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:16',
                'updated_at' => '2023-01-28 05:55:16'],
            [
                'hemis_id' => '327',
                'name' => 'NASIROV PURXANATDIN BAUYENOVICH',
                'telegram_id' => '135801570',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '425',
                'name' => 'IBRAGIMOV MUXTAR MAMUTOVICH',
                'telegram_id' => '705095036',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '448',
                'name' => 'TASPANOVA JIGAGUL KENJEBAYEVNA',
                'telegram_id' => '390610770',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:16',
                'updated_at' => '2023-01-28 05:55:16'],
            [
                'hemis_id' => '501',
                'name' => 'MATKURBANOVA YULDUZ URAZIMBETOVNA',
                'telegram_id' => '846432897',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '635',
                'name' => 'KAMALOVA MAMURA SAG‘IDULLAYEVNA',
                'telegram_id' => '6229042090',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '665',
                'name' => 'GANIYEVA GULISTAN MAXSETBAYEVNA',
                'telegram_id' => '1048314971',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '711',
                'name' => 'AMANIYAZOVA SVETA BAYNIYAZOVNA',
                'telegram_id' => '798616383',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '712',
                'name' => 'ALNIYAZOV DAULETMURAT AITMURATOVICH',
                'telegram_id' => '439146435',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '732',
                'name' => 'ABDIREYMOVA DILARAM KDIRBAYEVNA',
                'telegram_id' => '357473359',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:14',
                'updated_at' => '2023-01-28 05:55:14'],
            [
                'hemis_id' => '835',
                'name' => 'ABSAMETOV BAYRAM BEKBOSINOVICH',
                'telegram_id' => '587066430',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:14',
                'updated_at' => '2023-01-28 05:55:14'],
            [
                'hemis_id' => '842',
                'name' => 'SHAGILOVA GULXATISHA KOPTLEUOVNA',
                'telegram_id' => '638304511',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:16',
                'updated_at' => '2023-01-28 05:55:16'],
            [
                'hemis_id' => '856',
                'name' => 'TOLIYEVA SHOIRA ILXOMOVNA',
                'telegram_id' => '5491242489',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:16',
                'updated_at' => '2023-01-28 05:55:16'],
            [
                'hemis_id' => '983',
                'name' => 'ALLAMURATOV NURLIBAY YESEMURATOVICH',
                'telegram_id' => '486877961',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:14',
                'updated_at' => '2023-01-28 05:55:14'],
            [
                'hemis_id' => '1034',
                'name' => 'KARIMOV AZAMAT YAXIYA ULI‘',
                'telegram_id' => '225908794',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1040',
                'name' => 'TURSINBAYEV MIRADDIN ORALBAY ULI',
                'telegram_id' => '697772050',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:16',
                'updated_at' => '2023-01-28 05:55:16'],
            [
                'hemis_id' => '1086',
                'name' => 'UTEBAYEV SALAMAT MAKSETBAY ULI',
                'telegram_id' => '174780527',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:14',
                'updated_at' => '2023-01-28 05:55:14'],
            [
                'hemis_id' => '1097',
                'name' => 'AYTMURATOV NAURI‘ZBAY KALBAEVICH',
                'telegram_id' => '1296059793',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1112',
                'name' => 'QOSBERGENOV BAXTIBAY JUGINISOVICH',
                'telegram_id' => '1823773187',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1116',
                'name' => 'KALBAYEV ALLAMBERGEN MARKABAYEVICH',
                'telegram_id' => '928001892',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1117',
                'name' => 'SEYTAXOV RAZIHAQBERGEN POLATBAY ULI',
                'telegram_id' => '508129550',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:16',
                'updated_at' => '2023-01-28 05:55:16'],
            [
                'hemis_id' => '1206',
                'name' => 'PIRNAZAROV NURNAZAR RASHID ULI‘',
                'telegram_id' => '55470277',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1267',
                'name' => 'MIRAMETOVA ELMIRA KOSHMAXAMBETOVNA',
                'telegram_id' => '1366310605',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1380',
                'name' => 'MUXAMMADIYAROVA AQMARAL JANABAEVNA',
                'telegram_id' => '895980540',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1421',
                'name' => 'BEKIMBETOV DINMUXAMMED RAMETULLAYEVICH',
                'telegram_id' => '285391205',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1487',
                'name' => 'TOLIBAYEV XOJAAXMED YELBAYEVICH',
                'telegram_id' => '305196098',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:16',
                'updated_at' => '2023-01-28 05:55:16'],
            [
                'hemis_id' => '1488',
                'name' => 'BURXANOVA SHIYRIN JAMSHITOVNA',
                'telegram_id' => '559752510',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1503',
                'name' => 'QAZAQBAYEVA QALLIGUL BAXTIYAR QIZI',
                'telegram_id' => '1943850486',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:16',
                'updated_at' => '2023-01-28 05:55:16'],
            [
                'hemis_id' => '1569',
                'name' => 'AZIZOVA GULZADA SARSENBAYEVNA',
                'telegram_id' => '5454863357',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1575',
                'name' => 'AYAPBERGENOV MAXSUD MAXMUD ULI',
                'telegram_id' => '712527205',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1647',
                'name' => 'AVEZBAYEVA JAMILA MUXTARBAYEVNA',
                'telegram_id' => '210662675',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1671',
                'name' => 'ABDIREYMOV ARI‘SLANBAY RUSTEM ULI‘',
                'telegram_id' => '1024623460',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1687',
                'name' => 'KDIRBAYEV AMIR MARAT ULI',
                'telegram_id' => '2144359501',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1692',
                'name' => 'ASQAROV QUDRAT AZATBAYEVICH',
                'telegram_id' => '673855658',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1697',
                'name' => 'KAMALOVA DILFUZA ABDULLOYEVNA',
                'telegram_id' => '623111140',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:16',
                'updated_at' => '2023-01-28 05:55:16'],
            [
                'hemis_id' => '1698',
                'name' => 'KUDAYBERGENOV JABBARBERGEN KADIRBERGENOVICH',
                'telegram_id' => '638200349',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1769',
                'name' => 'BEKMURATOVA DILARAM MAXSETBAEVNA',
                'telegram_id' => '589481233',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:14',
                'updated_at' => '2023-01-28 05:55:14'],
            [
                'hemis_id' => '1800',
                'name' => 'JOLDASBAYEV DAURANBEK RUSTEM ULI',
                'telegram_id' => '5718383280',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '1835',
                'name' => 'ALLAMBERGENOV ALLAYAR XASANBAEVICH',
                'telegram_id' => '901022330',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:14',
                'updated_at' => '2023-01-28 05:55:14'],
            [
                'hemis_id' => '2455',
                'name' => 'KUNNAZAROV UBBINIYAZ BAKTIBAYEVICH',
                'telegram_id' => '991198781',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:14',
                'updated_at' => '2023-01-28 05:55:14'],
            [
                'hemis_id' => '2456',
                'name' => 'TURGANBAYEV AZIZBEK JENIS ULI',
                'telegram_id' => '572992841',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:15',
                'updated_at' => '2023-01-28 05:55:15'
            ],
            [
                'hemis_id' => '2596',
                'name' => 'AKILBEKOVA SALTANAT SHAMURATOVNA',
                'telegram_id' => '495735762',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:14',
                'updated_at' => '2023-01-28 05:55:14'
            ],
            [
                'hemis_id' => '709',
                'name' => 'TANIRBERGENOV SULTANBEK BAZARBAEVICH',
                'telegram_id' => '913637054',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:14',
                'updated_at' => '2023-01-28 05:55:14'
            ],
            [
                'hemis_id' => '413',
                'name' => 'DAULETNAZAROVA SARBINAZ PRANADINOVNA',
                'telegram_id' => '6619879112',
                'active' => '1',
                'created_at' => '2023-01-28 05:55:14',
                'updated_at' => '2023-01-28 05:55:14'
            ],
        ];
        foreach ($teachers as $teacher) {
            $teacher['password'] = Hash::make('123456789');
            $exp = explode(' ', strtolower($teacher['name']));
            $username = preg_replace("/[^a-zA-Z0-9]+/", "", $exp[1]) . '_' .
                preg_replace("/[^a-zA-Z0-9]+/", "", $exp[0]);
            $teacher['username'] = $username;
            $teacher['role'] = 'user';

            DB::table('users')
                ->insert($teacher);
        }
    }
}
