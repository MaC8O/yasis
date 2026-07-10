<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * §3.6 Administration: infrastructure backups are automated; the Admin portal exposes
 * backup status plus this on-demand data export/snapshot — a ZIP of CSVs of the core
 * tables, suitable for an off-site copy or an offline review.
 */
class DataExportController extends Controller
{
    /** Tables included in the snapshot. Passwords and tokens are never exported. */
    protected array $tables = [
        'users' => ['id', 'name', 'email', 'status', 'last_login_at', 'created_at'],
        'staff_profiles' => ['id', 'staff_id_number', 'role_type', 'job_title', 'department_id', 'status', 'joined_date', 'phone'],
        'students' => ['id', 'user_id', 'student_id_number', 'name', 'date_of_birth', 'gender', 'religious_background', 'admission_date', 'department_id', 'enrollment_status'],
        'guardians' => ['id', 'user_id', 'relationship', 'phone'],
        'student_guardian' => ['id', 'student_id', 'guardian_id', 'is_primary'],
        'departments' => ['id', 'name', 'level'],
        'academic_years' => ['id', 'year_label', 'is_active'],
        'terms' => ['id', 'academic_year_id', 'name', 'sequence', 'start_date', 'end_date', 'is_locked', 'results_released'],
        'sections' => ['id', 'academic_year_id', 'department_id', 'name', 'homeroom_teacher_id', 'capacity'],
        'subjects' => ['id', 'code', 'name', 'department_id'],
        'teaching_assignments' => ['id', 'section_id', 'subject_id', 'teacher_id'],
        'enrollments' => ['id', 'student_id', 'section_id', 'status'],
        'attendance_records' => ['id', 'student_id', 'section_id', 'term_id', 'attendance_date', 'status', 'remark', 'absence_notice_id', 'recorded_by'],
        'assessment_categories' => ['id', 'section_id', 'subject_id', 'term_id', 'name', 'weight_pct'],
        'assessments' => ['id', 'category_id', 'name', 'max_score'],
        'grades' => ['id', 'assessment_id', 'student_id', 'score', 'entered_by'],
        'import_batches' => ['id', 'uploaded_by', 'period', 'source_file', 'row_count', 'uploaded_at', 'published_at'],
        'imported_fee_records' => ['id', 'import_batch_id', 'student_id', 'raw_student_key', 'txn_date', 'amount', 'balance', 'status', 'is_restricted'],
        'leave_types' => ['id', 'name', 'is_paid'],
        'leave_requests' => ['id', 'staff_id', 'leave_type_id', 'from_date', 'to_date', 'days', 'reason', 'status', 'submitted_by', 'decided_by', 'decided_at'],
        'leave_balances' => ['id', 'staff_id', 'leave_type_id', 'year', 'allocated', 'pending', 'used'],
        'staff_attendance' => ['id', 'staff_id', 'attendance_date', 'status', 'remark', 'leave_request_id', 'recorded_by'],
        'absence_notices' => ['id', 'student_id', 'guardian_id', 'from_date', 'to_date', 'reason', 'status', 'acknowledged_by', 'acknowledged_at'],
        'report_card_comments' => ['id', 'student_id', 'term_id', 'staff_id', 'comment'],
        'promotion_batches' => ['id', 'from_section_id', 'prepared_by', 'status', 'vp_approved_by', 'vp_approved_at', 'principal_approved_by', 'principal_approved_at', 'applied_at'],
        'promotion_batch_items' => ['id', 'promotion_batch_id', 'student_id', 'action', 'to_section_id'],
        'document_requests' => ['id', 'student_id', 'type', 'status', 'prepared_by', 'approved_by', 'approved_at', 'principal_approved_by', 'principal_approved_at', 'generated_at'],
        'announcements' => ['id', 'author_id', 'title', 'audience_type', 'published_at'],
        'audit_logs' => ['id', 'user_id', 'role', 'action', 'entity_type', 'entity_id', 'created_at'],
    ];

    public function download(Request $request, AuditService $audit)
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'isms_snapshot_');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);

        foreach ($this->tables as $table => $columns) {
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, $columns);

            DB::table($table)->select($columns)->orderBy('id')->chunk(500, function ($rows) use ($handle, $columns) {
                foreach ($rows as $row) {
                    fputcsv($handle, array_map(fn ($col) => $row->{$col}, $columns));
                }
            });

            rewind($handle);
            $zip->addFromString("{$table}.csv", stream_get_contents($handle));
            fclose($handle);
        }

        $zip->addFromString('README.txt',
            "YASIS ISMS data snapshot\nGenerated: ".now()->toDateTimeString()." by {$request->user()->email}\n"
            ."Contains CSV exports of the core tables. Passwords and tokens are never included.\n");
        $zip->close();

        SystemSetting::set('last_export_at', now()->toDateTimeString());
        $audit->log($request->user(), 'Generated data export snapshot', 'SystemSetting', null);

        return response()->download($zipPath, 'isms-snapshot-'.now()->format('Y-m-d-Hi').'.zip')->deleteFileAfterSend();
    }
}
