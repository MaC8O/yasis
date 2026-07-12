<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\GradeScaleBand;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * §6.5 Grade Scale. The scale is an ACADEMIC configuration — it applies only to
 * the teaching departments (Pre-School, Elementary, Middle School, High School),
 * never to administrative units such as Canteen or Transportation.
 *
 * The YASIS standard scale (§8.4): the lower school (Early Years / Primary) uses a
 * descriptive, no-GPA scale; the secondary school uses letter grades with GPA points.
 */
class GradeScaleController extends Controller
{
    /** YASIS standard bands per department level (highest band first). */
    protected const DEFAULTS = [
        'Secondary' => [
            ['letter' => 'A',  'min_score' => 90, 'gpa_point' => 4.0],
            ['letter' => 'B+', 'min_score' => 85, 'gpa_point' => 3.5],
            ['letter' => 'B',  'min_score' => 80, 'gpa_point' => 3.0],
            ['letter' => 'C+', 'min_score' => 75, 'gpa_point' => 2.5],
            ['letter' => 'C',  'min_score' => 70, 'gpa_point' => 2.0],
            ['letter' => 'D',  'min_score' => 60, 'gpa_point' => 1.0],
            ['letter' => 'F',  'min_score' => 0,  'gpa_point' => 0.0],
        ],
        // Early Years + Primary: descriptive, no GPA.
        'descriptive' => [
            ['letter' => 'E', 'min_score' => 90, 'gpa_point' => null], // Excellent
            ['letter' => 'G', 'min_score' => 80, 'gpa_point' => null], // Good
            ['letter' => 'S', 'min_score' => 70, 'gpa_point' => null], // Satisfactory
            ['letter' => 'P', 'min_score' => 60, 'gpa_point' => null], // Progressing
            ['letter' => 'N', 'min_score' => 0,  'gpa_point' => null], // Needs support
        ],
    ];

    public function index()
    {
        return view('admin.grade-scale.index', [
            'departments' => Department::academic()
                ->with(['gradeScaleBands' => fn ($q) => $q->orderByDesc('min_score')])
                ->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'department_id' => ['required', Rule::exists('departments', 'id')->where(fn ($q) => $q->whereIn('level', Department::ACADEMIC_LEVELS))],
            'letter' => ['required', 'string', 'max:5'],
            'min_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'gpa_point' => ['nullable', 'numeric', 'min:0', 'max:4'],
        ], [
            'department_id.exists' => 'Grade scales apply to academic departments only.',
        ]);

        $band = GradeScaleBand::create($data);
        $audit->log($request->user(), 'Added grade scale band', 'GradeScaleBand', $band->id);

        return back()->with('status', 'Grade scale band added.');
    }

    /** Reset a department to the YASIS standard scale (descriptive or GPA by level). */
    public function loadDefaults(Request $request, Department $department, AuditService $audit)
    {
        abort_unless(in_array($department->level, Department::ACADEMIC_LEVELS, true), 403);

        $bands = $department->level === 'Secondary' ? self::DEFAULTS['Secondary'] : self::DEFAULTS['descriptive'];

        DB::transaction(function () use ($department, $bands) {
            $department->gradeScaleBands()->delete();
            foreach ($bands as $band) {
                $department->gradeScaleBands()->create($band);
            }
        });

        $audit->log($request->user(), 'Loaded YASIS standard grade scale', 'Department', $department->id);

        return back()->with('status', "{$department->name} reset to the YASIS standard scale.");
    }

    public function destroy(Request $request, GradeScaleBand $gradeScaleBand, AuditService $audit)
    {
        $id = $gradeScaleBand->id;
        $gradeScaleBand->delete();
        $audit->log($request->user(), 'Removed grade scale band', 'GradeScaleBand', $id);

        return back()->with('status', 'Grade scale band removed.');
    }
}
