<?php

namespace App\Console\Commands;

use App\Actions\MaterializeSeries;
use Illuminate\Console\Command;

class MaterializeLessonSeries extends Command
{
    protected $signature = 'lessons:materialize-series';

    protected $description = 'Har haftalik darslar uchun bugungi Zoom uchrashuvlarini yaratadi (dars boshlanishidan 1 soat oldin)';

    public function handle(MaterializeSeries $action): int
    {
        $result = $action->run();
        if ($result['created'] || $result['failed']) {
            $this->info("Yaratildi: {$result['created']}, xato: {$result['failed']}");
        }

        return self::SUCCESS;
    }
}
