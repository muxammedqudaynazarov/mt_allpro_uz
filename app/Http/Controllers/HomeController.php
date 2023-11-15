<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            echo '<option value="' . $v->hemis_id . '">' . $v->name . '</option>';
        }
    }

    public function index()
    {
        return view('home');
    }

    public function meets_get()
    {
        $groups = DB::table('groups')
            ->where('course', '1')->get()->toArray();
        return view('meets', ['groups' => $groups]);
    }

    public function meets_post(Request $request)
    {
        $group = DB::table('groups')
            ->where('hemis_id', $request->group)
            ->first();
        $api_url = 'https://api.zoom.us/v2/users/' . $group->email . '/meetings';
        $access_token = $group->api;

        $headers = array(
            "Authorization: Bearer $access_token",
            "Content-Type: application/json",
        );

        if (isset($request->topic)) $topic = $request->topic;
        else $topic = $group->name;

        $data = array(
            'topic' => $topic,
            'type' => 2,
            'start_time' => date('Y-m-d H:i:s'),
            'duration' => 80,
        );

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = json_decode(curl_exec($ch));
        dd($response);
        curl_close($ch);


    }

    public function token_get()
    {
        return view('token');
    }

    public function token_post(Request $request)
    {
        $clientId = $request->clientId;
        $clientSecret = $request->clientSecret;
        $accountId = $request->accountId;

        $oauthUrl = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . $accountId;
        try {
            $authHeader = 'Basic ' . base64_encode($clientId . ':' . $clientSecret);
            echo "authHeader: " . $authHeader . PHP_EOL;
            $ch = curl_init($oauthUrl);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: ' . $authHeader));
            $response = curl_exec($ch);
            dd(json_decode($response));
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode == 200) {
                $oauthResponse = json_decode($response, true);
                $accessToken = $oauthResponse['access_token'];
                dd($accessToken);
                return $accessToken;
            } else {
                echo 'OAuth Request Failed with Status Code: ' . $httpCode . PHP_EOL;
                echo $response . PHP_EOL;
                return null;
            }
            curl_close($ch);
        } catch (Exception $e) {
            dd($e);
            echo 'An error occurred: ' . $e->getMessage() . PHP_EOL;
            return null;
        }

    }

    public function create()
    {


    }
}
