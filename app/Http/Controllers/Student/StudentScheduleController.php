<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\TeachingAssignment;
use Illuminate\Http\Request;

class StudentScheduleController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;
        abort_unless($student, 403);

        $assignments = TeachingAssignment::whereIn('section_id', $student->enrollments()->pluck('section_id'))
            ->with(['subject', 'section', 'teacher.user'])->get();

        return view('student.schedule.index', ['student' => $student, 'assignments' => $assignments]);
    }
}
