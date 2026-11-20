<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrar\BulkTeachingAssignmentRequest;
use App\Http\Requests\Registrar\TeachingAssignmentRequest;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Services\TeachingAssignmentService;
use Illuminate\Http\Request;

/**
 * Subject-teacher assignments within the Registrar's sections. The VP Academic owns the
 * subject catalogue itself; the Registrar only chooses who teaches a subject in a section.
 */
class TeachingAssignmentController extends Controller
{
    public function __construct(private TeachingAssignmentService $service)
    {
    }

    public function index(Request $request)
    {
        $activeYear = AcademicYear::where('is_active', true)->first();
        $search = $request->string('search')->trim()->value();

        $assignments = TeachingAssignment::with(['section', 'subject', 'teacher.user'])
            ->whereHas('section', fn ($q) => $q->where('academic_year_id', $activeYear?->id)->whereNull('archived_at'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('section', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                ->orWhereHas('subject', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                ->orWhereHas('teacher.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))))
            ->get()
            ->sortBy(fn ($a) => [strlen($a->section->name), $a->section->name, $a->subject->name])->values();

        return view('registrar.teaching-assignments.index', [
            'assignments' => $assignments,
            'sections' => Section::active()->where('academic_year_id', $activeYear?->id)->orderByRaw('LENGTH(name), name')->get(),
            'subjects' => Subject::orderBy('name')->get(),
            'teachers' => StaffProfile::where('role_type', 'Teacher')->whereIn('status', ['Active', 'Probation'])
                ->with('user')->get()->sortBy('user.name')->values(),
            'search' => $search,
        ]);
    }

    public function store(TeachingAssignmentRequest $request)
    {
        $data = $request->validated();
        $this->service->assign((int) $data['section_id'], (int) $data['subject_id'], (int) $data['teacher_id'], $request->user());

        return back()->with('status', 'Teaching assignment saved.');
    }

    public function update(TeachingAssignmentRequest $request, TeachingAssignment $teachingAssignment)
    {
        $teacherId = (int) $request->validated()['teacher_id'];

        if ($teachingAssignment->teacher_id === $teacherId) {
            return back()->with('status', 'No change.');
        }

        $this->service->reassign($teachingAssignment, $teacherId, $request->user());

        return back()->with('status', "Teacher changed for {$teachingAssignment->subject->name} in {$teachingAssignment->section->name}.");
    }

    public function destroy(Request $request, TeachingAssignment $teachingAssignment)
    {
        $this->service->remove($teachingAssignment, $request->user());

        return back()->with('status', 'Teaching assignment removed.');
    }

    /** Section detail page: set teachers for several subjects in one save. */
    public function bulk(BulkTeachingAssignmentRequest $request, Section $section)
    {
        $counts = $this->service->bulkSave($section, $request->validated()['teachers'], $request->user());
        $changed = array_sum($counts);

        $message = $changed === 0
            ? 'No changes to save.'
            : "Saved: {$counts['assigned']} assigned, {$counts['reassigned']} changed, {$counts['removed']} removed.";

        return redirect()->route('registrar.sections.show', ['section' => $section, 'tab' => 'assignments'])->with('status', $message);
    }
}
