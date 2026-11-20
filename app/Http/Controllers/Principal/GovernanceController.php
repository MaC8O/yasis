<?php

namespace App\Http\Controllers\Principal;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\GradeScaleBand;
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
            'gradeScales' => $this->academicGradeScales(),
            'settings' => [
                'promotion_window_open' => SystemSetting::get('promotion_window_open', '1'),
                'transcript_issuance_enabled' => SystemSetting::get('transcript_issuance_enabled', '0'),
                'principal_may_assist_registration' => SystemSetting::get('principal_may_assist_registration', '1'),
            ],
        ]);
    }

    /**
     * Grade scales for the academic departments only (administrative departments such as Finance
     * or Canteen have no students and no grades), in school order, with each band's score range.
     *
     * @return \Illuminate\Support\Collection<int, array{department: Department, bands: \Illuminate\Support\Collection, hasGpa: bool}>
     */
    protected function academicGradeScales()
    {
        $schoolOrder = ['Pre-School', 'Elementary', 'Middle School', 'High School'];

        return Department::academic()
            ->with(['gradeScaleBands' => fn ($q) => $q->orderByDesc('min_score')])
            ->get()
            ->sortBy(fn ($d) => [array_search($d->level, Department::ACADEMIC_LEVELS, true), array_search($d->name, $schoolOrder, true) === false ? 99 : array_search($d->name, $schoolOrder, true), $d->name])
            ->map(function (Department $department) {
                $upper = 100.0;
                $bands = $department->gradeScaleBands->map(function (GradeScaleBand $band) use (&$upper) {
                    $row = ['letter' => $band->letter, 'min' => (float) $band->min_score, 'max' => $upper, 'gpa' => $band->gpa_point];
                    $upper = max((float) $band->min_score - 1, 0);

                    return $row;
                });

                return ['department' => $department, 'bands' => $bands, 'hasGpa' => $bands->contains(fn ($b) => $b['gpa'] !== null)];
            })
            ->values();
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
