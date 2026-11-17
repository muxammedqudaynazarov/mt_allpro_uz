<?php

use Illuminate\Support\Facades\Schedule;

// Serverda bitta cron yozuvi kerak: * * * * * php /path/backend/artisan schedule:run
Schedule::command('lessons:sync-statuses')->everyMinute()->withoutOverlapping();
// Zoom'dagi haqiqiy holat (boshlandi/tugadi) va davomat. Zoom'ga so'rovlar bir necha soniya olishi mumkin.
Schedule::command('lessons:track-zoom')->everyMinute()->withoutOverlapping(15)->runInBackground();
// Har haftalik darslar: dars kuni, boshlanishidan 1 soat oldin Zoom uchrashuvi yaratiladi.
Schedule::command('lessons:materialize-series')->everyMinute()->withoutOverlapping(10)->runInBackground();
Schedule::command('model:prune')->daily();
Schedule::command('queue:prune-failed --hours=720')->daily();
