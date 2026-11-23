<?php

namespace App\Support;

use App\Models\Group;

/** API javoblaridagi qisqa guruh ma'lumoti. */
final class GroupRef
{
    /** @return array{id: int, name: string, course: int} */
    public static function make(Group $group): array
    {
        return ['id' => $group->id, 'name' => $group->name, 'course' => $group->course];
    }

    /**
     * @param  iterable<Group>  $groups
     * @return list<array{id: int, name: string, course: int}>
     */
    public static function list(iterable $groups): array
    {
        return collect($groups)->map(fn (Group $group) => self::make($group))->values()->all();
    }
}
