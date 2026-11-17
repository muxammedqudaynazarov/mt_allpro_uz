<?php

namespace App\Console\Commands;

use App\Exceptions\ExternalServiceException;
use App\Services\Hemis\HemisStudentApi;
use Illuminate\Console\Command;

/**
 * Serverda HEMIS REST API sozlamasini tekshirish: berilgan HEMIS ID bo'yicha
 * qaysi maydonlar kelayotgani va talaba topilganini ko'rsatadi.
 */
class HemisStudent extends Command
{
    protected $signature = 'hemis:student {hemis_id : Talabaning HEMIS ID raqami}';

    protected $description = 'HEMIS API orqali talabani topishni tekshirish (HEMIS_API_TOKEN)';

    public function handle(HemisStudentApi $api): int
    {
        if (! $api->enabled()) {
            $this->error('HEMIS_API_TOKEN .env faylida koʻrsatilmagan.');

            return self::FAILURE;
        }
        try {
            $probe = $api->probe((string) $this->argument('hemis_id'));
        } catch (ExternalServiceException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('HTTP: '.$probe['status'].', yozuvlar: '.$probe['count']);
        $this->line('Maydonlar: '.($probe['keys'] ? implode(', ', $probe['keys']) : '—'));
        if (! $probe['match']) {
            $this->warn('Bu ID bilan aniq mos talaba topilmadi (student_id_number yoki id). HEMIS_STUDENT_ENDPOINT / HEMIS_STUDENT_SEARCH_PARAM ni tekshiring.');

            return self::FAILURE;
        }
        $this->info('Topildi: '.$probe['match']['name'].($probe['match']['picture'] ? ' (rasm bor)' : ''));

        return self::SUCCESS;
    }
}
