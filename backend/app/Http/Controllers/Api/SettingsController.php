<?php

namespace App\Http\Controllers\Api;

use App\Enums\Assessment;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Audit;
use App\Support\HostDisplayName;
use App\Support\RecordingSettings;
use App\Support\ZoomAutoEnd;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Tizim sozlamalari (faqat Administrator). */
class SettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payload()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assessment' => ['sometimes', 'array'],
            'assessment.basis' => ['required_with:assessment', Rule::in([Assessment::BASIS_TIME, Assessment::BASIS_LESSONS])],
            'assessment.presence_percent' => ['required_with:assessment', 'integer', 'between:1,100'],
            'assessment.thresholds' => ['required_with:assessment', 'array'],
            'assessment.thresholds.*' => ['integer', 'between:0,100'],
            'recording' => ['sometimes', 'array'],
            'recording.enabled' => ['required_with:recording', 'boolean'],
            'recording.delete_from_zoom' => ['required_with:recording', 'boolean'],
            'recording.delete_action' => ['required_with:recording', Rule::in(['trash', 'delete'])],
            'recording.include_audio' => ['required_with:recording', 'boolean'],
            'zoom' => ['sometimes', 'array'],
            'zoom.auto_end_percent' => ['sometimes', 'integer', 'between:0,100'],
            'zoom.rename_host' => ['sometimes', 'boolean'],
        ], [
            'zoom.auto_end_percent.between' => __('Foiz 0 dan 100 gacha boʻlishi kerak.'),
            'zoom.auto_end_percent.integer' => __('Foiz butun son boʻlishi kerak.'),
        ]);

        if (isset($data['zoom'])) {
            $current = ['auto_end_percent' => ZoomAutoEnd::percent(), 'rename_host' => HostDisplayName::enabled()];
            $next = [
                'auto_end_percent' => (int) ($data['zoom']['auto_end_percent'] ?? $current['auto_end_percent']),
                'rename_host' => (bool) ($data['zoom']['rename_host'] ?? $current['rename_host']),
            ];
            Setting::put('zoom', $next);
            if ($current['auto_end_percent'] !== $next['auto_end_percent']) {
                Audit::log('settings.zoom', $next['auto_end_percent'] > 0 ? 'Zoom avtomatik yakunlanadi: juftlikning :percent%' : 'Zoom avtomatik yakunlanishi oʻchirildi', replace: ['percent' => $next['auto_end_percent']]);
            }
            if ($current['rename_host'] !== $next['rename_host']) {
                Audit::log('settings.zoom', $next['rename_host'] ? 'Zoom host ismi oʻqituvchi nomiga oʻzgartiriladi' : 'Zoom host ismini oʻzgartirish oʻchirildi');
            }
        }

        if (isset($data['recording'])) {
            $before = RecordingSettings::get();
            Setting::put('recording', [
                'enabled' => (bool) $data['recording']['enabled'],
                'delete_from_zoom' => (bool) $data['recording']['delete_from_zoom'],
                'delete_action' => $data['recording']['delete_action'],
                'include_audio' => (bool) $data['recording']['include_audio'],
            ]);
            if ($before['enabled'] !== (bool) $data['recording']['enabled']) {
                Audit::log('settings.recording', $data['recording']['enabled'] ? 'Darslarni avtomatik yozib olish yoqildi' : 'Darslarni avtomatik yozib olish oʻchirildi');
            }
        }

        if (isset($data['assessment'])) {
            $thresholds = [];
            $previous = -1;
            foreach (['sust', 'qoniqarli', 'ortacha', 'yaxshi', 'alo'] as $level) {
                $value = $data['assessment']['thresholds'][$level] ?? null;
                if ($value === null || $value <= $previous) {
                    throw ValidationException::withMessages(['assessment.thresholds.'.$level => __('Har bir daraja chegarasi oldingisidan katta boʻlishi kerak.')]);
                }
                $thresholds[$level] = (int) $value;
                $previous = $value;
            }
            Setting::put('assessment', [
                'basis' => $data['assessment']['basis'],
                'presence_percent' => (int) $data['assessment']['presence_percent'],
                'thresholds' => $thresholds,
            ]);
            Audit::log('settings.assessment', 'Oʻzlashtirish mezoni oʻzgartirildi', properties: Assessment::criteria());
        }

        return response()->json(['data' => $this->payload()]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'assessment' => Assessment::criteria(),
            'assessment_defaults' => Assessment::DEFAULTS,
            'recording' => RecordingSettings::get(),
            'zoom' => [
                'auto_end_percent' => ZoomAutoEnd::percent(),
                'default_duration' => (int) config('lessons.default_duration', 80),
                'rename_host' => HostDisplayName::enabled(),
                // Oxirgi urinish xatosi (masalan, Zoom ilovasida ruxsat yo'q).
                'rename_error' => HostDisplayName::enabled() ? HostDisplayName::lastError() : null,
            ],
            'storage' => RecordingController::storage(),
        ];
    }
}
