<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\LessonSlotController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ZoomAccountController;
use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

// Ochiq marshrutlar
Route::get('/config', ConfigController::class);
Route::post('/auth/login', [SessionController::class, 'store'])->middleware('throttle:auth');
Route::post('/telegram/webhook', TelegramWebhookController::class)->middleware('throttle:telegram');

Route::middleware(['auth:sanctum', 'account', 'throttle:api'])->group(function () {
    Route::post('/auth/logout', [SessionController::class, 'destroy']);

    // Tasdiqlanmagan xodimlar ham foydalana oladi
    Route::get('/me', [MeController::class, 'show']);
    Route::post('/me/telegram', [MeController::class, 'telegramLink']);
    Route::delete('/me/telegram', [MeController::class, 'telegramUnlink']);

    Route::middleware('account:active')->group(function () {
        Route::get('/dashboard', DashboardController::class);

        Route::get('/lessons', [LessonController::class, 'index']);
        Route::get('/lessons/options', [LessonController::class, 'options']);
        Route::get('/lessons/export', [LessonController::class, 'export']);
        Route::post('/lessons', [LessonController::class, 'store'])->middleware('throttle:lesson-create');
        Route::post('/lessons/{lesson}/start', [LessonController::class, 'start']);
        Route::post('/lessons/{lesson}/end', [LessonController::class, 'end']);

        Route::middleware('role:admin,super_admin')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::get('/users/counts', [UserController::class, 'counts']);
            Route::patch('/users/{user}', [UserController::class, 'update']);
            Route::delete('/users/{user}', [UserController::class, 'destroy']);

            Route::get('/groups', [GroupController::class, 'index']);
            Route::post('/groups', [GroupController::class, 'store']);
            Route::put('/groups/{group}', [GroupController::class, 'update']);
            Route::post('/groups/{group}/toggle', [GroupController::class, 'toggle']);
            Route::delete('/groups/{group}', [GroupController::class, 'destroy']);

            Route::get('/reports/summary', [ReportController::class, 'summary']);
        });

        Route::middleware('role:super_admin')->group(function () {
            Route::get('/zoom-accounts', [ZoomAccountController::class, 'index']);
            Route::post('/zoom-accounts', [ZoomAccountController::class, 'store']);
            Route::put('/zoom-accounts/{zoomAccount}', [ZoomAccountController::class, 'update']);
            Route::delete('/zoom-accounts/{zoomAccount}', [ZoomAccountController::class, 'destroy']);
            Route::post('/zoom-accounts/{zoomAccount}/test', [ZoomAccountController::class, 'test']);
            Route::get('/zoom-accounts/{zoomAccount}/users', [ZoomAccountController::class, 'users']);

            Route::get('/lesson-slots', [LessonSlotController::class, 'index']);
            Route::post('/lesson-slots', [LessonSlotController::class, 'store']);
            Route::put('/lesson-slots/{lessonSlot}', [LessonSlotController::class, 'update']);
            Route::delete('/lesson-slots/{lessonSlot}', [LessonSlotController::class, 'destroy']);

            Route::get('/audit-logs', AuditLogController::class);
        });
    });
});
