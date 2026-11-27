<?php

namespace App\Actions;

use App\Exceptions\ExternalServiceException;
use App\Models\Group;
use App\Models\Student;
use App\Services\Hemis\HemisStudentApi;
use App\Support\Audit;
use Illuminate\Validation\ValidationException;

/**
 * "HEMIS SYNC": guruhda HEMIS ID kiritilgan, lekin hali so'ralmagan yoki o'zgargan
 * talabalar uchun HEMIS'dan haqiqiy F.I.Sh. va rasm olinadi. Avval olingan
 * (ID o'zgarmagan) ma'lumot qayta so'ralmaydi.
 */
class SyncStudentsFromHemis
{
    public function __construct(private readonly HemisStudentApi $api) {}

    /** @return array{synced: int, not_found: int, failed: int} */
    public function handle(Group $group): array
    {
        if (! $this->api->enabled()) {
            throw ValidationException::withMessages(['hemis' => __('HEMIS_API_TOKEN sozlanmagan (.env faylida).')]);
        }

        $students = Student::query()
            ->where('group_id', $group->id)
            ->whereNotNull('hemis_id')
            ->where('is_ignored', false)
            ->where(fn ($q) => $q->whereNull('hemis_synced_id')->orWhereColumn('hemis_synced_id', '!=', 'hemis_id'))
            ->get();

        $result = ['synced' => 0, 'not_found' => 0, 'failed' => 0];
        foreach ($students as $student) {
            try {
                $found = $this->api->find($student->hemis_id);
            } catch (ExternalServiceException $e) {
                // Token xatosi — barcha so'rovlar xato bo'ladi, darhol to'xtatamiz.
                if ($e->getCode() === 422) {
                    throw ValidationException::withMessages(['hemis' => $e->getMessage()]);
                }
                $student->forceFill(['hemis_error' => mb_substr($e->getMessage(), 0, 191)])->save();
                $result['failed']++;

                continue;
            }

            if ($found) {
                $student->forceFill([
                    'name' => mb_substr($found['name'], 0, 120),
                    'picture_url' => $found['picture'],
                    'hemis_synced_id' => $student->hemis_id,
                    'hemis_synced_at' => now(),
                    'hemis_error' => null,
                ])->save();
                $result['synced']++;
            } else {
                // Topilmagan ID ham qayta so'ralmaydi — faqat o'zgartirilganda.
                $student->forceFill(['hemis_synced_id' => $student->hemis_id, 'hemis_synced_at' => now(), 'hemis_error' => __('HEMIS’da topilmadi')])->save();
                $result['not_found']++;
            }
        }

        if ($students->isNotEmpty()) {
            Audit::log('students.hemis_sync', 'HEMIS’dan talabalar yangilandi: :group (:count)', $group, $result, replace: ['group' => $group->name, 'count' => $result['synced']]);
        }

        return $result;
    }
}
