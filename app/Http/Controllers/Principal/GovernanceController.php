<?php

namespace App\Http\Controllers\Principal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Principal\UpdateGovernanceRequest;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\GradingScheme;
use App\Models\ResultFreeze;
use App\Models\SystemSetting;
use App\Models\Term;
use App\Services\AuditService;
use App\Services\GradebookTemplateService;
use App\Services\ReportCardActionsService;
use App\Services\ResultFreezeService;
use Illuminate\Support\Facades\DB;

class GovernanceController extends Controller
{
    public function index(ResultFreezeService $freezes, GradebookTemplateService $templates)
    {
        $activeYear = AcademicYear::where('is_active', true)->first();
        $currentTerm = $this->currentTerm();

        return view('principal.governance.index', [
            'activeYear' => $activeYear,
            'terms' => $activeYear?->terms()->orderBy('sequence')->get() ?? collect(),
            'currentTerm' => $currentTerm,
            'activeFreeze' => $currentTerm ? ResultFreeze::active()->where('term_id', $currentTerm->id)->latest('id')->first() : null,
            'previousFreeze' => $currentTerm && ! $currentTerm->is_locked ? $freezes->previousFreeze($currentTerm) : null,
            'emptyCategories' => $currentTerm && ! $currentTerm->is_locked ? $templates->emptyItemCategories($currentTerm) : [],
            'unconfirmedSections' => $currentTerm && ! $currentTerm->results_released ? app(ReportCardActionsService::class)->unconfirmedSections($currentTerm) : [],
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

    public function update(UpdateGovernanceRequest $request, AuditService $audit, ResultFreezeService $freezes, ReportCardActionsService $reports)
    {
        foreach (['promotion_window_open', 'transcript_issuance_enabled', 'principal_may_assist_registration'] as $key) {
            SystemSetting::set($key, $request->boolean($key) ? '1' : '0');
        }

        $currentTerm = $this->currentTerm();

        $releasing = $currentTerm && $request->boolean('results_released') && ! $currentTerm->results_released;
        $unconfirmed = $releasing ? $reports->unconfirmedSections($currentTerm) : [];

        DB::transaction(function () use ($request, $audit, $freezes, $reports, $currentTerm) {
            // Locking freezes the term's results; unlocking lifts the freeze. Only a change does anything.
            if ($currentTerm && $request->boolean('grade_lock') !== $currentTerm->is_locked) {
                $request->boolean('grade_lock')
                    ? $freezes->lock($currentTerm, $request->user(), $request->input('relock_rules'), $request->input('relock_reason'))
                    : $freezes->unlock($currentTerm, $request->user());
                $currentTerm->refresh();
            }
            if ($currentTerm) {
                // Releasing the term's results is the Principal's sign-off on every report card (§7.1).
                $reports->setPrincipalRelease($currentTerm, $request->boolean('results_released'), $request->user());
                $currentTerm->update(['results_released' => $request->boolean('results_released')]);
            }

            $audit->log($request->user(), 'Updated governance controls', 'SystemSetting', null);
        });

        // Released anyway, but the Principal is told which classes still have unconfirmed cards.
        $response = back()->with('status', 'Governance controls updated.');
        if ($unconfirmed) {
            $response->with('warning', $currentTerm->name.' results are released, but these classes have report cards the class teacher has not confirmed: '
                .collect($unconfirmed)->map(fn ($s) => "{$s['section']} ({$s['unconfirmed']} of {$s['total']})")->implode(', ').'.');
        }

        return $response;
    }

    private function currentTerm(): ?Term
    {
        return self::currentTermFor();
    }

    /** The term the Principal's grade lock applies to: the one running today, else the year's last. */
    public static function currentTermFor(): ?Term
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        return $activeYear?->terms()->where('start_date', '<=', today())->where('end_date', '>=', today())->first()
            ?? $activeYear?->terms()->orderByDesc('sequence')->first();
    }
}
