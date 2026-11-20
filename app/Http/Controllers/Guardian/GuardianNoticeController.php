<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guardian\Concerns\ResolvesChild;
use App\Models\Announcement;
use Illuminate\Http\Request;

class GuardianNoticeController extends Controller
{
    use ResolvesChild;

    public function index(Request $request)
    {
        $children = $this->guardianChildren($request);
        $departmentIds = $children->pluck('department_id')->unique();
        // Class announcements from teachers target the children's current sections.
        $sectionIds = \App\Models\Enrollment::whereIn('student_id', $children->pluck('id'))->where('status', 'Active')->pluck('section_id');

        $announcements = Announcement::where(function ($q) use ($departmentIds, $sectionIds) {
            $q->whereIn('audience_type', ['School', 'Guardians'])
                ->orWhere(fn ($q2) => $q2->where('audience_type', 'Department')->whereIn('audience_id', $departmentIds))
                ->orWhere(fn ($q3) => $q3->where('audience_type', 'Section')->whereIn('audience_id', $sectionIds));
        })->latest('published_at')->get();

        return view('guardian.notices.index', ['announcements' => $announcements]);
    }
}
