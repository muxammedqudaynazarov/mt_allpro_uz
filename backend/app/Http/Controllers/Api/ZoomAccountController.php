<?php

namespace App\Http\Controllers\Api;

use App\Actions\SyncZoomHosts;
use App\Http\Controllers\Controller;
use App\Http\Resources\ZoomAccountResource;
use App\Models\Lesson;
use App\Models\ZoomAccount;
use App\Services\Zoom\ZoomClient;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ZoomAccountController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ZoomAccountResource::collection(ZoomAccount::withCount(['groups', 'hosts'])->orderBy('name')->get());
    }

    public function store(Request $request): ZoomAccountResource
    {
        $account = ZoomAccount::create($this->validated($request));
        Audit::log('zoom.created', 'Zoom akkaunt qoʻshildi: :name', $account, replace: ['name' => $account->name]);

        return new ZoomAccountResource($account->loadCount(['groups', 'hosts']));
    }

    public function update(Request $request, ZoomAccount $zoomAccount): ZoomAccountResource
    {
        $data = $this->validated($request, $zoomAccount);
        foreach (['client_secret', 'webhook_secret'] as $secret) {
            if (empty($data[$secret])) {
                unset($data[$secret]);
            }
        }
        if ($request->boolean('remove_webhook_secret')) {
            $data['webhook_secret'] = null;
            $zoomAccount->webhook_received_at = null;
        }
        $zoomAccount->update($data);
        Audit::log('zoom.updated', 'Zoom akkaunt tahrirlandi: :name', $zoomAccount, replace: ['name' => $zoomAccount->name], properties: [
            'secret_changed' => array_key_exists('client_secret', $zoomAccount->getChanges()),
            'webhook_secret_changed' => array_key_exists('webhook_secret', $zoomAccount->getChanges()),
        ]);

        return new ZoomAccountResource($zoomAccount->loadCount(['groups', 'hosts']));
    }

    public function destroy(ZoomAccount $zoomAccount): Response
    {
        if ($zoomAccount->groups()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['account' => __('Akkauntga guruhlar biriktirilgan. Avval ularni boshqa akkauntga oʻtkazing.')]);
        }
        if (Lesson::query()->open()->where('zoom_account_id', $zoomAccount->id)->exists()) {
            throw ValidationException::withMessages(['account' => __('Bu akkauntda yakunlanmagan darslar bor. Avval akkauntni nofaol qiling.')]);
        }
        $zoomAccount->delete();
        Audit::log('zoom.deleted', 'Zoom akkaunt oʻchirildi: :name', $zoomAccount, replace: ['name' => $zoomAccount->name]);

        return response()->noContent();
    }

    /** Ulanishni tekshiradi va shu bilan hostlar ro'yxatini yangilaydi. */
    public function test(ZoomAccount $zoomAccount, ZoomClient $zoom, SyncZoomHosts $hosts): JsonResponse
    {
        $zoom->token($zoomAccount, fresh: true);
        $count = $hosts->handle($zoomAccount);
        $zoomAccount->forceFill(['last_checked_at' => now(), 'last_error' => null])->save();

        return response()->json(['data' => ['users' => $count]]);
    }

    public function users(ZoomAccount $zoomAccount, ZoomClient $zoom): JsonResponse
    {
        $users = Cache::remember('zoom:users:'.$zoomAccount->id, now()->addMinutes(10), fn () => $zoom->users($zoomAccount));

        return response()->json(['data' => $users]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ZoomAccount $account = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'string', 'max:128'],
            'client_id' => ['required', 'string', 'max:128', Rule::unique('zoom_accounts')->where('account_id', $request->input('account_id'))->ignore($account?->id)],
            'client_secret' => [$account ? 'nullable' : 'required', 'string', 'max:255'],
            // Zoom ilovasi → Feature → Event Subscriptions → Secret Token.
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'client_id.unique' => __('Bu akkaunt allaqachon qoʻshilgan.'),
            'client_secret.required' => __('Client Secret kiritilishi shart.'),
        ]);
    }
}
