<?php

namespace App\Http\Controllers\Principal;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\GradingScheme;
use App\Models\SystemSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;

class GovernanceController extends Controller
{
    public function index()
    {
        $activeYear = AcademicYear::where('is_active', true)->first();
        $currentTerm = $activeYear?->terms()->where('start_date', '<=', today())->where('end_date', '>=', today())->first()
            ?? $activeYear?->terms()->orderByDesc('sequence')->first();

        return view('principal.governance.index', [
            'activeYear' => $activeYear,
            'terms' => $activeYear?->terms()->orderBy('sequence')->get() ?? collect(),
            'currentTerm' => $currentTerm,
            'gradingSchemes' => GradingScheme::with([
                'gradeLevels' => fn ($q) => $q->ordered(),
                'bands' => fn ($q) => $q->active()->where('kind', 'result')->orderByDesc('min'),
            ])->withCount(['bands as flagged_bands_count' => fn ($q) => $q->active()->whereNotNull('pending_question')])
                ->orderBy('id')->get(),
            'gradeLevels' => GradeLevel::ordered()->withCount('sections')->get(),
            'settings' => [
                'promotion_window_open' => SystemSetting::get('promotion_window_open', '1'),
                'transcript_issuance_enabled' => SystemSetting::get('transcript_issuance_enabled', '0'),
                'principal_may_assist_registration' => SystemSetting::get('principal_may_assist_registration', '1'),
            ],
        ]);
    }

    public function update(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'promotion_window_open' => ['nullable', 'boolean'],
            'transcript_issuance_enabled' => ['nullable', 'boolean'],
            'principal_may_assist_registration' => ['nullable', 'boolean'],
            'grade_lock' => ['nullable', 'boolean'],
            'results_released' => ['nullable', 'boolean'],
        ]);

        foreach (['promotion_window_open', 'transcript_issuance_enabled', 'principal_may_assist_registration'] as $key) {
            SystemSetting::set($key, $request->boolean($key) ? '1' : '0');
        }

        $activeYear = AcademicYear::where('is_active', true)->first();
        $currentTerm = $activeYear?->terms()->where('start_date', '<=', today())->where('end_date', '>=', today())->first()
            ?? $activeYear?->terms()->orderByDesc('sequence')->first();

        $currentTerm?->update([
            'is_locked' => $request->boolean('grade_lock'),
            'results_released' => $request->boolean('results_released'),
        ]);

        $audit->log($request->user(), 'Updated governance controls', 'SystemSetting', null);

        return back()->with('status', 'Governance controls updated.');
    }
}
