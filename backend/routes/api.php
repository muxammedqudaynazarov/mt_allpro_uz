<?php

use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\LessonAttendanceController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\LessonSeriesController;
use App\Http\Controllers\Api\LessonSlotController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MonitorController;
use App\Http\Controllers\Api\RecordingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SemesterController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ZoomAccountController;
use App\Http\Controllers\Api\ZoomHostController;
use App\Http\Controllers\Api\ZoomWebhookController;
use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

// Ochiq marshrutlar
Route::get('/config', ConfigController::class);
Route::post('/auth/login', [SessionController::class, 'store'])->middleware('throttle:auth');
Route::post('/telegram/webhook', TelegramWebhookController::class)->middleware('throttle:telegram');
Route::post('/zoom/webhook/{zoomAccount}', ZoomWebhookController::class)->middleware('throttle:zoom-webhook');

Route::middleware(['auth:sanctum', 'account', 'throttle:api'])->group(function () {
    Route::post('/auth/logout', [SessionController::class, 'destroy']);

    // Tasdiqlanmagan xodimlar ham foydalana oladi
    Route::get('/me', [MeController::class, 'show']);
    Route::patch('/me', [MeController::class, 'update']);
    Route::put('/me/password', [MeController::class, 'password'])->middleware('throttle:auth');
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
        Route::get('/lessons/{lesson}/live', [MonitorController::class, 'participants']);
        Route::get('/lessons/{lesson}/recordings', [RecordingController::class, 'lesson']);
        Route::get('/recordings', [RecordingController::class, 'index']);
        Route::get('/recordings/{recording}/file', [RecordingController::class, 'file']);

        Route::get('/calendar', CalendarController::class);
        Route::get('/semesters', [SemesterController::class, 'index']);
        Route::get('/series', [LessonSeriesController::class, 'index']);
        Route::post('/series', [LessonSeriesController::class, 'store']);
        Route::patch('/series/{series}', [LessonSeriesController::class, 'update']);
        Route::post('/series/{series}/cancel', [LessonSeriesController::class, 'cancel']);
        Route::delete('/series/{series}', [LessonSeriesController::class, 'destroy']);
        Route::post('/series/{series}/skips', [LessonSeriesController::class, 'skip']);
        Route::delete('/series/{series}/skips', [LessonSeriesController::class, 'unskip']);
        Route::get('/lessons/{lesson}/attendance', [LessonAttendanceController::class, 'show']);
        Route::get('/lessons/{lesson}/attendance/export', [LessonAttendanceController::class, 'export']);
        Route::post('/lessons/{lesson}/attendance/sync', [LessonAttendanceController::class, 'sync'])->middleware('throttle:10,1');

        Route::middleware('role:education,super_admin')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::get('/users/counts', [UserController::class, 'counts']);
            Route::patch('/users/{user}', [UserController::class, 'update']);
            Route::delete('/users/{user}', [UserController::class, 'destroy']);

            Route::get('/groups', [GroupController::class, 'index']);
            Route::post('/groups', [GroupController::class, 'store']);
            Route::put('/groups/{group}', [GroupController::class, 'update']);
            Route::post('/groups/{group}/toggle', [GroupController::class, 'toggle']);
            Route::delete('/groups/{group}', [GroupController::class, 'destroy']);

            Route::get('/groups/{group}/students', [StudentController::class, 'index']);
            Route::post('/groups/{group}/students/merge', [StudentController::class, 'merge']);
            Route::post('/groups/{group}/students/hemis-sync', [StudentController::class, 'hemisSync'])->middleware('throttle:10,1');
            Route::patch('/students/{student}', [StudentController::class, 'update']);

            Route::get('/reports/summary', [ReportController::class, 'summary']);
            Route::get('/reports/semester', [ReportController::class, 'semester']);
            Route::get('/reports/semester/export', [ReportController::class, 'semesterExport']);
            Route::get('/academic-years', [AcademicYearController::class, 'index']);
            Route::get('/monitor', [MonitorController::class, 'index']);
        });

        Route::middleware('role:super_admin')->group(function () {
            Route::get('/zoom-accounts', [ZoomAccountController::class, 'index']);
            Route::post('/zoom-accounts', [ZoomAccountController::class, 'store']);
            Route::put('/zoom-accounts/{zoomAccount}', [ZoomAccountController::class, 'update']);
            Route::delete('/zoom-accounts/{zoomAccount}', [ZoomAccountController::class, 'destroy']);
            Route::post('/zoom-accounts/{zoomAccount}/test', [ZoomAccountController::class, 'test']);
            Route::get('/zoom-accounts/{zoomAccount}/users', [ZoomAccountController::class, 'users']);
            Route::post('/zoom-accounts/{zoomAccount}/sync-hosts', [ZoomHostController::class, 'sync']);
            Route::get('/zoom-hosts', [ZoomHostController::class, 'index']);
            Route::patch('/zoom-hosts/{zoomHost}', [ZoomHostController::class, 'update']);

            Route::get('/lesson-slots', [LessonSlotController::class, 'index']);
            Route::post('/lesson-slots', [LessonSlotController::class, 'store']);
            Route::put('/lesson-slots/{lessonSlot}', [LessonSlotController::class, 'update']);
            Route::delete('/lesson-slots/{lessonSlot}', [LessonSlotController::class, 'destroy']);

            Route::get('/audit-logs', AuditLogController::class);

            Route::post('/academic-years', [AcademicYearController::class, 'store']);
            Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update']);
            Route::patch('/academic-years/{academicYear}/status', [AcademicYearController::class, 'status']);
            Route::delete('/academic-years/{academicYear}', [AcademicYearController::class, 'destroy']);
            Route::get('/settings', [SettingsController::class, 'show']);
            Route::put('/settings', [SettingsController::class, 'update']);
            Route::post('/recordings/{recording}/retry', [RecordingController::class, 'retry']);
        });
    });
});
