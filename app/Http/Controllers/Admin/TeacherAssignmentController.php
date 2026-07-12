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
 *
 * The editor is section-focused: pick a class, then set the teacher for each of
 * its department's subjects from a single dropdown per subject (assign, reassign,
 * or clear in one action).
 */
class TeacherAssignmentController extends Controller
{
    public function index()
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        $sections = Section::where('academic_year_id', $activeYear?->id)
            ->with(['department', 'homeroomTeacher.user', 'teachingAssignments.subject', 'teachingAssignments.teacher.user'])
            ->orderBy('department_id')->orderBy('name')->get();

        // Teacher workload: subjects taught + homerooms held, for the roster panel.
        $teacherLoads = TeachingAssignment::selectRaw('teacher_id, COUNT(*) as c')->groupBy('teacher_id')->pluck('c', 'teacher_id');
        $homeroomLoads = Section::whereNotNull('homeroom_teacher_id')
            ->selectRaw('homeroom_teacher_id, COUNT(*) as c')->groupBy('homeroom_teacher_id')->pluck('c', 'homeroom_teacher_id');

        return view('admin.teacher-assignments.index', [
            'sections' => $sections,
            'activeYear' => $activeYear,
            // Subjects available per department, so each section only offers its own curriculum.
            'subjectsByDept' => Subject::orderBy('name')->get()->groupBy('department_id'),
            'teachers' => StaffProfile::where('role_type', 'Teacher')->with('user')->get()
                ->sortBy(fn ($t) => $t->user->name)->values(),
            'teacherLoads' => $teacherLoads,
            'homeroomLoads' => $homeroomLoads,
        ]);
    }

    /**
     * Single entry point for a subject row: assign, reassign, or clear the teacher
     * for one section+subject depending on the submitted value.
     */
    public function setSubjectTeacher(Request $request, TeachingAssignmentService $service, AuditService $audit)
    {
        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['nullable', Rule::exists('staff_profiles', 'id')->where('role_type', 'Teacher')],
        ]);

        $existing = TeachingAssignment::where('section_id', $data['section_id'])
            ->where('subject_id', $data['subject_id'])->first();

        if (empty($data['teacher_id'])) {
            // Cleared — remove any existing assignment.
            if ($existing) {
                $id = $existing->id;
                $existing->delete();
                $audit->log($request->user(), 'Removed teaching assignment', 'TeachingAssignment', $id);

                return back()->with('status', 'Subject unassigned.');
            }

            return back()->with('status', 'No change.');
        }

        if ($existing) {
            if ($existing->teacher_id !== (int) $data['teacher_id']) {
                $service->reassign($existing, (int) $data['teacher_id'], $request->user());

                return back()->with('status', 'Teacher reassigned.');
            }

            return back()->with('status', 'No change.');
        }

        $service->assign($data['section_id'], $data['subject_id'], (int) $data['teacher_id'], $request->user());

        return back()->with('status', 'Teacher assigned.');
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
