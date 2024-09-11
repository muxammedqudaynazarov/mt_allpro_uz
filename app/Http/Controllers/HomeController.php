<?php

namespace App\Http\Controllers;

use App\Models\Meet;
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

    public function index(Request $request)
    {
        $meets = Meet::orderBy('created_at', 'desc')->paginate(10)->withQueryString();
        $teachers = DB::table('users')->orderBy('name')->get()->toArray();

        return view('home', [
            'meets' => $meets,
            'teachers' => $teachers,
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
                return redirect('/home')->with('success', 'Пароль мувафаққиятли ўзгартирилди!');
            } else return redirect()->back()->with('error', 'Пароллар бир-бирини тасдиқламади!');
        } else return redirect()->back()->with('error', 'Пароль камида 8 белгидан иборат бўлиши керак!');
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
