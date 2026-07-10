<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StudentAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;
        abort_unless($student, 403);

        $records = $student->attendanceRecords()->with('section')->orderByDesc('attendance_date')->take(30)->get();

        return view('student.attendance.index', [
            'student' => $student,
            'records' => $records,
            'counts' => [
                'Present' => $records->where('status', 'Present')->count(),
                'Absent' => $records->where('status', 'Absent')->count(),
                'Tardy' => $records->where('status', 'Tardy')->count(),
            ],
            'rate' => $records->count() ? round($records->whereIn('status', ['Present', 'Tardy', 'Excused'])->count() / $records->count() * 100) : null,
        ]);
    }
}
