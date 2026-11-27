<?php

use App\Http\Controllers\Admin\AcademicCalendarController;
use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\DataExportController;
use App\Http\Controllers\Admin\GradeLevelController;
use App\Http\Controllers\Admin\RetentionActionController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\TeacherAssignmentController as AdminTeacherAssignmentController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Registrar\AccountStatusController as RegistrarAccountStatusController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\Registrar\CalendarController as RegistrarCalendarController;
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
use App\Http\Controllers\Guardian\GuardianBehaviorController;
use App\Http\Controllers\Behavior\BehaviorAdminController;
use App\Http\Controllers\LessonPlans\LessonPlanController;
use App\Http\Controllers\Behavior\BehaviorReportController;
use App\Http\Controllers\Behavior\BehaviorStudentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Hr\StaffRecordController;
use App\Http\Controllers\Leadership\AnnouncementComposerController;
use App\Http\Controllers\Leadership\FeeVisibilityController;
use App\Http\Controllers\Principal\BoardReportController;
use App\Http\Controllers\Principal\GovernanceController;
use App\Http\Controllers\Principal\PrincipalApprovalController;
use App\Http\Controllers\Principal\PrincipalDashboardController;
use App\Http\Controllers\Principal\PrincipalRegistrationController;
use App\Http\Controllers\Registrar\AttendanceCorrectionController;
use App\Http\Controllers\CurriculumController;
use App\Http\Controllers\GradingSchemeController;
use App\Http\Controllers\GradebookTemplateController;
use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\ReportRulesController;
use App\Http\Controllers\Registrar\DocumentRequestController;
use App\Http\Controllers\Registrar\ElectiveController;
use App\Http\Controllers\Registrar\GuardianController;
use App\Http\Controllers\Registrar\PromotionBatchController;
use App\Http\Controllers\Registrar\RegistrarAnnouncementController;
use App\Http\Controllers\Registrar\RegistrarDashboardController;
use App\Http\Controllers\Registrar\SectionController;
use App\Http\Controllers\Registrar\StudentController;
use App\Http\Controllers\Registrar\StudentImportController;
use App\Http\Controllers\Registrar\TeachingAssignmentController;
use App\Http\Controllers\Student\StudentAttendanceController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Student\StudentGradeController;
use App\Http\Controllers\Student\StudentNoticeController;
use App\Http\Controllers\Student\StudentBehaviorController;
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

// Short role addresses (§3.3: Admin → /admin, VP → /vp, HR → /hr, …) forward to the role's dashboard,
// so typing /admin opens the sign-in page (then the dashboard) instead of a 404.
foreach (['admin', 'principal', 'vp_academic', 'registrar', 'teacher', 'treasurer', 'hr_office', 'guardian', 'student'] as $roleHome) {
    Route::redirect("/{$roleHome}", "/{$roleHome}/dashboard");
}
Route::redirect('/vp', '/vp_academic/dashboard');
Route::redirect('/hr', '/hr_office/dashboard');

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

    // Read-only academic calendar available to every signed-in user (Published events only).
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

    // Personal in-app notices, every role.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
});

