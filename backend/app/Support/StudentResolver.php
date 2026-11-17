<?php

namespace App\Support;

use App\Models\Student;
use App\Models\StudentAlias;

/**
 * Zoom qatnashchisini guruh talabasiga bog'laydi. Tartib: Zoom ID (talaba Zoom'ga
 * kirgan bo'lsa) → email → normallashtirilgan ism. Topilmasa, yangi talaba yaratiladi.
 * Topilgan talabaga yangi kalitlar (masalan, Zoom ID) qo'shib boriladi — keyingi
 * darslarda u ismini o'zgartirsa ham tanib olinadi.
 */
class StudentResolver
{
    /** @var array<string, Student> */
    private array $cache = [];

    public function resolve(int $groupId, string $name, ?string $email = null, ?string $zoomId = null): Student
    {
        $keys = array_filter([
            StudentAlias::ZOOM_ID => $zoomId ? substr($zoomId, 0, 191) : null,
            // Email ochiq holda saqlanmaydi — faqat bir tomonlama xesh (tanish uchun yetarli).
            StudentAlias::EMAIL => $email ? StudentAlias::emailKey($email) : null,
            StudentAlias::NAME => StudentName::key($name),
        ]);

        $cacheKey = $groupId.'|'.json_encode($keys);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $student = null;
        foreach ($keys as $type => $value) {
            $alias = StudentAlias::query()->where(['group_id' => $groupId, 'type' => $type, 'value' => $value])->first();
            if ($alias && $student = Student::find($alias->student_id)) {
                break;
            }
        }

        $student ??= Student::create(['group_id' => $groupId, 'name' => StudentName::clean($name)]);

        // Yangi kalitlarni eslab qolamiz. Kalit boshqa talabaga tegishli bo'lsa, tegmaymiz.
        $now = now();
        StudentAlias::query()->insertOrIgnore(array_map(fn (string $type, string $value) => [
            'student_id' => $student->id,
            'group_id' => $groupId,
            'type' => $type,
            'value' => $value,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys($keys), array_values($keys)));

        return $this->cache[$cacheKey] = $student;
    }
}
