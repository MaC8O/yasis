<?php

// Per-role sidebar navigation for the app shell layout (resources/views/components/app-layout.blade.php).
// Keyed by Spatie role slug. Each entry: label, route name, icon (see components/icon.blade.php);
// plus the portal label shown under the logo.
return [
    'admin' => [
        'portal_label' => 'Admin Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'home'],
            ['label' => 'User Management', 'route' => 'admin.users.index', 'icon' => 'users'],
            ['label' => 'Teacher Class Assignment', 'route' => 'admin.teacher-assignments.index', 'icon' => 'layers'],
            ['label' => 'Academic Year', 'route' => 'admin.academic-year.index', 'icon' => 'calendar'],
            ['label' => 'Academic Calendar', 'route' => 'admin.calendar.index', 'icon' => 'calendar'],
            ['label' => 'Grade Scale', 'route' => 'admin.grade-scale.index', 'icon' => 'list'],
            ['label' => 'Audit Logs', 'route' => 'admin.audit-logs.index', 'icon' => 'shield'],
            ['label' => 'Data & Backup', 'route' => 'admin.backup.index', 'icon' => 'database'],
            ['label' => 'System Settings', 'route' => 'admin.settings.index', 'icon' => 'sliders'],
        ],
    ],
    'principal' => [
        'portal_label' => 'Principal Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'principal.dashboard', 'icon' => 'home'],
            ['label' => 'Approvals', 'route' => 'principal.approvals.index', 'icon' => 'approve'],
            ['label' => 'Board Reports', 'route' => 'principal.board-reports.index', 'icon' => 'chart'],
            ['label' => 'Fee Records', 'route' => 'principal.fees.index', 'icon' => 'money'],
            ['label' => 'Announcements', 'route' => 'principal.announcements.index', 'icon' => 'megaphone'],
            ['label' => 'Academic Calendar', 'route' => 'calendar.index', 'icon' => 'calendar'],
            ['label' => 'Setup & Controls', 'route' => 'principal.governance.index', 'icon' => 'sliders'],
        ],
    ],
    'vp_academic' => [
        'portal_label' => 'VP Academic Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'vp_academic.dashboard', 'icon' => 'home'],
            ['label' => 'Approvals', 'route' => 'vp_academic.approvals.index', 'icon' => 'approve'],
            ['label' => 'Subjects & Teaching', 'route' => 'vp_academic.subjects.index', 'icon' => 'book'],
            ['label' => 'Fee Records', 'route' => 'vp_academic.fees.index', 'icon' => 'money'],
            ['label' => 'Academic Calendar', 'route' => 'calendar.index', 'icon' => 'calendar'],
        ],
    ],
    'registrar' => [
        'portal_label' => 'Registrar Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'registrar.dashboard', 'icon' => 'home'],
            ['label' => 'Register Student', 'route' => 'registrar.students.create', 'icon' => 'user-plus'],
            ['label' => 'Students', 'route' => 'registrar.students.index', 'icon' => 'student'],
            ['label' => 'Guardians', 'route' => 'registrar.guardians.index', 'icon' => 'users'],
            ['label' => 'Sections', 'route' => 'registrar.sections.index', 'icon' => 'layers'],
            ['label' => 'Transcripts', 'route' => 'registrar.documents.index', 'icon' => 'document'],
            ['label' => 'Fee Records', 'route' => 'registrar.fees.index', 'icon' => 'money'],
            ['label' => 'Announcements', 'route' => 'registrar.announcements.index', 'icon' => 'megaphone'],
            ['label' => 'Promotions', 'route' => 'registrar.promotions.index', 'icon' => 'arrow-up'],
            ['label' => 'Absence Corrections', 'route' => 'registrar.attendance-corrections.index', 'icon' => 'pencil'],
            ['label' => 'Teacher Assignment', 'route' => 'registrar.teaching-assignments.index', 'icon' => 'briefcase'],
            ['label' => 'Academic Calendar', 'route' => 'registrar.calendar.index', 'icon' => 'calendar'],
        ],
    ],
    'teacher' => [
        'portal_label' => 'Teacher Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'teacher.dashboard', 'icon' => 'home'],
            ['label' => 'My Classes', 'route' => 'teacher.classes.index', 'icon' => 'layers'],
            ['label' => 'Attendance', 'route' => 'teacher.attendance.index', 'icon' => 'clipboard'],
            ['label' => 'Gradebook', 'route' => 'teacher.gradebook.index', 'icon' => 'book'],
            ['label' => 'Announcements', 'route' => 'teacher.announcements.index', 'icon' => 'megaphone'],
            ['label' => 'Academic Calendar', 'route' => 'calendar.index', 'icon' => 'calendar'],
            ['label' => 'Leave Request', 'route' => 'teacher.leave.index', 'icon' => 'leave'],
        ],
    ],
    'treasurer' => [
        'portal_label' => 'Treasurer Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'treasurer.dashboard', 'icon' => 'home'],
            ['label' => 'Source Prep', 'route' => 'treasurer.info.source-prep', 'icon' => 'info'],
            ['label' => 'Import Records', 'route' => 'treasurer.import.index', 'icon' => 'upload'],
            ['label' => 'Validate & Match', 'route' => 'treasurer.validate.index', 'icon' => 'check-square'],
            ['label' => 'Imported Records', 'route' => 'treasurer.records.index', 'icon' => 'document'],
            ['label' => 'Fee Reports', 'route' => 'treasurer.reports.index', 'icon' => 'chart'],
            ['label' => 'History', 'route' => 'treasurer.history.index', 'icon' => 'clock'],
            ['label' => 'Academic Calendar', 'route' => 'calendar.index', 'icon' => 'calendar'],
            ['label' => 'Visibility Rules', 'route' => 'treasurer.info.visibility-rules', 'icon' => 'eye'],
        ],
    ],
    'hr_office' => [
        'portal_label' => 'HR Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'hr_office.dashboard', 'icon' => 'home'],
            ['label' => 'Staff Records', 'route' => 'hr_office.staff.index', 'icon' => 'briefcase'],
            ['label' => 'Attendance', 'route' => 'hr_office.attendance.index', 'icon' => 'clipboard'],
            ['label' => 'Leave Management', 'route' => 'hr_office.leave.index', 'icon' => 'leave'],
            ['label' => 'Academic Calendar', 'route' => 'calendar.index', 'icon' => 'calendar'],
        ],
    ],
    'guardian' => [
        'portal_label' => 'Guardian Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'guardian.dashboard', 'icon' => 'home'],
            ['label' => 'Attendance', 'route' => 'guardian.attendance.index', 'icon' => 'clipboard'],
            ['label' => 'Grades & Reports', 'route' => 'guardian.grades.index', 'icon' => 'book'],
            ['label' => 'Fees', 'route' => 'guardian.fees.index', 'icon' => 'money'],
            ['label' => 'Notices', 'route' => 'guardian.notices.index', 'icon' => 'bell'],
            ['label' => 'Academic Calendar', 'route' => 'calendar.index', 'icon' => 'calendar'],
            ['label' => 'Notify Absence', 'route' => 'guardian.absence-notices.index', 'icon' => 'pencil'],
        ],
    ],
    'student' => [
        'portal_label' => 'Student Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'student.dashboard', 'icon' => 'home'],
            ['label' => 'Grades', 'route' => 'student.grades.index', 'icon' => 'book'],
            ['label' => 'Schedule', 'route' => 'student.schedule.index', 'icon' => 'clock'],
            ['label' => 'Attendance', 'route' => 'student.attendance.index', 'icon' => 'clipboard'],
            ['label' => 'Academic Calendar', 'route' => 'calendar.index', 'icon' => 'calendar'],
            ['label' => 'Notices', 'route' => 'student.notices.index', 'icon' => 'bell'],
        ],
    ],
];
