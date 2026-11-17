<?php

namespace App\Http\Controllers\Api;

use App\Enums\Assessment;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Audit;
use App\Support\RecordingSettings;
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
        ]);

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
            'storage' => RecordingController::storage(),
        ];
    }
}
