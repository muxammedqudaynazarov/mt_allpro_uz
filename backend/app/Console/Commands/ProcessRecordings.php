<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use App\Models\Recording;
use App\Services\Zoom\RecordingSync;
use Illuminate\Console\Command;

class ProcessRecordings extends Command
{
    protected $signature = 'recordings:process {--limit=3 : Bir ishga tushishda yuklanadigan fayllar}';

    protected $description = 'Zoom video yozuvlarini serverga yuklaydi va Zoom bulutidan o‘chiradi';

    public function handle(RecordingSync $sync): int
    {
        // Yozuv yoqilmagan va hech qachon yoqilmagan bo'lsa — hech narsa qilinmaydi.
        if (! Lesson::query()->where('record_cloud', true)->exists() && ! Recording::query()->exists()) {
            return self::SUCCESS;
        }

        $found = $sync->poll();
        $result = $sync->download((int) $this->option('limit'));
        $purged = $sync->purge();

        if ($found || $result['downloaded'] || $result['failed'] || $purged) {
            $this->info("Topildi: {$found}, yuklandi: {$result['downloaded']}, xato: {$result['failed']}, Zoom'dan o'chirildi: {$purged}");
        }

        return self::SUCCESS;
    }
}
