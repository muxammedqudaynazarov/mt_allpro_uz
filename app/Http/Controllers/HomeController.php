<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */

    public function get_course($course)
    {
        echo '<option selected disabled></option>';
        $get = DB::table('groups')
            ->where('course', $course)
            ->where('active', '1')
            ->get()->toArray();
        foreach ($get as $v) {
            if (\App\Models\Application::in_empty_group($v->hemis_id)) {
                echo '<option value="' . $v->hemis_id . '">' . $v->name .
                    '(' . substr($v->email, 0, 2) . '***' . substr($v->email, -11) . ')' .
                    '</option>';
            }
        }
    }

    public function index(Request $request)
    {
        if (\auth()->user()->change_password == '0') return \redirect('/home/change');
        $t = false;
        if (auth()->user()->role == 'admin') {
            $teachers = DB::table('users')
                ->where('active', '1')
                ->orderBy('name')
                ->get()->toArray();

            if (isset($request->query()['teacher_id'])) {
                $meets = DB::table('meets')
                    ->where('_from', $request->query()['teacher_id'])
                    ->orderBy('created_at', 'desc')
                    ->paginate(10)->withQueryString();
                $t = $request->query()['teacher_id'];
            } else {
                $meets = DB::table('meets')
                    ->orderBy('created_at', 'desc')
                    ->paginate(10)->withQueryString();
            }
        } else {
            $teachers = false;
            $meets = DB::table('meets')
                ->where('_from', auth()->user()->hemis_id)
                ->orderBy('created_at', 'desc')
                ->paginate(10)->withQueryString();
        }
        return view('home', [
            'meets' => $meets,
            'teachers' => $teachers,
            't' => $t,
            'lessons' => DB::table('meets')->count(),
            'lessons_o' => DB::table('meets')
                ->where('status', 'w')
                ->orWhere('status', 's')->count(),
            'lessons_c' => DB::table('meets')->where('status', 'o')->count(),
        ]);
    }

    public function meet_delete($meeting_id)
    {
        $get = DB::table('meets')
            ->where('meet_id', $meeting_id)
            ->first();
        if ($get) {
            $access_token = DB::table('groups')
                ->where('hemis_id', $get->_to)->first()->api;

            $api_url = "https://api.zoom.us/v2/meetings/$meeting_id/status";
            $data = array(
                "action" => "end"
            );
            $headers = array(
                "Authorization: Bearer $access_token",
                "Content-Type: application/json",
            );
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $response = curl_exec($ch);
            DB::table('meets')
                ->where('meet_id', $meeting_id)
                ->update([
                    'status' => 'o',
                ]);
            return redirect()->back()->with('success', 'Konferentsiya muvofaqqiyatli tugatildi');
        } else {
            return redirect()->back()->with('error', 'Konferentsiya topilmadi');
        }
    }

    public function meets_get()
    {
        if (\auth()->user()->change_password == '0') return \redirect('/home/change');
        $groups = DB::table('groups')
            ->where('course', '1')
            ->where('active', '1')
            ->get()->toArray();
        if (auth()->user()->role == 'admin') {
            $teachers = DB::table('users')
                ->where('active', '1')
                ->orderBy('name')
                ->get()->toArray();
        } else {
            $teachers = false;
        }
        return view('meets', ['groups' => $groups, 'teachers' => $teachers]);
    }

    public function meets_post(Request $request)
    {
        if (\auth()->user()->change_password == '0') return \redirect('/home/change');

        if (isset($request->group)) {
            $group = DB::table('groups')
                ->where('hemis_id', $request->group)
                ->first();
            if ($group) {
                $api_url = 'https://api.zoom.us/v2/users/' . $group->email . '/meetings';
                $access_token = $group->api;

                $headers = array(
                    "Authorization: Bearer $access_token",
                    "Content-Type: application/json",
                );

                if (isset($request->topic)) $topic = $request->topic;
                else $topic = $group->name;

                $data = [
                    'topic' => $topic,
                    'type' => 1,
                    'start_time' => date('Y-m-d H:i:s'),
                    'duration' => 80,
                    'timezone' => 'UTC+5',
                    'password' => '2023',
                    'settings' => [
                        'host_video' => false,
                        'participant_video' => true,
                        'in_meeting' => false,
                        'join_before_host' => false,
                        'mute_upon_entry' => false,
                        'use_pmi' => false,
                        'approval_type' => 1,
                        'audio' => 'both',
                        'auto_recording' => 'none',
                        'enforce_login' => false,
                        'registrants_email_notification' => false,
                        'email_notification' => false,
                        'waiting_room' => false,
                    ],
                ];

                $from_id = auth()->user()->hemis_id;
                $telegram_id = auth()->user()->telegram_id;

                if (auth()->user()->role == 'admin') {
                    $from_id = $request->teacher;
                    if (!isset($from_id)) return redirect()->back()
                        ->with('error', 'Kerakli maydonlar to‘ldirilishi shart!');
                    $user = DB::table('users')
                        ->where('hemis_id', $request->teacher)
                        ->first();
                    $telegram_id = $user->telegram_id;
                }

                $ch = curl_init($api_url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                $response = json_decode(curl_exec($ch));
                curl_close($ch);

                if (isset($response->id)) {
                    $time = time() + 80 * 60;
                    DB::table('meets')->insert([
                        'meet_id' => $response->id,
                        'host_id' => $response->host_id,
                        'topic' => $response->topic,
                        '_from' => $from_id,
                        '_to' => $request->group,
                        'start_url' => $response->start_url,
                        'join_url' => $response->join_url,
                        'started_at' => date('Y-m-d H:i:s', time()),
                        'ended_at' => date('Y-m-d H:i:s', $time),
                        'created_at' => date('Y-m-d H:i:s', time()),
                    ]);

                    $text = '<b>Assalawma áleykum, ';

                    if (auth()->user()->role == 'admin') $text .= $user->name;
                    else $text .= auth()->user()->name;

                    $text .= '. Sizge ZOOM arqalı sabaq ótiw linki jaratıldı. ' .
                        'Tómendegi «SABAQTÍ BASLAW» túymesin basıp sabaq ótiwdi baslasańız boladı.</b>' .
                        "\n\n" .
                        '‼️ <b>«LINK JIBERIW» túymesin basıp kerekli gruppaǵa jiberiwdi umıtpań!</b>' .
                        "\n\n" .
                        "<b>Studentler ushın kiriw linki:\n</b>";
                    $text .= '<code>' . $response->join_url . '</code>';

                    $botApi = '5684948299:AAHa3NhGYHfxVcXemExopbJYPDEOG-3ytuw';


                    $data_telegram = [
                        'chat_id' => $telegram_id,
                        'text' => $text,
                        'parse_mode' => 'html',
                        'reply_markup' => json_encode([
                            "inline_keyboard" => [
                                [
                                    [
                                        'text' => "SABAQTÍ BASLAW",
                                        'url' => $response->start_url,
                                    ],
                                    [
                                        'text' => 'LINK JIBERIW',
                                        'url' => 'https://telegram.me/share/url?url=' . $response->join_url,
                                    ]
                                ]
                            ]
                        ])
                    ];
                    $context = stream_context_create(['http' => ['ignore_errors' => true]]);
                    $result_fgc =
                        file_get_contents("https://api.telegram.org/bot$botApi/sendMessage?" . http_build_query($data_telegram)
                            , false, $context);
                    $result_fgc = json_decode($result_fgc);
                    /*
                    $admins = DB::table('users')->select('telegram_id')
                        ->where('role', 'admin')->get()->toArray();

                    foreach ($admins as $admin) {
                        $data_telegram_admin = [
                            'chat_id' => $admin->telegram_id,
                            'text' => $text,
                            'parse_mode' => 'html',
                            'reply_markup' => json_encode([
                                "inline_keyboard" => [
                                    [
                                        [
                                            'text' => "SABAQTÍ BASLAW",
                                            'url' => $response->start_url,
                                        ],
                                        [
                                            'text' => 'LINK JIBERIW',
                                            'url' => 'https://telegram.me/share/url?url=' . $response->join_url,
                                        ]
                                    ]
                                ]
                            ])
                        ];
                        file_get_contents("https://api.telegram.org/bot$botApi/sendMessage?" .
                            http_build_query($data_telegram_admin));
                    }*/

                    return redirect('/home')->with('success', 'Dars yaratildi!');
                } else {
                    return redirect()->back()->with('error', 'Tizimda texnik ishlar olib borilmoqda...');
                }
            } else {
                return redirect()->back()->with('error', 'Bunday guruh topilmadi yoki ruxsat etilmagan!');
            }
        } else {
            return redirect()->back()->with('error', 'Guruh belgilanmadi!');
        }
    }

    public function change_password()
    {
        return view('change_password');
    }

    public function change_password_post(Request $request)
    {
        if (strlen($request->newPassword) >= 8) {
            if ($request->newPassword == $request->reEnterPassword) {
                DB::table('users')->where('id', auth()->id())
                    ->update([
                        'change_password' => '1',
                        'password' => Hash::make($request->newPassword),
                        'updated_at' => DB::raw('NOW()'),
                    ]);
                return redirect('/home')->with('success', 'Parol muvaffaqiyatli o‘zgartirildi!');
            } else return redirect()->back()->with('error', 'Parollar bir-birini tasdiqlamadi!');
        } else return redirect()->back()->with('error', 'Parol qiymati 8 belgidan kam!');
    }

    public function teacher_insert()
    {
        if (auth()->user()->role == 'admin') {
            return view('teacher_add');
        } else return redirect()->back()->with('error', 'Ushbu sahifaga o‘tib bo‘lmaydi!');
    }

    public function teacher_insert_post(Request $request)
    {
        if (auth()->user()->role == 'admin') {
            DB::table('users')
                ->insert([
                    'hemis_id' => $request->hemis_id,
                    'telegram_id' => $request->telegram_id,
                    'name' => $request->name,
                    'username' => $request->username,
                    'password' => Hash::make($request->password),
                    'role' => $request->role,
                    'active' => $request->active,
                ]);
            return redirect('/home')->with(['success', 'O‘qituvchi bazaga q‘oshildi!']);
        } else return redirect()->back()->with('error', 'Ushbu sahifaga o‘tib bo‘lmaydi!');
    }

    public function user_name($name)
    {
        if (auth()->user()->role == 'admin') {
            if (str_contains($name, ' ')) {
                $exp = explode(' ', strtolower($name));
                return preg_replace("/[^a-zA-Z0-9]+/", "", $exp[1]) . '_' .
                    preg_replace("/[^a-zA-Z0-9]+/", "", $exp[0]);
            } else {
                return strtolower($name);
            }
        } else return redirect()->back()->with('error', 'Ushbu sahifaga o‘tib bo‘lmaydi!');
    }

    public function groups()
    {
        $groups = DB::table('groups')->orderBy('course')->get()->toArray();
        return view('groups', ['groups' => $groups]);
    }

    public function change_status($group_id)
    {
        $data = DB::table('groups')
            ->where('hemis_id', $group_id)
            ->first();
        $change = 1;
        if ($data->active == '0') $change = '1';
        if ($data->active == '1') $change = '0';
        DB::table('groups')->where('hemis_id', $group_id)
            ->update([
                'active' => $change,
            ]);
    }

}
