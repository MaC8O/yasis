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
        $departmentIds = $this->guardianChildren($request)->pluck('department_id')->unique();

        $announcements = Announcement::where(function ($q) use ($departmentIds) {
            $q->whereIn('audience_type', ['School', 'Guardians'])
                ->orWhere(fn ($q2) => $q2->where('audience_type', 'Department')->whereIn('audience_id', $departmentIds));
        })->latest('published_at')->get();

        return view('guardian.notices.index', ['announcements' => $announcements]);
    }
}
