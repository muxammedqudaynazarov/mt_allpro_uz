<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $data = $request->validate([
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'role' => ['nullable', Rule::in(Role::values())],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $query = User::query()->visibleTo($request->user())->with('managedBy')->withCount('lessons');

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['role'])) {
            $query->withRole($data['role']);
        }
        if (! empty($data['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $data['search']).'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('hemis_id', 'like', $term));
        }

        return UserResource::collection(
            $query->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'active' THEN 1 ELSE 2 END")
                ->orderBy('name')
                ->paginate($data['per_page'] ?? 20)
                ->withQueryString()
        );
    }

    public function counts(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);
        $base = fn () => User::query()->visibleTo($request->user());

        return response()->json(['data' => [
            'all' => $base()->count(),
            'pending' => $base()->where('status', UserStatus::Pending->value)->count(),
            'active' => $base()->where('status', UserStatus::Active->value)->count(),
            'blocked' => $base()->where('status', UserStatus::Blocked->value)->count(),
        ]]);
    }

    public function update(Request $request, User $user, Notifier $notifier): UserResource
    {
        $actor = $request->user();
        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => [Rule::in(Role::values())],
            'managed_by_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'hemis_id' => ['sometimes', 'nullable', 'string', 'max:64', Rule::unique('users', 'hemis_id')->ignore($user->id)],
        ], ['roles.min' => __('Kamida bitta rol tanlang.'), 'hemis_id.unique' => __('Bu HEMIS ID boshqa xodimga biriktirilgan.')]);

        if (array_key_exists('status', $data)) {
            Gate::authorize('updateStatus', $user);
            $previous = $user->status;
            $user->status = UserStatus::from($data['status']);
            // Administrator tasdiqlagan xodim unga biriktiriladi.
            if ($previous === UserStatus::Pending && $user->status === UserStatus::Active && ! $user->managed_by_id && ! $actor->isSuperAdmin()) {
                $user->managed_by_id = $actor->id;
            }
        }

        if (array_key_exists('roles', $data) || array_key_exists('managed_by_id', $data) || array_key_exists('hemis_id', $data)) {
            Gate::authorize('updateRoles', $user);
            if (isset($data['roles'])) {
                $user->roles = array_values(array_unique($data['roles']));
            }
            if (array_key_exists('managed_by_id', $data)) {
                if ($data['managed_by_id'] && ($data['managed_by_id'] === $user->id || ! User::find($data['managed_by_id'])?->isAdmin())) {
                    throw ValidationException::withMessages(['managed_by_id' => __('Masʼul sifatida boshqa administratorni tanlang.')]);
                }
                $user->managed_by_id = $data['managed_by_id'];
            }
            if (array_key_exists('hemis_id', $data)) {
                $user->hemis_id = $data['hemis_id'] ?: null;
            }
        }

        $changes = array_keys($user->getDirty());
        $activated = $user->isDirty('status') && $user->status === UserStatus::Active;
        $user->save();

        if ($changes) {
            Audit::log('user.updated', 'Xodim maʼlumotlari oʻzgartirildi: :name (:fields)', $user, replace: ['name' => $user->name, 'fields' => implode(', ', $changes)], properties: [
                'status' => $user->status->value,
                'roles' => $user->roles,
            ]);
        }
        if ($activated) {
            $notifier->userActivated($user);
        }

        return new UserResource($user->load('managedBy')->loadCount('lessons'));
    }

    public function destroy(Request $request, User $user): Response
    {
        Gate::authorize('delete', $user);
        $user->forceFill(['status' => UserStatus::Blocked, 'telegram_chat_id' => null])->save();
        $user->delete();
        Audit::log('user.deleted', 'Xodim oʻchirildi: :name', $user, replace: ['name' => $user->name]);

        return response()->noContent();
    }
}
