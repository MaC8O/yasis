<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\AssignGradingSchemeRequest;
use App\Http\Requests\Admin\SaveGradingBandsRequest;
use App\Http\Requests\Admin\UpdateGradingSchemeRequest;
use App\Models\GradeLevel;
use App\Models\GradingScheme;
use App\Services\GradingSchemeService;
use Illuminate\Http\Request;

/**
 * Grading schemes per grade level. The Admin edits them (admin.grading-schemes.*); the Principal
 * sees the same page read-only (principal.grading-schemes.index). The portal comes from the route
 * name.
 */
class GradingSchemeController extends Controller
{
    public function __construct(private GradingSchemeService $schemes) {}

    public function index(Request $request)
    {
        $portal = strtok($request->route()->getName(), '.');
        abort_unless(in_array($portal, ['admin', 'principal'], true), 404);

        $schemes = GradingScheme::with(['gradeLevels' => fn ($q) => $q->ordered()])
            ->withCount(['bands as flagged_bands_count' => fn ($q) => $q->active()->whereNotNull('pending_question')])
            ->orderBy('id')->get();
        $scheme = $schemes->firstWhere('code', $request->string('scheme')->value()) ?? $schemes->first();

        return view('grading-schemes.index', [
            'portal' => $portal,
            'readOnly' => $portal !== 'admin',
            'schemes' => $schemes,
            'scheme' => $scheme,
            'bandSets' => $scheme ? [
                'mark' => $scheme->activeBands('mark')->get(),
                'result' => $scheme->activeBands('result')->get(),
            ] : [],
            'levels' => GradeLevel::ordered()->with('gradingScheme')->get(),
        ]);
    }

    public function update(UpdateGradingSchemeRequest $request, GradingScheme $gradingScheme)
    {
        $this->schemes->updateSettings($gradingScheme, $request->validated(), $request->user());

        return back()->with('status', "{$gradingScheme->fresh()->name} saved.");
    }

    public function updateBands(SaveGradingBandsRequest $request, GradingScheme $gradingScheme, string $kind)
    {
        $this->schemes->saveBands($gradingScheme, $kind, $request->validated(), $request->user());

        return back()->with('status', GradingScheme::BAND_KINDS[$kind]." bands for {$gradingScheme->name} saved. The previous set is kept in the history.");
    }

    public function assign(AssignGradingSchemeRequest $request, GradeLevel $gradeLevel)
    {
        $scheme = GradingScheme::findOrFail($request->integer('grading_scheme_id'));
        $this->schemes->assignLevel($gradeLevel, $scheme, $request->user());

        return back()->with('status', "{$gradeLevel->name} now uses {$scheme->name}.");
    }
}
