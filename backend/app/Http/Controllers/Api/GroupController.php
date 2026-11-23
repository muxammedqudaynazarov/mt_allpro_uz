<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\Lesson;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Group::class);

        $groups = Group::query()
            ->visibleTo($request->user())
            ->with(['zoomAccount'])
            ->withCount(['lessons', 'lessons as live_lessons_count' => fn ($q) => $q->live()])
            ->orderBy('course')
            ->orderBy('name')
            ->get();

        return GroupResource::collection($groups);
    }

    public function store(Request $request): GroupResource
    {
        Gate::authorize('create', Group::class);
        $group = Group::create($this->validated($request));
        Audit::log('group.created', 'Guruh qoʻshildi: :name', $group, replace: ['name' => $group->name]);

        return new GroupResource($group->load(['zoomAccount']));
    }

    public function update(Request $request, Group $group): GroupResource
    {
        Gate::authorize('update', $group);
        $group->update($this->validated($request, $group));
        Audit::log('group.updated', 'Guruh tahrirlandi: :name', $group, ['changes' => array_keys($group->getChanges())], replace: ['name' => $group->name]);

        return new GroupResource($group->load(['zoomAccount'])->loadCount('lessons'));
    }

    public function toggle(Group $group): GroupResource
    {
        Gate::authorize('toggle', $group);
        $group->update(['is_active' => ! $group->is_active]);
        Audit::log('group.toggled', $group->is_active ? 'Guruh faollashtirildi: :name' : 'Guruh oʻchirib qoʻyildi: :name', $group, replace: ['name' => $group->name]);

        return new GroupResource($group->load(['zoomAccount'])->loadCount('lessons'));
    }

    public function destroy(Group $group): Response
    {
        Gate::authorize('delete', $group);
        if (Lesson::query()->forGroup($group->id)->open()->exists()) {
            throw ValidationException::withMessages(['group' => __('Guruhda davom etayotgan yoki rejalashtirilgan dars bor. Avval uni yakunlang.')]);
        }
        $group->delete();
        Audit::log('group.deleted', 'Guruh oʻchirildi: :name', $group, replace: ['name' => $group->name]);

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Group $group = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'hemis_id' => ['nullable', 'string', 'max:64', Rule::unique('groups', 'hemis_id')->ignore($group?->id)],
            'course' => ['required', 'integer', 'between:1,6'],
            // Standart host (ixtiyoriy): o'qituvchi dars yaratishda boshqasini tanlashi mumkin.
            'zoom_account_id' => ['nullable', 'required_with:zoom_user_email', 'integer', Rule::exists('zoom_accounts', 'id')],
            'zoom_user_email' => ['nullable', 'required_with:zoom_account_id', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => __('Guruh nomini kiriting.'),
            'hemis_id.unique' => __('Bu HEMIS ID boshqa guruhga biriktirilgan.'),
            'zoom_account_id.required' => __('Zoom akkauntni tanlang.'),
            'zoom_user_email.required' => __('Zoom foydalanuvchisi emailini kiriting.'),
            'zoom_user_email.email' => __('Email notoʻgʻri.'),
        ]);

        $data['zoom_user_email'] = isset($data['zoom_user_email']) ? mb_strtolower(trim($data['zoom_user_email'])) : null;

        return $data;
    }
}
