<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Behavior\BehaviorStudentController;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

/** Student → Behavior: their own score, stage, office visits and reports. Read only. */
class StudentBehaviorController extends Controller
{
    public function index(Request $request)
    {
        $student = Student::where('user_id', $request->user()->id)->firstOrFail();

        return view('family.behavior', BehaviorStudentController::record($student) + [
            'student' => $student, 'role' => 'student', 'children' => null,
        ]);
    }
}
