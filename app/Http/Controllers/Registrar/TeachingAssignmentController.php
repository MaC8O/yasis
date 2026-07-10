<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Services\AuditService;
use Illuminate\Http\Request;

class TeachingAssignmentController extends Controller
{
    public function index()
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        return view('registrar.teaching-assignments.index', [
            'assignments' => TeachingAssignment::with(['section', 'subject', 'teacher.user'])
                ->whereHas('section', fn ($q) => $q->where('academic_year_id', $activeYear?->id))
                ->get(),
            'sections' => Section::where('academic_year_id', $activeYear?->id)->orderBy('name')->get(),
            'subjects' => Subject::orderBy('name')->get(),
            'teachers' => StaffProfile::where('role_type', 'Teacher')->with('user')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['required', 'exists:staff_profiles,id'],
        ]);

        $assignment = TeachingAssignment::firstOrCreate($data);
        $audit->log($request->user(), 'Assigned teacher to section/subject', 'TeachingAssignment', $assignment->id);

        return back()->with('status', 'Teaching assignment saved.');
    }

    public function destroy(Request $request, TeachingAssignment $teachingAssignment, AuditService $audit)
    {
        $id = $teachingAssignment->id;
        $teachingAssignment->delete();
        $audit->log($request->user(), 'Removed teaching assignment', 'TeachingAssignment', $id);

        return back()->with('status', 'Teaching assignment removed.');
    }
}
