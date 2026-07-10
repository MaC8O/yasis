<?php

namespace App\Http\Controllers\VpAcademic;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Services\AuditService;
use App\Services\TeachingAssignmentService;
use Illuminate\Http\Request;

/**
 * §3.6 Module 3: the VP Academic owns the subject catalogue and subject-teaching
 * assignments, while the Registrar owns sections and homeroom assignment — both
 * work from the same class structure.
 */
class SubjectCatalogController extends Controller
{
    public function index()
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        return view('vp_academic.subjects.index', [
            'subjects' => Subject::with('department')->withCount('teachingAssignments')->orderBy('code')->get(),
            'departments' => Department::whereIn('level', ['Secondary', 'Primary', 'Early Years'])->orderBy('name')->get(),
            'assignments' => TeachingAssignment::with(['section', 'subject', 'teacher.user'])
                ->whereHas('section', fn ($q) => $q->where('academic_year_id', $activeYear?->id))
                ->get(),
            'sections' => Section::where('academic_year_id', $activeYear?->id)->orderBy('name')->get(),
            'teachers' => StaffProfile::where('role_type', 'Teacher')->where('status', '!=', 'Inactive')->with('user')->get(),
        ]);
    }

    public function storeSubject(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:subjects,code'],
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
        ]);

        $subject = Subject::create($data);
        $audit->log($request->user(), 'Added subject to catalogue', 'Subject', $subject->id);

        return back()->with('status', "Subject {$subject->code} — {$subject->name} added to the catalogue.");
    }

    public function destroySubject(Request $request, Subject $subject, AuditService $audit)
    {
        if ($subject->teachingAssignments()->exists() || $subject->assessmentCategories()->exists()) {
            return back()->withErrors(['subject' => "{$subject->code} has teaching assignments or gradebook data and cannot be removed. Remove its assignments first."]);
        }

        $id = $subject->id;
        $code = $subject->code;
        $subject->delete();
        $audit->log($request->user(), 'Removed subject from catalogue', 'Subject', $id);

        return back()->with('status', "Subject {$code} removed.");
    }

    public function storeAssignment(Request $request, TeachingAssignmentService $service)
    {
        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['required', 'exists:staff_profiles,id'],
        ]);

        $service->assign($data['section_id'], $data['subject_id'], $data['teacher_id'], $request->user());

        return back()->with('status', 'Teaching assignment saved.');
    }

    public function destroyAssignment(Request $request, TeachingAssignment $teachingAssignment, AuditService $audit)
    {
        $id = $teachingAssignment->id;
        $teachingAssignment->delete();
        $audit->log($request->user(), 'Removed teaching assignment', 'TeachingAssignment', $id);

        return back()->with('status', 'Teaching assignment removed.');
    }
}
