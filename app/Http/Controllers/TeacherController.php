<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TeacherController extends Controller
{
    public $error_back = 'Ушбу ҳаракатни бажариб бўлмайди!';

    public function index()
    {
        if (auth()->user()->can('teachers:view')) {
            $teachers = [];
            if (auth()->user()->current_role == 'super_admin')
                $teachers = User::paginate(10);
            if (auth()->user()->current_role == 'admin')
                $teachers = User::where('admin_id', auth()->id())->paginate(10);

            return view('teachers.index', compact('teachers'));
        }
        return redirect()->back()->with('error', $this->error_back);
    }

    public function create()
    {
        if (auth()->user()->can('teachers:create')) {
            return view('teachers.create');
        }
        return redirect()->back()->with('error', $this->error_back);
    }

    public function store(Request $request)
    {
        if (auth()->user()->can('teachers:create')) {
            $request->validate([
                'telegram_id' => 'required|unique:users,telegram_id',
                'name' => 'required',
                'phone' => 'required|unique:users,phone',
                'username' => 'required|unique:users,username'
            ], [
                'telegram_id.required' => '«Telegram ID» майдони тўлдирилиши мажбурий!',
                'telegram_id.unique' => '«Telegram ID» аввалдан мавжуд!',
                'name.required' => '«Ўқитувчининг тўлиқ Ф.И.Ш.» майдони тўлдирилиши мажбурий!',
                'phone.required' => '«Телефон рақами» майдони тўлдирилиши мажбурий!',
                'phone.unique' => '«Телефон рақами» аввалдан мавжуд!',
                'username.required' => '«Ўқитувчи логини» майдони тўлдирилиши мажбурий!',
                'username.unique' => '«Ўқитувчи логини» аввалдан мавжуд!',
            ]);
            if ($request->hemis_id) {
                $user_hemis_id = User::where('hemis_id', $request->hemis_id)->first();
                if ($user_hemis_id)
                    return redirect()->back()->with('error', '«HEMIS ID» аввалдан мавжуд!');
            }
            $password = uniqid();
            $user = User::create([
                'telegram_id' => $request->telegram_id,
                'name' => strtoupper($request->name),
                'phone' => $request->phone,
                'username' => strtolower($request->username),
                'hemis_id' => isset($request->hemis_id) ? $request->hemis_id : null,
                'admin_id' => auth()->user()->current_role == 'admin' ? auth()->id() : null,
                'roles_list' => json_encode(['teacher']),
                'password' => Hash::make($password),
                'current_role' => 'teacher'
            ]);
            $user->assignRole('teacher');

            $botApi = '5684948299:AAHa3NhGYHfxVcXemExopbJYPDEOG-3ytuw';
            $message = '<b>Ассалаўма әлейкум ' . strtoupper($request->name) . '. Сиз аралықтан оқытыў бағдары топарлары ушын сабақ өтиўге жәрдемлесиўши «Масофавий таълим тизими» (https://mt.allpro.uz) системасында дизимнен өткерилдиңиз!</b>';
            $message .= "\n\n<b>Логин:</b> <code>" . strtolower($request->username) . "</code>\n<b>Пароль:</b> <code>" . $password . '</code>';
            $data_telegram = [
                'chat_id' => $request->telegram_id,
                'text' => $message,
                'parse_mode' => 'html'
            ];
            $context = stream_context_create(['http' => ['ignore_errors' => true]]);

            file_get_contents("https://api.telegram.org/bot$botApi/sendMessage?" . http_build_query($data_telegram), false, $context);

            return redirect(route('teachers.index'))->with('success', 'Фойдаланувчи яратилди! Пароль (dist_karsu_uz_bot) орқали юборилди!');
        }
        return redirect()->back()->with('error', $this->error_back);
    }

    public function edit($id)
    {
        if (auth()->user()->can('teachers:edit')) {

        }
        return redirect()->back()->with('error', $this->error_back);
    }

    public function update(Request $request, $id)
    {
        if (auth()->user()->can('teachers:edit')) {

        }
        return redirect()->back()->with('error', $this->error_back);
    }

    public function destroy($id)
    {
        if (auth()->user()->can('teachers:delete')) {
            User::where('id', $id)->delete();
            return redirect(route('teachers.index'))->with('success', 'Фойдаланувчи ўчирилди!');
        }
        return redirect()->back()->with('error', $this->error_back);
    }

    public function status($id)
    {
        if (auth()->user()->can('teachers:status')) {
            $user = User::find($id);
            if (auth()->user()->current_role == 'super_admin' || $user->admin_id == auth()->id()) {
                if ($user) {
                    $user->status = ($user->status == 'active') ? 'inactive' : 'active';
                    $user->save();
                }
                return redirect(route('teachers.index'))->with('success', 'Фойдаланувчи статуси ўзгартирилди!');
            }
        }
        return redirect()->back()->with('error', $this->error_back);
    }

    public function password($id)
    {
        if (auth()->user()->can('teachers:password')) {
            $user = User::find($id);
            if (auth()->user()->current_role == 'super_admin' || $user->admin_id == auth()->id()) {
                if ($user) {
                    $password = uniqid();
                    $botApi = '5684948299:AAHa3NhGYHfxVcXemExopbJYPDEOG-3ytuw';
                    $message = '🔑 <b>Ассалаўма әлейкум ' . $user->name . '. Пароль өзгертилди!</b>';
                    $message .= "\n\n<b>Логин:</b> <code>" . strtolower($user->username) . "</code>\n<b>Пароль:</b> <code>" . $password . '</code>';
                    $data_telegram = [
                        'chat_id' => $user->telegram_id,
                        'text' => $message,
                        'parse_mode' => 'html'
                    ];
                    $context = stream_context_create(['http' => ['ignore_errors' => true]]);
                    file_get_contents("https://api.telegram.org/bot$botApi/sendMessage?" . http_build_query($data_telegram), false, $context);
                    $user->password = Hash::make($password);
                    $user->save();
                }
                return redirect(route('teachers.index'))->with('success', 'Фойдаланувчи пароли ўзгартирилди!');
            }
        }
        return redirect()->back()->with('error', $this->error_back);
    }
}
