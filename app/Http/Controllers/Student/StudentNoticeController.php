<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class StudentNoticeController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;
        abort_unless($student, 403);

        $sectionIds = $student->enrollments()->where('status', 'Active')->pluck('section_id');

        $announcements = Announcement::where(fn ($q) => $q->whereIn('audience_type', ['School', 'Students'])
            ->orWhere(fn ($q2) => $q2->where('audience_type', 'Department')->where('audience_id', $student->department_id))
            ->orWhere(fn ($q3) => $q3->where('audience_type', 'Section')->whereIn('audience_id', $sectionIds)))
            ->latest('published_at')->get();

        return view('student.notices.index', ['announcements' => $announcements]);
    }
}
