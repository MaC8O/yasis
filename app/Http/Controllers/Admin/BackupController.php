<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

/**
 * §3.6 / §6.6 Data & Backup: infrastructure backups are automated; this page
 * surfaces backup status, the on-demand data snapshot (ZIP of CSVs), and the
 * data-retention/erasure controls in one place.
 */
class BackupController extends Controller
{
    public function index(DataExportController $export)
    {
        $lastExport = SystemSetting::get('last_export_at');

        return view('admin.backup.index', [
            'backupStatus' => SystemSetting::get('last_backup_status', 'Automated (infrastructure-managed)'),
            'lastBackupAt' => SystemSetting::get('last_backup_at'),
            'lastExport' => $lastExport,
            'lastExportAt' => $lastExport ? \Carbon\Carbon::parse($lastExport) : null,
            'snapshotTables' => $export->tableNames(),
            'recordCounts' => $this->recordCounts(),
            'lastExportEvents' => AuditLog::with('user')
                ->whereIn('action', ['Generated data export snapshot'])
                ->latest('created_at')->limit(5)->get(),
        ]);
    }

    /** Headline row counts so the admin can eyeball what a snapshot would contain. */
    protected function recordCounts(): array
    {
        return [
            'Users' => DB::table('users')->count(),
            'Students' => DB::table('students')->count(),
            'Guardians' => DB::table('guardians')->count(),
            'Grades' => DB::table('grades')->count(),
            'Attendance records' => DB::table('attendance_records')->count(),
            'Fee records' => DB::table('imported_fee_records')->count(),
            'Audit log entries' => DB::table('audit_logs')->count(),
        ];
    }
}
