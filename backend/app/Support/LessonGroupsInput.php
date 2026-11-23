<?php

namespace App\Support;

use App\Models\Group;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Dars (yoki haftalik dars) guruhlari so'rovdan: oddiy dars — group_id,
 * ma'ruza (is_lecture) — group_ids (birinchisi asosiy guruh).
 */
final class LessonGroupsInput
{
    /** @return array{0: Collection<int, Group>, 1: bool} */
    public static function from(Request $request): array
    {
        $exists = Rule::exists('groups', 'id')->whereNull('deleted_at');
        if (! $request->boolean('is_lecture')) {
            $data = $request->validate(['group_id' => ['required', 'integer', $exists]], [
                'group_id.required' => __('Guruhni tanlang.'),
                'group_id.exists' => __('Tanlangan guruh topilmadi.'),
            ]);

            return [collect([Group::findOrFail($data['group_id'])]), false];
        }

        $data = $request->validate([
            'group_ids' => ['required', 'array', 'min:1', 'max:'.Lesson::MAX_LECTURE_GROUPS],
            'group_ids.*' => ['integer', 'distinct', $exists],
        ], [
            'group_ids.required' => __('Maʼruza guruhlarini tanlang.'),
            'group_ids.min' => __('Maʼruza guruhlarini tanlang.'),
            'group_ids.max' => __('Maʼruzaga koʻpi bilan :max ta guruh qoʻshiladi.'),
            'group_ids.*.distinct' => __('Guruh ikki marta tanlangan.'),
            'group_ids.*.exists' => __('Tanlangan guruh topilmadi.'),
        ]);
        $ids = array_map('intval', $data['group_ids']);
        $groups = Group::query()->whereIn('id', $ids)->get()->sortBy(fn (Group $group) => array_search($group->id, $ids, true))->values();

        return [$groups, true];
    }
}
