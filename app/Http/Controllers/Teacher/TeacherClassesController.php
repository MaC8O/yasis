<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TeacherClassesController extends Controller
{
    public function index(Request $request)
    {
        $teacher = $request->user()->staffProfile;

        $taught = $teacher->teachingAssignments()->with(['section.department', 'subject'])->get();
        $homerooms = $teacher->homeroomSections()->with('department')->get();

        $rows = $taught->map(fn ($assignment) => [
            'section' => $assignment->section,
            'subject' => $assignment->subject->name,
            'students' => $assignment->section->enrollments()->count(),
            'gradebook' => true,
        ]);

        foreach ($homerooms as $section) {
            if (! $taught->contains(fn ($a) => $a->section_id === $section->id)) {
                $rows->push([
                    'section' => $section,
                    'subject' => 'Homeroom',
                    'students' => $section->enrollments()->count(),
                    'gradebook' => false,
                ]);
            }
        }

        return view('teacher.classes.index', [
            'rows' => $rows,
            'stats' => [
                'classes' => $rows->count(),
                'students' => $rows->sum('students'),
                'subjects' => $taught->pluck('subject_id')->unique()->count(),
                'sections' => $rows->pluck('section.id')->unique()->count(),
            ],
        ]);
    }
}
