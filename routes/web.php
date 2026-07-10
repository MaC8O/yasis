<?php

use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DataExportController;
use App\Http\Controllers\Admin\GradeScaleController;
use App\Http\Controllers\Admin\RetentionActionController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\Hr\HrDashboardController;
use App\Http\Controllers\Hr\HrLeaveManagementController;
use App\Http\Controllers\Hr\HrStaffAttendanceController;
use App\Http\Controllers\Guardian\GuardianAbsenceNoticeController;
use App\Http\Controllers\Guardian\GuardianAttendanceController;
use App\Http\Controllers\Guardian\GuardianDashboardController;
use App\Http\Controllers\Guardian\GuardianFeeController;
use App\Http\Controllers\Guardian\GuardianGradeController;
use App\Http\Controllers\Guardian\GuardianNoticeController;
use App\Http\Controllers\Hr\StaffRecordController;
use App\Http\Controllers\Leadership\FeeVisibilityController;
use App\Http\Controllers\Principal\BoardReportController;
use App\Http\Controllers\Principal\GovernanceController;
use App\Http\Controllers\Principal\PrincipalAnnouncementController;
use App\Http\Controllers\Principal\PrincipalApprovalController;
use App\Http\Controllers\Principal\PrincipalDashboardController;
use App\Http\Controllers\Principal\PrincipalRegistrationController;
use App\Http\Controllers\Registrar\AttendanceCorrectionController;
use App\Http\Controllers\Registrar\DocumentRequestController;
use App\Http\Controllers\Registrar\GuardianController;
use App\Http\Controllers\Registrar\PromotionBatchController;
use App\Http\Controllers\Registrar\RegistrarDashboardController;
use App\Http\Controllers\Registrar\SectionController;
use App\Http\Controllers\Registrar\StudentController;
use App\Http\Controllers\Registrar\StudentImportController;
use App\Http\Controllers\Registrar\TeachingAssignmentController;
use App\Http\Controllers\Student\StudentAttendanceController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Student\StudentGradeController;
use App\Http\Controllers\Student\StudentNoticeController;
use App\Http\Controllers\Student\StudentScheduleController;
use App\Http\Controllers\Teacher\GradeChangeRequestController;
use App\Http\Controllers\Teacher\TeacherAnnouncementController;
use App\Http\Controllers\Teacher\TeacherAttendanceController;
use App\Http\Controllers\Teacher\TeacherClassesController;
use App\Http\Controllers\Teacher\TeacherDashboardController;
use App\Http\Controllers\Teacher\TeacherGradebookController;
use App\Http\Controllers\Teacher\TeacherLeaveRequestController;
use App\Http\Controllers\Treasurer\FeeImportController;
use App\Http\Controllers\Treasurer\FeeReportController;
use App\Http\Controllers\Treasurer\FinanceInfoController;
use App\Http\Controllers\Treasurer\ImportBatchController as TreasurerImportBatchController;
use App\Http\Controllers\Treasurer\ImportedFeeRecordController;
use App\Http\Controllers\Treasurer\TreasurerDashboardController;
use App\Http\Controllers\Treasurer\ValidateMatchController;
use App\Http\Controllers\VpAcademic\SubjectCatalogController;
use App\Http\Controllers\VpAcademic\VpApprovalController;
use App\Http\Controllers\VpAcademic\VpDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// --- AUTHENTICATION ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->name('password.email');
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->name('password.update');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Forced set-password (§3.1) — reachable only while authenticated and flagged for reset.
Route::middleware('auth')->group(function () {
    Route::get('/set-password', [SetPasswordController::class, 'show'])->name('password.set');
    Route::post('/set-password', [SetPasswordController::class, 'update'])->name('password.set.update');
});

