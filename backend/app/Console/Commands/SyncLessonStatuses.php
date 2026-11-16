<?php

namespace App\Console\Commands;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use Illuminate\Console\Command;

/** Har daqiqada: boshlangan darslarni "live", vaqti tugaganlarini "finished" qiladi. */
class SyncLessonStatuses extends Command
{
    protected $signature = 'lessons:sync-statuses';

    protected $description = 'Dars holatlarini vaqtga qarab yangilaydi';

    public function handle(): int
    {
        $finished = Lesson::query()
            ->whereIn('status', LessonStatus::openValues())
            ->where('ends_at', '<=', now())
            ->update(['status' => LessonStatus::Finished->value, 'start_url' => null]);

        $live = Lesson::query()
            ->where('status', LessonStatus::Scheduled->value)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->update(['status' => LessonStatus::Live->value]);

        if ($finished || $live) {
            $this->info("Yakunlandi: {$finished}, boshlandi: {$live}");
        }

        return self::SUCCESS;
    }
}
