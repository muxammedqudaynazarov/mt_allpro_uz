<?php

use App\Http\Controllers\GroupController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MeetController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\UpdateController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

//Route::get('/', [ScheduleController::class, 'index']);
//Route::get('/groups/{group_id}', [ScheduleController::class, 'schedule']);
//Route::get('/course/{course}', [ScheduleController::class, 'course']);

Route::get('/', function () {
    return redirect()->route('login');
});

Auth::routes();

Route::get('/logout', function () {
    Auth::logout();
    return redirect('/login');
});

Route::get('/register', function () {
    return redirect('/login');
});

Route::post('/register', function () {
    return redirect('/login');
});

Route::prefix('home')->middleware(['auth'])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::resource('/meets', MeetController::class)->only(['create', 'store', 'destroy']);
    Route::get('/course/{course}', [MeetController::class, 'get_course']);

    Route::resource('/teachers', TeacherController::class);
    Route::get('/teachers/{id}/status', [TeacherController::class, 'status']);
    Route::get('/teachers/{id}/password', [TeacherController::class, 'password']);

    Route::resource('/groups', GroupController::class);
    Route::get('/groups/change_status/{group_id}', [HomeController::class, 'change_status']);

    Route::get('/change', [HomeController::class, 'change_password']);
    Route::post('/change', [HomeController::class, 'change_password_post']);

    //Route::get('/username/{name}', [HomeController::class, 'user_name']);
    //Route::get('/teacher_insert', [HomeController::class, 'teacher_insert']);
    //Route::post('/teacher_insert', [HomeController::class, 'teacher_insert_post']);
    //Route::get('/groups', [HomeController::class, 'groups']);
});
Route::get('/generate_token', [UpdateController::class, 'generate_token']);