// --- BEHAVIOR (staff) ---
// Who sees which student is decided by BehaviorAccess on every request; each write group below
// is also limited by role here, and the services check again.
Route::middleware(['auth', 'role:vp_academic|principal|admin|registrar|teacher'])->prefix('behavior')->name('behavior.')->group(function () {
    Route::get('/', [BehaviorStudentController::class, 'index'])->name('index');
    Route::get('/students/{student}', [BehaviorStudentController::class, 'show'])->name('students.show');
    Route::get('/reports/{report}', [BehaviorReportController::class, 'show'])->name('reports.show');

    Route::middleware('role:teacher')->group(function () {
        Route::get('/students/{student}/reports/create', [BehaviorReportController::class, 'create'])->name('reports.create');
        Route::post('/students/{student}/reports', [BehaviorReportController::class, 'store'])->name('reports.store');
        Route::get('/reports/{report}/edit', [BehaviorReportController::class, 'edit'])->name('reports.edit');
        Route::put('/reports/{report}', [BehaviorReportController::class, 'update'])->name('reports.update');
        Route::post('/reports/{report}/void', [BehaviorReportController::class, 'void'])->name('reports.void');
    });

    Route::middleware('role:vp_academic')->group(function () {
        Route::post('/students/{student}/vp-reports', [BehaviorReportController::class, 'vpStore'])->name('reports.vp-store');
        Route::post('/reports/{report}/dismiss', [BehaviorReportController::class, 'dismiss'])->name('reports.dismiss');
        Route::post('/reports/{report}/deduct', [BehaviorReportController::class, 'deduct'])->name('reports.deduct');
        Route::post('/entries/{entry}/restore', [BehaviorAdminController::class, 'restore'])->name('entries.restore');
        Route::get('/categories', [BehaviorAdminController::class, 'categories'])->name('categories.index');
        Route::post('/categories', [BehaviorAdminController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{category}', [BehaviorAdminController::class, 'updateCategory'])->name('categories.update');
    });

    Route::middleware('role:vp_academic|principal|admin|registrar')->group(function () {
        Route::get('/cases', [BehaviorAdminController::class, 'cases'])->name('cases.index');
        Route::get('/cases/{case}', [BehaviorAdminController::class, 'showCase'])->name('cases.show');
    });
    Route::post('/cases/{case}/decide', [BehaviorAdminController::class, 'decide'])->middleware('role:vp_academic|principal')->name('cases.decide');
    Route::post('/exit-requests/{exitRequest}/withdraw', [BehaviorAdminController::class, 'withdrawExit'])->middleware('role:principal')->name('exit-requests.withdraw');

    // Thresholds: a separate permission, not the Admin technical or Principal governance settings.
    Route::get('/policy', [BehaviorAdminController::class, 'policy'])->middleware('permission:behavior.policy.view')->name('policy.show');
    Route::get('/policy/preview', [BehaviorAdminController::class, 'previewPolicy'])->middleware('permission:behavior.policy.manage')->name('policy.preview');
    Route::put('/policy', [BehaviorAdminController::class, 'updatePolicy'])->middleware('permission:behavior.policy.manage')->name('policy.update');
});

// --- LESSON PLANS --- staff documents: Teacher, VP, Principal, Admin only (students and guardians
// are refused by the role check). Each write needs its permission; LessonPlanAccess decides every
// plan and file on every request, and LessonPlanService checks again.
// Report cards (§7.1): homeroom teachers for their own classes (ratings, confirmation), the Registrar,
// VP and Principal for every class. The attendance check (§8) is for the VP and Principal.
Route::middleware(['auth', 'role:teacher|registrar|vp_academic|principal'])->prefix('report-cards')->name('report-cards.')->group(function () {
    Route::get('/', [ReportCardController::class, 'index'])->name('index');
    Route::get('/attendance', [ReportCardController::class, 'attendance'])->middleware('role:vp_academic|principal')->name('attendance');
    Route::put('/{section}/{term}/personality', [ReportCardController::class, 'savePersonality'])->middleware('role:teacher')->name('personality');
    Route::post('/{section}/{term}/confirm', [ReportCardController::class, 'confirm'])->middleware('role:teacher')->name('confirm');
    Route::get('/{section}/{term}/{student}', [ReportCardController::class, 'show'])->name('show');
    Route::get('/{section}/{term}/{student}/pdf', [ReportCardController::class, 'pdf'])->name('pdf');
});

Route::middleware(['auth', 'role:teacher|vp_academic|principal|admin'])->prefix('lesson-plans')->name('lesson-plans.')->group(function () {
    Route::get('/', [LessonPlanController::class, 'index'])->name('index');
    Route::get('/review-queue', [LessonPlanController::class, 'queue'])->middleware('permission:lesson_plans.view_all')->name('queue');
    Route::post('/', [LessonPlanController::class, 'store'])->middleware('permission:lesson_plans.submit')->name('store');
    Route::get('/{lessonPlan}', [LessonPlanController::class, 'show'])->name('show');
    Route::put('/{lessonPlan}', [LessonPlanController::class, 'update'])->middleware('permission:lesson_plans.submit')->name('update');
    Route::delete('/{lessonPlan}', [LessonPlanController::class, 'destroy'])->middleware('permission:lesson_plans.submit')->name('destroy');
    Route::post('/{lessonPlan}/versions', [LessonPlanController::class, 'storeVersion'])->middleware('permission:lesson_plans.submit')->name('versions.store');
    Route::post('/{lessonPlan}/reviews', [LessonPlanController::class, 'review'])->middleware('permission:lesson_plans.review')->name('reviews.store');
    Route::get('/{lessonPlan}/versions/{version}/download', [LessonPlanController::class, 'download'])->scopeBindings()->name('versions.download');
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
    Route::get('/users/{user}/panel', [UserManagementController::class, 'panel'])->name('users.panel');
    Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
    Route::post('/users/{user}/reactivate', [UserManagementController::class, 'reactivate'])->name('users.reactivate');
    Route::post('/users/{user}/activate', [UserManagementController::class, 'activate'])->name('users.activate');
    Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('/users/{user}/set-password', [UserManagementController::class, 'setPassword'])->name('users.set-password');
    Route::post('/users/{user}/unlock', [UserManagementController::class, 'unlock'])->name('users.unlock');
    Route::post('/users/{user}/photo', [UserManagementController::class, 'uploadPhoto'])->name('users.photo.upload');
    Route::delete('/users/{user}/photo', [UserManagementController::class, 'deletePhoto'])->name('users.photo.delete');
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

    Route::get('/teacher-assignments', [AdminTeacherAssignmentController::class, 'index'])->name('teacher-assignments.index');
    Route::post('/teacher-assignments/set', [AdminTeacherAssignmentController::class, 'setSubjectTeacher'])->name('teacher-assignments.set');
    Route::put('/teacher-assignments/sections/{section}/homeroom', [AdminTeacherAssignmentController::class, 'setHomeroom'])->name('teacher-assignments.homeroom');

    Route::get('/academic-year', [AcademicYearController::class, 'index'])->name('academic-year.index');
    Route::post('/academic-year', [AcademicYearController::class, 'store'])->name('academic-year.store');
    Route::post('/academic-year/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('academic-year.activate');
    Route::put('/academic-year/{academicYear}', [AcademicYearController::class, 'update'])->name('academic-year.update');
    Route::delete('/academic-year/{academicYear}', [AcademicYearController::class, 'destroy'])->name('academic-year.destroy');

    Route::get('/calendar', [AcademicCalendarController::class, 'index'])->name('calendar.index');
    Route::post('/calendar', [AcademicCalendarController::class, 'store'])->name('calendar.store');
    Route::put('/calendar/{calendarEvent}', [AcademicCalendarController::class, 'update'])->name('calendar.update');
    Route::post('/calendar/{calendarEvent}/publish', [AcademicCalendarController::class, 'publish'])->name('calendar.publish');
    Route::post('/calendar/{calendarEvent}/unpublish', [AcademicCalendarController::class, 'unpublish'])->name('calendar.unpublish');
    Route::delete('/calendar/{calendarEvent}', [AcademicCalendarController::class, 'destroy'])->name('calendar.destroy');

    Route::get('/curriculum', [CurriculumController::class, 'index'])->name('curriculum.index');
    Route::post('/curriculum/levels/{gradeLevel}', [CurriculumController::class, 'store'])->name('curriculum.store');
    Route::put('/curriculum/rows/{subjectOffering}', [CurriculumController::class, 'update'])->name('curriculum.update');
    Route::post('/curriculum/rows/{subjectOffering}/move/{direction}', [CurriculumController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('curriculum.move');
    Route::post('/curriculum/rows/{subjectOffering}/retire', [CurriculumController::class, 'retire'])->name('curriculum.retire');
    Route::post('/curriculum/rows/{subjectOffering}/restore', [CurriculumController::class, 'restore'])->name('curriculum.restore');

    Route::get('/grade-levels', [GradeLevelController::class, 'index'])->name('grade-levels.index');
    Route::post('/grade-levels', [GradeLevelController::class, 'store'])->name('grade-levels.store');
    Route::put('/grade-levels/{gradeLevel}', [GradeLevelController::class, 'update'])->name('grade-levels.update');
    Route::post('/grade-levels/{gradeLevel}/move/{direction}', [GradeLevelController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('grade-levels.move');
    Route::post('/grade-levels/{gradeLevel}/retire', [GradeLevelController::class, 'retire'])->name('grade-levels.retire');
    Route::post('/grade-levels/{gradeLevel}/restore', [GradeLevelController::class, 'restore'])->name('grade-levels.restore');

    Route::get('/grading-schemes', [GradingSchemeController::class, 'index'])->name('grading-schemes.index');
    Route::put('/grading-schemes/levels/{gradeLevel}', [GradingSchemeController::class, 'assign'])->name('grading-schemes.assign');
    Route::put('/grading-schemes/{gradingScheme}', [GradingSchemeController::class, 'update'])->name('grading-schemes.update');
    Route::put('/grading-schemes/{gradingScheme}/bands/{kind}', [GradingSchemeController::class, 'updateBands'])->whereIn('kind', ['mark', 'result'])->name('grading-schemes.bands');

    Route::get('/gradebook-template', [GradebookTemplateController::class, 'index'])->name('gradebook-template.index');
    Route::put('/gradebook-template', [GradebookTemplateController::class, 'update'])->name('gradebook-template.update');

    Route::get('/report-rules', [ReportRulesController::class, 'index'])->name('report-rules.index');
    Route::put('/report-rules', [ReportRulesController::class, 'update'])->name('report-rules.update');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
    Route::get('/audit-logs/verify', [AuditLogController::class, 'verify'])->name('audit-logs.verify');

    Route::get('/settings', [SystemSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SystemSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/test-smtp', [SystemSettingsController::class, 'testSmtp'])->name('settings.test-smtp');

    Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::post('/retention-actions', [RetentionActionController::class, 'store'])->name('retention-actions.store');

    Route::get('/export-snapshot', [DataExportController::class, 'download'])->name('export-snapshot');
});

// --- REGISTRAR ---
Route::middleware(['auth', 'role:registrar'])->prefix('registrar')->name('registrar.')->group(function () {
    Route::get('/dashboard', [RegistrarDashboardController::class, 'index'])->name('dashboard');

    // §2.2 fees.view_readonly: read-only fee visibility, same rules as the Principal/VP.
    Route::get('/fees', [FeeVisibilityController::class, 'index'])->name('fees.index');
    Route::get('/fees/{student}', [FeeVisibilityController::class, 'show'])->name('fees.show');

    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::get('/students/import', [StudentImportController::class, 'index'])->name('students.import');
    Route::post('/students/import', [StudentImportController::class, 'store'])->name('students.import.store');
    Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
    Route::get('/students/{student}/panel', [StudentController::class, 'panel'])->name('students.panel');
    Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::post('/students/{student}/transfer', [StudentController::class, 'transfer'])->name('students.transfer');
    Route::post('/students/{student}/graduate', [StudentController::class, 'graduate'])->name('students.graduate');
    Route::post('/students/{student}/login', [StudentController::class, 'storeLogin'])->name('students.login.store');

    // Guardian and student portal accounts only — the service refuses (403) any other account id.
    Route::post('/accounts/{user}/deactivate', [RegistrarAccountStatusController::class, 'deactivate'])->name('accounts.deactivate');
    Route::post('/accounts/{user}/reactivate', [RegistrarAccountStatusController::class, 'reactivate'])->name('accounts.reactivate');
    Route::post('/accounts/{user}/activate', [RegistrarAccountStatusController::class, 'activate'])->name('accounts.activate');
    Route::post('/accounts/{user}/resend-invite', [RegistrarAccountStatusController::class, 'resendInvite'])->name('accounts.resend-invite');

    Route::get('/guardians', [GuardianController::class, 'index'])->name('guardians.index');
    Route::post('/guardians', [GuardianController::class, 'store'])->name('guardians.store');
    Route::get('/guardians/{guardian}', [GuardianController::class, 'show'])->name('guardians.show');
    Route::put('/guardians/{guardian}', [GuardianController::class, 'update'])->name('guardians.update');
    Route::post('/guardians/{guardian}/link', [GuardianController::class, 'link'])->name('guardians.link');
    Route::delete('/guardians/{guardian}/links/{student}', [GuardianController::class, 'unlink'])->name('guardians.unlink');
    Route::post('/guardians/{guardian}/links/{student}/primary', [GuardianController::class, 'setPrimary'])->name('guardians.set-primary');
    Route::post('/guardians/{guardian}/resend-invite', [GuardianController::class, 'resendInvite'])->name('guardians.resend-invite');

    Route::get('/electives', [ElectiveController::class, 'index'])->name('electives.index');
    Route::get('/electives/{section}', [ElectiveController::class, 'show'])->name('electives.show');
    Route::put('/electives/{section}', [ElectiveController::class, 'update'])->name('electives.update');

    Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
    Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
    Route::get('/sections/{section}', [SectionController::class, 'show'])->name('sections.show');
    Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
    Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
    Route::post('/sections/{section}/archive', [SectionController::class, 'archive'])->name('sections.archive');
    Route::post('/sections/{section}/restore', [SectionController::class, 'restore'])->name('sections.restore');
    Route::post('/sections/{section}/enroll', [SectionController::class, 'enroll'])->name('sections.enroll');
    Route::put('/sections/{section}/assignments', [TeachingAssignmentController::class, 'bulk'])->name('sections.assignments.bulk');

    Route::get('/teaching-assignments', [TeachingAssignmentController::class, 'index'])->name('teaching-assignments.index');
    Route::post('/teaching-assignments', [TeachingAssignmentController::class, 'store'])->name('teaching-assignments.store');
    Route::put('/teaching-assignments/{teachingAssignment}', [TeachingAssignmentController::class, 'update'])->name('teaching-assignments.update');
    Route::delete('/teaching-assignments/{teachingAssignment}', [TeachingAssignmentController::class, 'destroy'])->name('teaching-assignments.destroy');

    Route::get('/documents', [DocumentRequestController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentRequestController::class, 'store'])->name('documents.store');
    Route::post('/documents/{documentRequest}/submit-for-approval', [DocumentRequestController::class, 'submitForApproval'])->name('documents.submit-for-approval');
    Route::get('/documents/{documentRequest}/download', [DocumentRequestController::class, 'download'])->name('documents.download');

    Route::get('/promotions', [PromotionBatchController::class, 'index'])->name('promotions.index');
    Route::get('/promotions/{section}/create', [PromotionBatchController::class, 'create'])->name('promotions.create');
    Route::post('/promotions/{section}', [PromotionBatchController::class, 'store'])->name('promotions.store');

    Route::get('/announcements', [RegistrarAnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [RegistrarAnnouncementController::class, 'store'])->name('announcements.store');

    Route::get('/attendance-corrections', [AttendanceCorrectionController::class, 'index'])->name('attendance-corrections.index');
    Route::put('/attendance-corrections/{attendanceRecord}', [AttendanceCorrectionController::class, 'update'])->name('attendance-corrections.update');

    // Academic calendar — Registrar creates/edits; Admin approves & publishes.
    Route::get('/calendar', [RegistrarCalendarController::class, 'index'])->name('calendar.index');
    Route::post('/calendar', [RegistrarCalendarController::class, 'store'])->name('calendar.store');
    Route::put('/calendar/{calendarEvent}', [RegistrarCalendarController::class, 'update'])->name('calendar.update');
    Route::delete('/calendar/{calendarEvent}', [RegistrarCalendarController::class, 'destroy'])->name('calendar.destroy');
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
    Route::patch('/gradebook/assessments/{assessment}', [TeacherGradebookController::class, 'updateAssessment'])->name('gradebook.assessments.update');
    Route::delete('/gradebook/assessments/{assessment}', [TeacherGradebookController::class, 'destroyAssessment'])->name('gradebook.assessments.destroy');
    Route::patch('/gradebook/assessments/{assessment}/move', [TeacherGradebookController::class, 'moveAssessment'])->name('gradebook.assessments.move');
    Route::post('/gradebook/assessments/{assessment}/restore', [TeacherGradebookController::class, 'restoreAssessment'])->name('gradebook.assessments.restore');
    Route::post('/gradebook/categories/{assessmentCategory}/restore', [TeacherGradebookController::class, 'restoreCategory'])->name('gradebook.categories.restore');
    Route::get('/gradebook/assessments/{assessment}/scores', [TeacherGradebookController::class, 'itemScores'])->name('gradebook.assessments.scores');
    Route::put('/gradebook/assessments/{assessment}/scores', [TeacherGradebookController::class, 'saveItemScores'])->name('gradebook.assessments.scores.save');
    Route::get('/gradebook/assessments/{assessment}/scores-template', [TeacherGradebookController::class, 'scoresTemplate'])->name('gradebook.assessments.scores-template');
    Route::post('/gradebook/assessments/{assessment}/scores-import', [TeacherGradebookController::class, 'importScores'])->name('gradebook.assessments.scores-import');
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
    Route::put('/info/visibility-rules/policy', [FinanceInfoController::class, 'updatePolicy'])->name('info.visibility-policy');
    Route::post('/info/visibility-rules/batches/{importBatch}/confirm', [FinanceInfoController::class, 'confirmRestricted'])->name('info.confirm-restricted');

    Route::get('/import', [FeeImportController::class, 'index'])->name('import.index');
    Route::post('/import', [FeeImportController::class, 'store'])->name('import.store');

    Route::get('/validate', [ValidateMatchController::class, 'index'])->name('validate.index');
    Route::post('/validate/{importedFeeRecord}/resolve', [ValidateMatchController::class, 'resolve'])->name('validate.resolve');
    Route::post('/validate/{importedFeeRecord}/unmatch', [ValidateMatchController::class, 'unmatch'])->name('validate.unmatch');
    Route::post('/validate/{importedFeeRecord}/toggle-restrict', [ValidateMatchController::class, 'toggleRestrict'])->name('validate.toggle-restrict');
    Route::post('/validate/{importedFeeRecord}/toggle-hold', [ValidateMatchController::class, 'toggleHold'])->name('validate.toggle-hold');
    Route::post('/validate/batches/{importBatch}/publish', [ValidateMatchController::class, 'publish'])->name('validate.publish');

    Route::get('/records', [ImportedFeeRecordController::class, 'index'])->name('records.index');
    Route::get('/records/{student}', [ImportedFeeRecordController::class, 'show'])->name('records.show');

    Route::get('/history', [TreasurerImportBatchController::class, 'index'])->name('history.index');
    Route::delete('/history/{importBatch}', [TreasurerImportBatchController::class, 'revert'])->name('history.revert');

    Route::get('/reports', [FeeReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/outstanding', [FeeReportController::class, 'downloadOutstanding'])->name('reports.outstanding');
    Route::get('/reports/aging', [FeeReportController::class, 'downloadAging'])->name('reports.aging');
    Route::get('/reports/find-statement', [FeeReportController::class, 'findStatement'])->name('reports.find-statement');
    Route::get('/reports/statement/{student}', [FeeReportController::class, 'downloadStatement'])->name('reports.statement');
});

// --- HR OFFICE ---
Route::middleware(['auth', 'role:hr_office'])->prefix('hr_office')->name('hr_office.')->group(function () {
    Route::get('/dashboard', [HrDashboardController::class, 'index'])->name('dashboard');

    Route::get('/staff', [StaffRecordController::class, 'index'])->name('staff.index');
    Route::get('/staff/create', [StaffRecordController::class, 'create'])->name('staff.create');
    Route::post('/staff', [StaffRecordController::class, 'store'])->name('staff.store');
    Route::get('/staff/{staffProfile}/panel', [StaffRecordController::class, 'panel'])->name('staff.panel');
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

    Route::get('/announcements', [AnnouncementComposerController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [AnnouncementComposerController::class, 'store'])->name('announcements.store');

    Route::get('/governance', [GovernanceController::class, 'index'])->name('governance.index');
    Route::get('/curriculum', [CurriculumController::class, 'index'])->name('curriculum.index');
    Route::get('/grading-schemes', [GradingSchemeController::class, 'index'])->name('grading-schemes.index');
    Route::get('/gradebook-template', [GradebookTemplateController::class, 'index'])->name('gradebook-template.index');
    Route::get('/report-rules', [ReportRulesController::class, 'index'])->name('report-rules.index');
    Route::put('/governance', [GovernanceController::class, 'update'])->name('governance.update');

    Route::get('/registration/create', [PrincipalRegistrationController::class, 'create'])->name('registration.create');
    Route::post('/registration', [PrincipalRegistrationController::class, 'store'])->name('registration.store');

    Route::get('/fees', [FeeVisibilityController::class, 'index'])->name('fees.index');
    Route::get('/fees/{student}', [FeeVisibilityController::class, 'show'])->name('fees.show');
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

    // Same composer as the Principal's (shared controller and view); this group's role check
    // limits publishing here to the VP.
    Route::get('/announcements', [AnnouncementComposerController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [AnnouncementComposerController::class, 'store'])->name('announcements.store');

    Route::get('/fees', [FeeVisibilityController::class, 'index'])->name('fees.index');
    Route::get('/fees/{student}', [FeeVisibilityController::class, 'show'])->name('fees.show');
});

// --- GUARDIAN ---
Route::middleware(['auth', 'role:guardian'])->prefix('guardian')->name('guardian.')->group(function () {
    Route::get('/dashboard', [GuardianDashboardController::class, 'index'])->name('dashboard');

    Route::get('/attendance', [GuardianAttendanceController::class, 'index'])->name('attendance.index');

    Route::get('/grades', [GuardianGradeController::class, 'index'])->name('grades.index');
    Route::get('/grades/report-card', [GuardianGradeController::class, 'reportCard'])->name('grades.report-card');
    Route::get('/report-cards/{term}', [GuardianGradeController::class, 'showReportCard'])->name('report-cards.show');
    Route::post('/report-cards/{term}/acknowledge', [GuardianGradeController::class, 'acknowledge'])->name('report-cards.acknowledge');

    Route::get('/fees', [GuardianFeeController::class, 'index'])->name('fees.index');
    Route::get('/fees/statement', [GuardianFeeController::class, 'statement'])->name('fees.statement');

    Route::get('/notices', [GuardianNoticeController::class, 'index'])->name('notices.index');
    Route::get('/behavior', [GuardianBehaviorController::class, 'index'])->name('behavior.index');

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
    Route::get('/report-cards/{term}', [StudentGradeController::class, 'showReportCard'])->name('report-cards.show');

    Route::get('/schedule', [StudentScheduleController::class, 'index'])->name('schedule.index');
    Route::get('/attendance', [StudentAttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/notices', [StudentNoticeController::class, 'index'])->name('notices.index');
    Route::get('/behavior', [StudentBehaviorController::class, 'index'])->name('behavior.index');
});
