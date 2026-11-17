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

        $query = User::query()->visibleTo($request->user())->withCount('lessons');

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['role'])) {
            $query->withRole($data['role']);
        }
        if (! empty($data['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $data['search']).'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $term)->orWhere('hemis_id', 'like', $term));
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
        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => [Rule::in(Role::values())],
            'hemis_id' => ['sometimes', 'nullable', 'string', 'max:64', Rule::unique('users', 'hemis_id')->ignore($user->id)],
            // Ism-sharif vaqtincha to'g'rilanadi: foydalanuvchi HEMIS orqali kirganda yana HEMIS'dagi qiymat olinadi.
            'last_name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'first_name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'can_manage_calendar' => ['sometimes', 'boolean'],
        ], ['roles.min' => __('Kamida bitta rol tanlang.'), 'hemis_id.unique' => __('Bu HEMIS ID boshqa foydalanuvchiga biriktirilgan.')]);

        if ($user->isProtectedAdmin()) {
            $blocked = array_filter([
                'status' => isset($data['status']) && $data['status'] !== UserStatus::Active->value,
                // Administrator rolini olib tashlab bo'lmaydi (qo'shish mumkin).
                'roles' => isset($data['roles']) && $user->isSuperAdmin() && ! in_array(Role::SuperAdmin->value, $data['roles'], true),
                'hemis_id' => array_key_exists('hemis_id', $data) && (string) $data['hemis_id'] !== (string) $user->hemis_id,
            ]);
            if ($blocked) {
                throw ValidationException::withMessages(array_map(fn () => self::protectedMessage(), $blocked));
            }
        }

        if (array_key_exists('status', $data)) {
            Gate::authorize('updateStatus', $user);
            $user->status = UserStatus::from($data['status']);
        }

        if (array_key_exists('roles', $data) || array_key_exists('hemis_id', $data)) {
            Gate::authorize('updateRoles', $user);
            if (isset($data['roles'])) {
                $user->roles = array_values(array_unique($data['roles']));
            }
            if (array_key_exists('hemis_id', $data)) {
                $user->hemis_id = $data['hemis_id'] ?: null;
            }
        }

        if (array_intersect(['last_name', 'first_name', 'middle_name', 'can_manage_calendar'], array_keys($data))) {
            Gate::authorize('updateProfile', $user);
            $user->fill(array_intersect_key($data, array_flip(['last_name', 'first_name', 'middle_name', 'can_manage_calendar'])));
            if (array_intersect(['last_name', 'first_name', 'middle_name'], array_keys($data))) {
                $user->name = User::fullName($user->last_name, $user->first_name, $user->middle_name);
            }
        }

        $changes = array_keys($user->getDirty());
        $activated = $user->isDirty('status') && $user->status === UserStatus::Active;
        $user->save();

        if ($changes) {
            Audit::log('user.updated', 'Foydalanuvchi maʼlumotlari oʻzgartirildi: :name (:fields)', $user, replace: ['name' => $user->displayName(), 'fields' => implode(', ', $changes)], properties: [
                'status' => $user->status->value,
                'roles' => $user->roles,
            ]);
        }
        if ($activated) {
            $notifier->userActivated($user);
        }

        return new UserResource($user->loadCount('lessons'));
    }

    public function destroy(Request $request, User $user): Response
    {
        Gate::authorize('delete', $user);
        if ($user->isProtectedAdmin()) {
            throw ValidationException::withMessages(['user' => self::protectedMessage()]);
        }
        $user->forceFill(['status' => UserStatus::Blocked, 'telegram_chat_id' => null])->save();
        $user->delete();
        Audit::log('user.deleted', 'Foydalanuvchi oʻchirildi: :name', $user, replace: ['name' => $user->displayName()]);

        return response()->noContent();
    }

    private static function protectedMessage(): string
    {
        return __('Bu administrator server sozlamasida himoyalangan: uning Administrator rolini olib, bloklab, oʻchirib yoki HEMIS ID sini oʻzgartirib boʻlmaydi.');
    }
}
