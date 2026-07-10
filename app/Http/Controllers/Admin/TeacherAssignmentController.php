<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Services\AuditService;
use App\Services\TeachingAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * §6.3: initial technical setup/override of class assignments. Operational
 * ownership of sections + homeroom is the Registrar (§7.5); subject-teaching
 * is the VP Academic (§12.3) — this screen exists for Admin bootstrap/fixes.
 */
class TeacherAssignmentController extends Controller
{
    public function index()
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        return view('admin.teacher-assignments.index', [
            'sections' => Section::where('academic_year_id', $activeYear?->id)
                ->with(['department', 'homeroomTeacher.user', 'teachingAssignments.subject', 'teachingAssignments.teacher.user'])
                ->orderBy('name')->get(),
            'subjects' => Subject::orderBy('name')->get(),
            'teachers' => StaffProfile::where('role_type', 'Teacher')->with('user')->get(),
        ]);
    }

    public function storeAssignment(Request $request, TeachingAssignmentService $service)
    {
        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['required', Rule::exists('staff_profiles', 'id')->where('role_type', 'Teacher')],
        ]);

        $service->assign($data['section_id'], $data['subject_id'], $data['teacher_id'], $request->user());

        return back()->with('status', 'Teaching assignment saved.');
    }

    public function reassign(Request $request, TeachingAssignment $teachingAssignment, TeachingAssignmentService $service)
    {
        $data = $request->validate([
            'teacher_id' => ['required', Rule::exists('staff_profiles', 'id')->where('role_type', 'Teacher')],
        ]);

        $service->reassign($teachingAssignment, $data['teacher_id'], $request->user());

        return back()->with('status', 'Teacher reassigned.');
    }

    public function destroyAssignment(Request $request, TeachingAssignment $teachingAssignment, AuditService $audit)
    {
        $id = $teachingAssignment->id;
        $teachingAssignment->delete();
        $audit->log($request->user(), 'Removed teaching assignment', 'TeachingAssignment', $id);

        return back()->with('status', 'Teaching assignment removed.');
    }

    public function setHomeroom(Request $request, Section $section, AuditService $audit)
    {
        $data = $request->validate([
            'homeroom_teacher_id' => ['nullable', Rule::exists('staff_profiles', 'id')->where('role_type', 'Teacher')],
        ], [
            'homeroom_teacher_id.exists' => 'The homeroom teacher must be a staff member with the Teacher role.',
        ]);

        $section->update(['homeroom_teacher_id' => $data['homeroom_teacher_id'] ?? null]);
        $audit->log($request->user(), 'Set homeroom teacher (admin override)', 'Section', $section->id);

        return back()->with('status', "Homeroom updated for {$section->name}.");
    }
}
