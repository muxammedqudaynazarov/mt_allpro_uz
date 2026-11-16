<?php

use Illuminate\Support\Facades\Schedule;

// Serverda bitta cron yozuvi kerak: * * * * * php /path/backend/artisan schedule:run
Schedule::command('lessons:sync-statuses')->everyMinute()->withoutOverlapping();
Schedule::command('model:prune')->daily();
Schedule::command('queue:prune-failed --hours=720')->daily();
