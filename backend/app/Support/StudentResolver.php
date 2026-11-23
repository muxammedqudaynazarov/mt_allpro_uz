<?php

namespace App\Support;

use App\Models\Student;
use App\Models\StudentAlias;
use Illuminate\Support\Collection;

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

    /** @var array<string, Collection<string, Collection<int, Student>>> */
    private array $names = [];

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

    /**
     * Ma'ruza uchun: qatnashchini bir nechta guruh talabalari orasidan qidiradi. Yangi talaba
     * yaratilmaydi — qaysi guruhga tegishli ekani noma'lum. Kalit bir nechta guruhda uchrasa
     * (masalan, adash talabalar) — keyingi kalitga o'tiladi. Topilmasa — null ("guruhi aniqlanmagan").
     *
     * @param  list<int>  $groupIds
     */
    public function find(array $groupIds, string $name, ?string $email = null, ?string $zoomId = null): ?Student
    {
        $keys = array_filter([
            StudentAlias::ZOOM_ID => $zoomId ? substr($zoomId, 0, 191) : null,
            StudentAlias::EMAIL => $email ? StudentAlias::emailKey($email) : null,
            StudentAlias::NAME => StudentName::key($name),
        ]);

        $student = null;
        foreach ($keys as $type => $value) {
            $ids = StudentAlias::query()->whereIn('group_id', $groupIds)->where(['type' => $type, 'value' => $value])->distinct()->pluck('student_id');
            if ($ids->count() === 1 && $student = Student::find($ids->first())) {
                break;
            }
        }

        // Kalit hali eslab qolinmagan bo'lsa — talabaning hozirgi ismi (masalan, HEMIS'dagi F.I.Sh.) bo'yicha.
        if (! $student && ($nameKey = $keys[StudentAlias::NAME] ?? null)) {
            $this->names[implode(',', $groupIds)] ??= Student::query()->whereIn('group_id', $groupIds)->get()->groupBy(fn (Student $item) => StudentName::key($item->name));
            $same = $this->names[implode(',', $groupIds)]->get($nameKey);
            $student = $same && $same->count() === 1 ? $same->first() : null;
        }
        if (! $student) {
            return null;
        }

        $now = now();
        StudentAlias::query()->insertOrIgnore(array_map(fn (string $type, string $value) => [
            'student_id' => $student->id,
            'group_id' => $student->group_id,
            'type' => $type,
            'value' => $value,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys($keys), array_values($keys)));

        return $student;
    }
}
