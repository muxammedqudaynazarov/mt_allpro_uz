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
    //return view('welcome');
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
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/meets', [HomeController::class, 'meets_get']);
    Route::post('/meets', [HomeController::class, 'meets_post']);

    Route::get('/token', [HomeController::class, 'token_get']);
    Route::post('/token', [HomeController::class, 'token_post']);

});
Route::get('/update', [UpdateController::class, 'login']);