// --- ADMIN ---
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/users/import', [UserImportController::class, 'index'])->name('users.import');
    Route::post('/users/import', [UserImportController::class, 'store'])->name('users.import.store');
    Route::get('/users/import/template', [UserImportController::class, 'template'])->name('users.import.template');
    Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
    Route::post('/users/{user}/reactivate', [UserManagementController::class, 'reactivate'])->name('users.reactivate');
    Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('/users/{user}/set-password', [UserManagementController::class, 'setPassword'])->name('users.set-password');
    Route::post('/users/{user}/unlock', [UserManagementController::class, 'unlock'])->name('users.unlock');
    Route::post('/users/{user}/photo', [UserManagementController::class, 'uploadPhoto'])->name('users.photo.upload');
    Route::delete('/users/{user}/photo', [UserManagementController::class, 'deletePhoto'])->name('users.photo.delete');
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

    Route::get('/academic-year', [AcademicYearController::class, 'index'])->name('academic-year.index');
    Route::post('/academic-year', [AcademicYearController::class, 'store'])->name('academic-year.store');
    Route::post('/academic-year/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('academic-year.activate');
    Route::put('/academic-year/{academicYear}', [AcademicYearController::class, 'update'])->name('academic-year.update');
    Route::delete('/academic-year/{academicYear}', [AcademicYearController::class, 'destroy'])->name('academic-year.destroy');

    Route::get('/grade-scale', [GradeScaleController::class, 'index'])->name('grade-scale.index');
    Route::post('/grade-scale', [GradeScaleController::class, 'store'])->name('grade-scale.store');
    Route::delete('/grade-scale/{gradeScaleBand}', [GradeScaleController::class, 'destroy'])->name('grade-scale.destroy');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');

    Route::get('/settings', [SystemSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SystemSettingsController::class, 'update'])->name('settings.update');

    Route::post('/retention-actions', [RetentionActionController::class, 'store'])->name('retention-actions.store');

    Route::get('/export-snapshot', [DataExportController::class, 'download'])->name('export-snapshot');
});

// --- REGISTRAR ---
Route::middleware(['auth', 'role:registrar'])->prefix('registrar')->name('registrar.')->group(function () {
    Route::get('/dashboard', [RegistrarDashboardController::class, 'index'])->name('dashboard');

    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::get('/students/import', [StudentImportController::class, 'index'])->name('students.import');
    Route::post('/students/import', [StudentImportController::class, 'store'])->name('students.import.store');
    Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
    Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::post('/students/{student}/transfer', [StudentController::class, 'transfer'])->name('students.transfer');
    Route::post('/students/{student}/graduate', [StudentController::class, 'graduate'])->name('students.graduate');

    Route::get('/guardians', [GuardianController::class, 'index'])->name('guardians.index');
    Route::post('/guardians', [GuardianController::class, 'store'])->name('guardians.store');
    Route::get('/guardians/{guardian}', [GuardianController::class, 'show'])->name('guardians.show');
    Route::put('/guardians/{guardian}', [GuardianController::class, 'update'])->name('guardians.update');
    Route::post('/guardians/{guardian}/link', [GuardianController::class, 'link'])->name('guardians.link');
    Route::delete('/guardians/{guardian}/links/{student}', [GuardianController::class, 'unlink'])->name('guardians.unlink');
    Route::post('/guardians/{guardian}/links/{student}/primary', [GuardianController::class, 'setPrimary'])->name('guardians.set-primary');
    Route::post('/guardians/{guardian}/resend-invite', [GuardianController::class, 'resendInvite'])->name('guardians.resend-invite');

    Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
    Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
    Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
    Route::post('/sections/{section}/enroll', [SectionController::class, 'enroll'])->name('sections.enroll');

    Route::get('/teaching-assignments', [TeachingAssignmentController::class, 'index'])->name('teaching-assignments.index');
    Route::post('/teaching-assignments', [TeachingAssignmentController::class, 'store'])->name('teaching-assignments.store');
    Route::delete('/teaching-assignments/{teachingAssignment}', [TeachingAssignmentController::class, 'destroy'])->name('teaching-assignments.destroy');

    Route::get('/documents', [DocumentRequestController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentRequestController::class, 'store'])->name('documents.store');
    Route::post('/documents/{documentRequest}/submit-for-approval', [DocumentRequestController::class, 'submitForApproval'])->name('documents.submit-for-approval');
    Route::get('/documents/{documentRequest}/download', [DocumentRequestController::class, 'download'])->name('documents.download');

    Route::get('/promotions', [PromotionBatchController::class, 'index'])->name('promotions.index');
    Route::get('/promotions/{section}/create', [PromotionBatchController::class, 'create'])->name('promotions.create');
    Route::post('/promotions/{section}', [PromotionBatchController::class, 'store'])->name('promotions.store');

    Route::get('/attendance-corrections', [AttendanceCorrectionController::class, 'index'])->name('attendance-corrections.index');
    Route::put('/attendance-corrections/{attendanceRecord}', [AttendanceCorrectionController::class, 'update'])->name('attendance-corrections.update');
});

