<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guardian\Concerns\ResolvesChild;
use Illuminate\Http\Request;

class GuardianAttendanceController extends Controller
{
    use ResolvesChild;

    public function index(Request $request)
    {
        $children = $this->guardianChildren($request);
        $child = $this->selectedChild($request);

        $records = $child->attendanceRecords()->with('section')->orderByDesc('attendance_date')->take(30)->get();

        return view('guardian.attendance.index', [
            'children' => $children,
            'child' => $child,
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
