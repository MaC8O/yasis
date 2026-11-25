<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\CurriculumRowRequest;
use App\Models\GradeLevel;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Services\CurriculumService;
use Illuminate\Http\Request;

/**
 * The curriculum per grade level. The Admin edits it (admin.curriculum.*); the Principal sees the
 * same page read-only (principal.curriculum.index). The portal comes from the route name.
 */
class CurriculumController extends Controller
{
    public function __construct(private CurriculumService $curriculum)
    {
    }

    public function index(Request $request)
    {
        $portal = strtok($request->route()->getName(), '.');
        abort_unless(in_array($portal, ['admin', 'principal'], true), 404);

        $levels = GradeLevel::ordered()->withCount([
            'offerings as flagged_count' => fn ($q) => $q->active()->whereNotNull('pending_question'),
        ])->get();
        $level = $levels->firstWhere('code', $request->string('level')->value())
            ?? $levels->first(fn ($l) => ! $l->isArchived()) ?? $levels->first();

        $offerings = $level
            ? SubjectOffering::with('subject')->where('grade_level_id', $level->id)->ordered()->get()
            : collect();

        return view('curriculum.index', [
            'portal' => $portal,
            'readOnly' => $portal !== 'admin',
            'levels' => $levels,
            'level' => $level,
            'offerings' => $offerings,
            'addable' => $level && $portal === 'admin'
                ? Subject::whereNotIn('id', $offerings->pluck('subject_id'))->orderByRaw('department_id IS NOT NULL')->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function store(CurriculumRowRequest $request, GradeLevel $gradeLevel)
    {
        $offering = $this->curriculum->add($gradeLevel, $request->validated(), $request->user());

        return back()->with('status', "{$offering->report_label} added to {$gradeLevel->name}.");
    }

    public function update(CurriculumRowRequest $request, SubjectOffering $subjectOffering)
    {
        $this->curriculum->update($subjectOffering, $request->validated(), $request->user());

        return back()->with('status', "{$subjectOffering->report_label} saved.");
    }

    public function move(Request $request, SubjectOffering $subjectOffering, string $direction)
    {
        $this->curriculum->move($subjectOffering, $direction, $request->user());

        return back()->with('status', "{$subjectOffering->report_label} moved {$direction}.");
    }

    public function retire(Request $request, SubjectOffering $subjectOffering)
    {
        $this->curriculum->retire($subjectOffering, $request->user());

        return back()->with('status', "{$subjectOffering->report_label} retired from this grade level.");
    }

    public function restore(Request $request, SubjectOffering $subjectOffering)
    {
        $this->curriculum->restore($subjectOffering, $request->user());

        return back()->with('status', "{$subjectOffering->report_label} restored.");
    }
}
