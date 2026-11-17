<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ZoomAccountResource;
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
        return ZoomAccountResource::collection(ZoomAccount::withCount('groups')->orderBy('name')->get());
    }

    public function store(Request $request): ZoomAccountResource
    {
        $account = ZoomAccount::create($this->validated($request));
        Audit::log('zoom.created', 'Zoom akkaunt qoʻshildi: :name', $account, replace: ['name' => $account->name]);

        return new ZoomAccountResource($account->loadCount('groups'));
    }

    public function update(Request $request, ZoomAccount $zoomAccount): ZoomAccountResource
    {
        $data = $this->validated($request, $zoomAccount);
        if (empty($data['client_secret'])) {
            unset($data['client_secret']);
        }
        $zoomAccount->update($data);
        Audit::log('zoom.updated', 'Zoom akkaunt tahrirlandi: :name', $zoomAccount, replace: ['name' => $zoomAccount->name], properties: [
            'secret_changed' => array_key_exists('client_secret', $zoomAccount->getChanges()),
        ]);

        return new ZoomAccountResource($zoomAccount->loadCount('groups'));
    }

    public function destroy(ZoomAccount $zoomAccount): Response
    {
        if ($zoomAccount->groups()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['account' => __('Akkauntga guruhlar biriktirilgan. Avval ularni boshqa akkauntga oʻtkazing.')]);
        }
        $zoomAccount->delete();
        Audit::log('zoom.deleted', 'Zoom akkaunt oʻchirildi: :name', $zoomAccount, replace: ['name' => $zoomAccount->name]);

        return response()->noContent();
    }

    public function test(ZoomAccount $zoomAccount, ZoomClient $zoom): JsonResponse
    {
        $zoom->token($zoomAccount, fresh: true);
        $users = $zoom->users($zoomAccount);
        $zoomAccount->forceFill(['last_checked_at' => now(), 'last_error' => null])->save();

        return response()->json(['data' => ['users' => count($users)]]);
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
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'client_id.unique' => __('Bu akkaunt allaqachon qoʻshilgan.'),
            'client_secret.required' => __('Client Secret kiritilishi shart.'),
        ]);
    }
}
