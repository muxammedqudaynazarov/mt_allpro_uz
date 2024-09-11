<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Meet;
use App\Models\Time;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeetController extends Controller
{
    public function create()
    {
        $groups = Group::where('course', '1')->get();
        $times = Time::where('status', '1')->get();
        return view('meets', compact(['groups', 'times']));
    }

    public function store(Request $request)
    {
        $request->validate([
            'course' => 'required',
            'group' => 'required',
            'time' => 'required',
        ], [
            'course.requires' => 'Курсни белгиланг',
            'group.requires' => 'Гуруҳни белгиланг',
            'time.requires' => 'Вақтни белгиланг',
        ]);
        $group = Group::find($request->group);
        if ($group) {
            $time = Time::where('code', $request->time)->first();
            $api_url = 'https://api.zoom.us/v2/users/' . $group->email . '/meetings';
            $headers = array(
                "Authorization: Bearer " . $group->api,
                "Content-Type: application/json",
            );
            $topic = !empty($request->topic) ? $request->topic : $group->name;

            $data = [
                'topic' => $topic, 'type' => 1, 'start_time' => date('Y-m-d H:i:s'),
                'duration' => 80, 'timezone' => 'UTC+5', 'password' => '2023',
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
                ]
            ];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $response = json_decode(curl_exec($ch));
            //dd($response);
            curl_close($ch);

            if (isset($response->id)) {
                $duration = time() + 80 * 60;
                Meet::create([
                    'meet_id' => $response->id,
                    'host_id' => $response->host_id,
                    'topic' => $topic,
                    'user_id' => auth()->id(),
                    'group_id' => $group->id,
                    'time_id' => $time->id,
                    'start_url' => $response->start_url,
                    'join_url' => $response->join_url,
                    'started_at' => date('Y-m-d H:i:s', time()),
                    'ended_at' => date('Y-m-d H:i:s', $duration)
                ]);

                $text = '<b>Assalawma áleykum, ' . auth()->user()->name;
                $text .= '. Sizge ZOOM arqalı sabaq ótiw linki jaratıldı. ' .
                    'Tómendegi «SABAQTÍ BASLAW» túymesin basıp sabaq ótiwdi baslasańız boladı.</b>' .
                    "\n\n" .
                    '‼️ <b>«LINK JIBERIW» túymesin basıp kerekli gruppaǵa jiberiwdi umıtpań!</b>' .
                    "\n\n" .
                    "<b>Studentler ushın kiriw linki:\n</b>";
                $text .= '<code>' . $response->join_url . '</code>';

                $botApi = '5684948299:AAHa3NhGYHfxVcXemExopbJYPDEOG-3ytuw';


                $data_telegram = [
                    'chat_id' => auth()->user()->telegram_id,
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
                return redirect('/home')->with('success', 'Дарс яратилди!');
            } else {
                return redirect()->back()->with('error', 'Тизимда техник ишлар олиб борилмоқда...');
            }
        }
    }

    public function destroy($id)
    {
        $meet = Meet::find($id);
        if ($meet) {
            $api_url = "https://api.zoom.us/v2/meetings/$meet->meet_id/status";
            $data = array('action' => 'end');
            $headers = array(
                "Authorization: Bearer " . $meet->group->api,
                "Content-Type: application/json",
            );
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_exec($ch);
            $meet->type = 'e';
            $meet->save();
            return redirect()->back()->with('success', 'Конференция мувофаққиятли якунланди');
        } else return redirect()->back()->with('error', 'Конференция топилмади');
    }

    public function get_course($course)
    {
        echo '<option selected disabled></option>';
        $groups = DB::table('groups')
            ->where('course', $course)
            ->where('active', '1')
            ->get()->toArray();
        foreach ($groups as $group) {
            echo '<option value="' . $group->id . '">' . $group->name . '</option>';
        }
    }
}
