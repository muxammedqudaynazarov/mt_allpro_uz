<?php

use App\Http\Controllers\HomeController;
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

Route::get('/', function () {
    return redirect('/login');
});

Auth::routes();
Route::get('/register', function () {
    return redirect('/login');
});
Route::post('/register', function () {
    return redirect('/login');
});

Route::prefix('home')->middleware(['auth'])->group(function () {
    Route::get('/course/{course}', [HomeController::class, 'get_course']);
    Route::get('/username/{name}', [HomeController::class, 'user_name']);
    Route::get('/delete/{meet_id}', [HomeController::class, 'meet_delete']);
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/meets', [HomeController::class, 'meets_get']);
    Route::post('/meets', [HomeController::class, 'meets_post']);

    Route::get('/change', [HomeController::class, 'change_password']);
    Route::post('/change', [HomeController::class, 'change_password_post']);

    Route::get('/teacher_insert', [HomeController::class, 'teacher_insert']);
    Route::post('/teacher_insert', [HomeController::class, 'teacher_insert_post']);

});
Route::get('/update', [UpdateController::class, 'teachers']);
Route::get('/generate_token', [UpdateController::class, 'generate_token']);
