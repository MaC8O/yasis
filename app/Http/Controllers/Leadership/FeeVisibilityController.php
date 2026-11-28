<?php

namespace App\Http\Controllers\Leadership;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\Sun\SunFamilyStatements;
use App\Services\Sun\SunFeeOverview;
use App\Support\FeeVisibility;
use App\Support\LedgerFormat;
use Illuminate\Http\Request;

/**
 * §2.2 fees.view_readonly: the Principal, VP Academic and Registrar read the published fee
 * statements (the same figures families see), read-only. Lines the Treasurer hid by hand show
 * only as "Adjustments" unless the Treasurer's policy switch lets leadership see them, marked.
 */
class FeeVisibilityController extends Controller
{
    public function index(Request $request, SunFeeOverview $overview, SunFamilyStatements $statements)
    {
        $role = $request->user()->getRoleNames()->first();
        $all = $overview->publishedRows();
        $rows = $all;

        if ($search = $request->string('search')->trim()->lower()->value()) {
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r->student->name), $search)
                || str_contains(mb_strtolower($r->student->student_id_number), $search)
                || str_contains(mb_strtolower((string) $r->section?->name), $search));
        }
        if (in_array($status = $request->string('status')->value(), ['Owing', 'Settled', 'In credit'], true)) {
            $rows = $rows->where('status', $status);
        }
        $year = $statements->latestYear();

        return view('leadership.fees.index', [
            'role' => $role,
            'rows' => $rows->values(),
            'filters' => $request->only(['search', 'status']),
            'yearLabel' => $year ? LedgerFormat::year($year) : null,
            'totals' => $overview->totals($all),
            'distribution' => $overview->distribution($all),
            'byDepartment' => $overview->groupBy($all, fn ($r) => $r->student->department?->name),
            'showsHidden' => FeeVisibility::leadershipSeesRestricted(),
        ]);
    }

    public function show(Request $request, Student $student, SunFamilyStatements $statements, AuditService $audit)
    {
        // §3.8 access logging: record who opened a student's financial record.
        $audit->log($request->user(), 'Viewed student financial record', 'Student', $student->id);

        return view('leadership.fees.show', [
            'role' => $request->user()->getRoleNames()->first(),
            'student' => $student->load(['department', 'enrollments.section']),
            'statements' => $statements->forStudent($student),
            'showsHidden' => FeeVisibility::leadershipSeesRestricted(),
        ]);
    }
}
