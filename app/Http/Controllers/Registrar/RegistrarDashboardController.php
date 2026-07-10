<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\Guardian;
use App\Models\PromotionBatch;
use App\Models\Student;

class RegistrarDashboardController extends Controller
{
    public function index()
    {
        // Absent records covered by an active guardian absence notice — likely misclassifications (§3.6).
        $needsCorrection = AttendanceRecord::where('status', 'Absent')
            ->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('absence_notices')
                ->whereColumn('absence_notices.student_id', 'attendance_records.student_id')
                ->whereColumn('absence_notices.from_date', '<=', 'attendance_records.attendance_date')
                ->whereColumn('absence_notices.to_date', '>=', 'attendance_records.attendance_date')
                ->whereIn('absence_notices.status', ['Submitted', 'Acknowledged']))
            ->count();

        return view('registrar.dashboard', [
            'needsCorrection' => $needsCorrection,
            'activeStudents' => Student::where('enrollment_status', 'Enrolled')->count(),
            'missingGuardian' => Student::doesntHave('guardians')->count(),
            'guardianLinkRate' => Student::count() > 0
                ? round(Student::has('guardians')->count() / Student::count() * 100)
                : 0,
            'documentsQueue' => DocumentRequest::where('status', 'Draft')->count(),
            'totalGuardians' => Guardian::count(),
            'pendingPromotions' => PromotionBatch::where('status', 'Pending')->count(),
            'recentActivity' => AuditLog::with('user')->whereIn('entity_type', ['Student', 'Guardian', 'DocumentRequest', 'PromotionBatch'])
                ->latest('created_at')->take(8)->get(),
        ]);
    }
}
