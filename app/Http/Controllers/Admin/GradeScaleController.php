<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\GradeScaleBand;
use App\Services\AuditService;
use Illuminate\Http\Request;

class GradeScaleController extends Controller
{
    public function index()
    {
        return view('admin.grade-scale.index', [
            'departments' => Department::with(['gradeScaleBands' => fn ($q) => $q->orderByDesc('min_score')])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'letter' => ['required', 'string', 'max:5'],
            'min_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'gpa_point' => ['required', 'numeric', 'min:0', 'max:4'],
        ]);

        $band = GradeScaleBand::create($data);
        $audit->log($request->user(), 'Added grade scale band', 'GradeScaleBand', $band->id);

        return back()->with('status', 'Grade scale band added.');
    }

    public function destroy(Request $request, GradeScaleBand $gradeScaleBand, AuditService $audit)
    {
        $id = $gradeScaleBand->id;
        $gradeScaleBand->delete();
        $audit->log($request->user(), 'Removed grade scale band', 'GradeScaleBand', $id);

        return back()->with('status', 'Grade scale band removed.');
    }
}
