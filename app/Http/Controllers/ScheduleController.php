<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function index()
    {
        $groups = DB::table('groups')->where('active', '1')
            ->orderBy('course')
            ->get()->toArray();
        return view('welcome', ['groups' => $groups]);
    }

    public function course($course)
    {
        if (DB::table('groups')->where('course', $course)->where('active', '1')->count()) {
            $groups = DB::table('groups')
                ->where('course', $course)
                ->where('active', '1')
                ->orderBy('course')
                ->get()->toArray();
            return view('courses', ['groups' => $groups]);
        } else return abort(404);
    }

    public function schedule($group_id)
    {
        $b_day = strtotime(date('d.m.Y 00:00:01', strtotime("this week")));
        $e_day = strtotime(date('d.m.Y 23:59:59', $b_day + (5 * 86400)));
        $array = [
            'Monday' => [
                1 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
                2 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
                3 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
            ],
            'Tuesday' => [
                1 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
                2 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
                3 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
            ],
            'Wednesday' => [
                1 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
                2 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
                3 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
            ],
            'Thursday' => [
                1 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
                2 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
                3 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
            ],
            'Friday' => [
                1 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
                2 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
                3 => ['lesson' => "&nbsp;", 'teacher' => "&nbsp;", 'value' => "&nbsp;",],
            ],
        ];

        $ch = curl_init();
        $headers = array(
            'accept: application/json',
            'Authorization: Bearer ' . env('API_HEMIS'),
        );
        curl_setopt($ch, CURLOPT_URL,
            "https://student.karsu.uz/rest/v1/data/schedule-list?_group=$group_id&lesson_date_from=$b_day&lesson_date_to=$e_day");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $list = json_decode(curl_exec($ch));

        foreach ($list->data->items as $value) {
            if ($value->lessonPair->start_time == '14:30') {
                $array[date('l', $value->lesson_date)][1]['lesson'] = $value->subject->name;
                $array[date('l', $value->lesson_date)][1]['teacher'] = $value->employee->name;
                $array[date('l', $value->lesson_date)][1]['value'] = $value->lessonPair->start_time . '-' .
                    $value->lessonPair->end_time;
            } elseif ($value->lessonPair->start_time == '16:00') {
                $array[date('l', $value->lesson_date)][2]['lesson'] = $value->subject->name;
                $array[date('l', $value->lesson_date)][2]['teacher'] = $value->employee->name;
                $array[date('l', $value->lesson_date)][2]['value'] = $value->lessonPair->start_time . '-' .
                    $value->lessonPair->end_time;
            } elseif ($value->lessonPair->start_time == '17:30') {
                $array[date('l', $value->lesson_date)][3]['lesson'] = $value->subject->name;
                $array[date('l', $value->lesson_date)][3]['teacher'] = $value->employee->name;
                $array[date('l', $value->lesson_date)][3]['value'] = $value->lessonPair->start_time . '-' .
                    $value->lessonPair->end_time;
            }
        }

        return view('schedule', [
            'data' => $array,
            'begin' => $b_day,
        ]);
    }
}
