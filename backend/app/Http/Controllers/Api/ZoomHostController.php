<?php

namespace App\Http\Controllers\Api;

use App\Actions\SyncZoomHosts;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\ZoomAccount;
use App\Models\ZoomHost;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Dars o'tkaziladigan Zoom hostlar (litsenziyalar) — faqat Administrator. */
class ZoomHostController extends Controller
{
    public function index(): JsonResponse
    {
        $upcoming = Lesson::query()->open()->selectRaw('zoom_account_id, zoom_host_email, count(*) as total')
            ->groupBy('zoom_account_id', 'zoom_host_email')->get()
            ->mapWithKeys(fn ($row) => [$row->zoom_account_id.'|'.mb_strtolower($row->zoom_host_email) => (int) $row->total]);

        $hosts = ZoomHost::query()->with('zoomAccount:id,name,is_active')->orderBy('zoom_account_id')->orderBy('name')->get()
            ->map(fn (ZoomHost $host) => $this->present($host, $upcoming->get($host->zoom_account_id.'|'.$host->email, 0)));

        return response()->json(['data' => $hosts]);
    }

    public function sync(ZoomAccount $zoomAccount, SyncZoomHosts $action): JsonResponse
    {
        $count = $action->handle($zoomAccount);
        $zoomAccount->forceFill(['last_checked_at' => now(), 'last_error' => null])->save();
        Audit::log('zoom.hosts-synced', 'Zoom hostlar yangilandi: :name', $zoomAccount, ['users' => $count], replace: ['name' => $zoomAccount->name]);

        return response()->json(['data' => ['users' => $count]]);
    }

    public function update(Request $request, ZoomHost $zoomHost): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'string', 'max:191'],
        ]);
        $zoomHost->update($data);
        Audit::log('zoom.host-updated', 'Zoom host oʻzgartirildi: :email', $zoomHost, $zoomHost->getChanges(), replace: ['email' => $zoomHost->email]);

        return response()->json(['data' => $this->present($zoomHost->load('zoomAccount:id,name,is_active'), 0)]);
    }

    /** @return array<string, mixed> */
    private function present(ZoomHost $host, int $openLessons): array
    {
        return [
            'id' => $host->id,
            'name' => $host->name,
            'email' => $host->email,
            'licensed' => $host->isLicensed(),
            'is_active' => $host->is_active,
            'account' => ['id' => $host->zoom_account_id, 'name' => $host->zoomAccount?->name, 'is_active' => (bool) $host->zoomAccount?->is_active],
            'open_lessons' => $openLessons,
        ];
    }
}