// --- TEACHER ---
Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/dashboard', [TeacherDashboardController::class, 'index'])->name('dashboard');

    Route::get('/classes', [TeacherClassesController::class, 'index'])->name('classes.index');

    Route::get('/attendance', [TeacherAttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance', [TeacherAttendanceController::class, 'store'])->name('attendance.store');
    Route::post('/attendance/notices/{absenceNotice}/acknowledge', [TeacherAttendanceController::class, 'acknowledge'])->name('attendance.acknowledge');

    Route::get('/gradebook', [TeacherGradebookController::class, 'index'])->name('gradebook.index');
    Route::post('/gradebook/categories', [TeacherGradebookController::class, 'storeCategory'])->name('gradebook.categories.store');
    Route::delete('/gradebook/categories/{assessmentCategory}', [TeacherGradebookController::class, 'destroyCategory'])->name('gradebook.categories.destroy');
    Route::post('/gradebook/assessments', [TeacherGradebookController::class, 'storeAssessment'])->name('gradebook.assessments.store');
    Route::post('/gradebook/scores', [TeacherGradebookController::class, 'saveScores'])->name('gradebook.scores.store');
    Route::post('/gradebook/comments', [TeacherGradebookController::class, 'saveComment'])->name('gradebook.comments.store');
    Route::post('/gradebook/change-requests', [GradeChangeRequestController::class, 'store'])->name('gradebook.change-requests.store');
    Route::post('/gradebook/change-requests/{gradeChangeRequest}/cancel', [GradeChangeRequestController::class, 'cancel'])->name('gradebook.change-requests.cancel');

    Route::get('/announcements', [TeacherAnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [TeacherAnnouncementController::class, 'store'])->name('announcements.store');

    Route::get('/leave', [TeacherLeaveRequestController::class, 'index'])->name('leave.index');
    Route::post('/leave', [TeacherLeaveRequestController::class, 'store'])->name('leave.store');
    Route::put('/leave/{leaveRequest}', [TeacherLeaveRequestController::class, 'update'])->name('leave.update');
    Route::post('/leave/{leaveRequest}/cancel', [TeacherLeaveRequestController::class, 'cancel'])->name('leave.cancel');
});

// --- TREASURER ---
Route::middleware(['auth', 'role:treasurer'])->prefix('treasurer')->name('treasurer.')->group(function () {
    Route::get('/dashboard', [TreasurerDashboardController::class, 'index'])->name('dashboard');

    Route::get('/info/source-prep', [FinanceInfoController::class, 'sourcePrep'])->name('info.source-prep');
    Route::get('/import-template', [FinanceInfoController::class, 'importTemplate'])->name('import-template');
    Route::get('/info/visibility-rules', [FinanceInfoController::class, 'visibilityRules'])->name('info.visibility-rules');

    Route::get('/import', [FeeImportController::class, 'index'])->name('import.index');
    Route::post('/import', [FeeImportController::class, 'store'])->name('import.store');

    Route::get('/validate', [ValidateMatchController::class, 'index'])->name('validate.index');
    Route::post('/validate/{importedFeeRecord}/resolve', [ValidateMatchController::class, 'resolve'])->name('validate.resolve');
    Route::post('/validate/{importedFeeRecord}/toggle-restrict', [ValidateMatchController::class, 'toggleRestrict'])->name('validate.toggle-restrict');
    Route::post('/validate/{importedFeeRecord}/toggle-hold', [ValidateMatchController::class, 'toggleHold'])->name('validate.toggle-hold');
    Route::post('/validate/batches/{importBatch}/publish', [ValidateMatchController::class, 'publish'])->name('validate.publish');

    Route::get('/records', [ImportedFeeRecordController::class, 'index'])->name('records.index');
    Route::get('/records/{student}', [ImportedFeeRecordController::class, 'show'])->name('records.show');

    Route::get('/history', [TreasurerImportBatchController::class, 'index'])->name('history.index');
    Route::delete('/history/{importBatch}', [TreasurerImportBatchController::class, 'revert'])->name('history.revert');

    Route::get('/reports', [FeeReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/outstanding', [FeeReportController::class, 'downloadOutstanding'])->name('reports.outstanding');
    Route::get('/reports/statement/{student}', [FeeReportController::class, 'downloadStatement'])->name('reports.statement');
});

// --- HR OFFICE ---
Route::middleware(['auth', 'role:hr_office'])->prefix('hr_office')->name('hr_office.')->group(function () {
    Route::get('/dashboard', [HrDashboardController::class, 'index'])->name('dashboard');

    Route::get('/staff', [StaffRecordController::class, 'index'])->name('staff.index');
    Route::get('/staff/create', [StaffRecordController::class, 'create'])->name('staff.create');
    Route::post('/staff', [StaffRecordController::class, 'store'])->name('staff.store');
    Route::get('/staff/{staffProfile}', [StaffRecordController::class, 'show'])->name('staff.show');
    Route::put('/staff/{staffProfile}/status', [StaffRecordController::class, 'updateStatus'])->name('staff.status');

    Route::get('/attendance', [HrStaffAttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance', [HrStaffAttendanceController::class, 'store'])->name('attendance.store');

    Route::get('/leave', [HrLeaveManagementController::class, 'index'])->name('leave.index');
    Route::post('/leave/on-behalf', [HrLeaveManagementController::class, 'submitOnBehalf'])->name('leave.submit-on-behalf');
    Route::post('/leave/{leaveRequest}/approve', [HrLeaveManagementController::class, 'approve'])->name('leave.approve');
    Route::post('/leave/{leaveRequest}/reject', [HrLeaveManagementController::class, 'reject'])->name('leave.reject');
});

// --- PRINCIPAL ---
Route::middleware(['auth', 'role:principal'])->prefix('principal')->name('principal.')->group(function () {
    Route::get('/dashboard', [PrincipalDashboardController::class, 'index'])->name('dashboard');

    Route::get('/approvals', [PrincipalApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/promotions/{promotionBatch}/approve', [PrincipalApprovalController::class, 'approvePromotion'])->name('approvals.promotions.approve');
    Route::post('/approvals/promotions/{promotionBatch}/reject', [PrincipalApprovalController::class, 'rejectPromotion'])->name('approvals.promotions.reject');
    Route::post('/approvals/transcripts/{documentRequest}/approve', [PrincipalApprovalController::class, 'approveTranscript'])->name('approvals.transcripts.approve');
    Route::post('/approvals/transcripts/{documentRequest}/reject', [PrincipalApprovalController::class, 'rejectTranscript'])->name('approvals.transcripts.reject');
    Route::post('/approvals/grade-changes/{gradeChangeRequest}/approve', [PrincipalApprovalController::class, 'approveGradeChange'])->name('approvals.grade-changes.approve');
    Route::post('/approvals/grade-changes/{gradeChangeRequest}/reject', [PrincipalApprovalController::class, 'rejectGradeChange'])->name('approvals.grade-changes.reject');

    Route::get('/board-reports', [BoardReportController::class, 'index'])->name('board-reports.index');
    Route::get('/board-reports/enrollment.pdf', [BoardReportController::class, 'enrollmentPdf'])->name('board-reports.enrollment-pdf');
    Route::get('/board-reports/religious-background.pdf', [BoardReportController::class, 'religiousPdf'])->name('board-reports.religious-pdf');

    Route::get('/announcements', [PrincipalAnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [PrincipalAnnouncementController::class, 'store'])->name('announcements.store');

    Route::get('/governance', [GovernanceController::class, 'index'])->name('governance.index');
    Route::put('/governance', [GovernanceController::class, 'update'])->name('governance.update');

    Route::get('/registration/create', [PrincipalRegistrationController::class, 'create'])->name('registration.create');
    Route::post('/registration', [PrincipalRegistrationController::class, 'store'])->name('registration.store');

    Route::get('/fees', [FeeVisibilityController::class, 'index'])->name('fees.index');
});

// --- VP ACADEMIC ---
Route::middleware(['auth', 'role:vp_academic'])->prefix('vp_academic')->name('vp_academic.')->group(function () {
    Route::get('/dashboard', [VpDashboardController::class, 'index'])->name('dashboard');

    Route::get('/approvals', [VpApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/promotions/{promotionBatch}/approve', [VpApprovalController::class, 'approvePromotion'])->name('approvals.promotions.approve');
    Route::post('/approvals/promotions/{promotionBatch}/reject', [VpApprovalController::class, 'rejectPromotion'])->name('approvals.promotions.reject');
    Route::post('/approvals/transcripts/{documentRequest}/approve', [VpApprovalController::class, 'approveTranscript'])->name('approvals.transcripts.approve');
    Route::post('/approvals/transcripts/{documentRequest}/reject', [VpApprovalController::class, 'rejectTranscript'])->name('approvals.transcripts.reject');
    Route::post('/approvals/grade-changes/{gradeChangeRequest}/approve', [VpApprovalController::class, 'approveGradeChange'])->name('approvals.grade-changes.approve');
    Route::post('/approvals/grade-changes/{gradeChangeRequest}/reject', [VpApprovalController::class, 'rejectGradeChange'])->name('approvals.grade-changes.reject');

    Route::get('/subjects', [SubjectCatalogController::class, 'index'])->name('subjects.index');
    Route::post('/subjects', [SubjectCatalogController::class, 'storeSubject'])->name('subjects.store');
    Route::delete('/subjects/{subject}', [SubjectCatalogController::class, 'destroySubject'])->name('subjects.destroy');
    Route::post('/assignments', [SubjectCatalogController::class, 'storeAssignment'])->name('assignments.store');
    Route::delete('/assignments/{teachingAssignment}', [SubjectCatalogController::class, 'destroyAssignment'])->name('assignments.destroy');

    Route::get('/fees', [FeeVisibilityController::class, 'index'])->name('fees.index');
});

// --- GUARDIAN ---
Route::middleware(['auth', 'role:guardian'])->prefix('guardian')->name('guardian.')->group(function () {
    Route::get('/dashboard', [GuardianDashboardController::class, 'index'])->name('dashboard');

    Route::get('/attendance', [GuardianAttendanceController::class, 'index'])->name('attendance.index');

    Route::get('/grades', [GuardianGradeController::class, 'index'])->name('grades.index');
    Route::get('/grades/report-card', [GuardianGradeController::class, 'reportCard'])->name('grades.report-card');

    Route::get('/fees', [GuardianFeeController::class, 'index'])->name('fees.index');
    Route::get('/fees/statement', [GuardianFeeController::class, 'statement'])->name('fees.statement');

    Route::get('/notices', [GuardianNoticeController::class, 'index'])->name('notices.index');

    Route::get('/absence-notices', [GuardianAbsenceNoticeController::class, 'index'])->name('absence-notices.index');
    Route::post('/absence-notices', [GuardianAbsenceNoticeController::class, 'store'])->name('absence-notices.store');
    Route::put('/absence-notices/{absenceNotice}', [GuardianAbsenceNoticeController::class, 'update'])->name('absence-notices.update');
    Route::post('/absence-notices/{absenceNotice}/cancel', [GuardianAbsenceNoticeController::class, 'cancel'])->name('absence-notices.cancel');
});

// --- STUDENT ---
Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');

    Route::get('/grades', [StudentGradeController::class, 'index'])->name('grades.index');
    Route::get('/grades/report-card', [StudentGradeController::class, 'reportCard'])->name('grades.report-card');

    Route::get('/schedule', [StudentScheduleController::class, 'index'])->name('schedule.index');
    Route::get('/attendance', [StudentAttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/notices', [StudentNoticeController::class, 'index'])->name('notices.index');
});
