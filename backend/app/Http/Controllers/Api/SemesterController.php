<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Support\SemesterReport;
use Illuminate\Http\JsonResponse;

/** Semestrlar ro'yxati (hisobot va filtrlar uchun). Boshqarish — AcademicYearController orqali. */
class SemesterController extends Controller
{
    public function index(): JsonResponse
    {
        $current = Semester::current();

        return response()->json(['data' => Semester::query()->with('academicYear')->orderByDesc('starts_on')->get()->map(fn (Semester $semester) => [
            ...SemesterReport::semester($semester),
            'current' => $current?->is($semester) ?? false,
        ])]);
    }
}
