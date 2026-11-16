<?php

use App\Http\Controllers\Api\LessonLaunchController;
use App\Http\Controllers\Auth\HemisController;
use Illuminate\Support\Facades\Route;

/*
| Frontend (React) alohida yig'iladi va nginx tomonidan beriladi.
| Laravel faqat API, HEMIS OAuth va Telegram'dagi "Darsni boshlash" havolasiga javob beradi.
*/

Route::middleware('throttle:auth')->group(function () {
    Route::get('/auth/hemis/redirect', [HemisController::class, 'redirect'])->name('hemis.redirect');
    Route::get('/auth/hemis/callback', [HemisController::class, 'callback'])->name('hemis.callback');
});

Route::get('/l/{lesson}/start', LessonLaunchController::class)
    ->middleware(['signed', 'throttle:auth'])
    ->name('lessons.launch');

Route::get('/', fn () => redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/'));
