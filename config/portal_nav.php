<?php

// Per-role sidebar navigation for the app shell layout (resources/views/components/app-layout.blade.php).
// Keyed by Spatie role slug. Each entry: label, route name, portal label shown under the logo.
return [
    'admin' => [
        'portal_label' => 'Admin Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard'],
            ['label' => 'User Management', 'route' => 'admin.users.index'],
            ['label' => 'Teacher Class Assignment', 'route' => 'admin.teacher-assignments.index'],
            ['label' => 'Academic Year', 'route' => 'admin.academic-year.index'],
            ['label' => 'Grade Scale', 'route' => 'admin.grade-scale.index'],
            ['label' => 'Audit Logs', 'route' => 'admin.audit-logs.index'],
            ['label' => 'System Settings', 'route' => 'admin.settings.index'],
        ],
    ],
    'principal' => [
        'portal_label' => 'Principal Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'principal.dashboard'],
            ['label' => 'Approvals', 'route' => 'principal.approvals.index'],
            ['label' => 'Board Reports', 'route' => 'principal.board-reports.index'],
            ['label' => 'Announcements', 'route' => 'principal.announcements.index'],
            ['label' => 'Setup & Controls', 'route' => 'principal.governance.index'],
        ],
    ],
    'vp_academic' => [
        'portal_label' => 'VP Academic Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'vp_academic.dashboard'],
            ['label' => 'Approvals', 'route' => 'vp_academic.approvals.index'],
            ['label' => 'Subjects & Teaching', 'route' => 'vp_academic.subjects.index'],
            ['label' => 'Imported Fee Records', 'route' => 'vp_academic.fees.index'],
        ],
    ],
    'registrar' => [
        'portal_label' => 'Registrar Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'registrar.dashboard'],
            ['label' => 'Register Student', 'route' => 'registrar.students.create'],
            ['label' => 'Students', 'route' => 'registrar.students.index'],
            ['label' => 'Guardians', 'route' => 'registrar.guardians.index'],
            ['label' => 'Sections', 'route' => 'registrar.sections.index'],
            ['label' => 'Transcripts', 'route' => 'registrar.documents.index'],
            ['label' => 'Announcements', 'route' => 'registrar.announcements.index'],
            ['label' => 'Promotions', 'route' => 'registrar.promotions.index'],
            ['label' => 'Absence Corrections', 'route' => 'registrar.attendance-corrections.index'],
            ['label' => 'Teacher Assignment', 'route' => 'registrar.teaching-assignments.index'],
        ],
    ],
    'teacher' => [
        'portal_label' => 'Teacher Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'teacher.dashboard'],
            ['label' => 'My Classes', 'route' => 'teacher.classes.index'],
            ['label' => 'Attendance', 'route' => 'teacher.attendance.index'],
            ['label' => 'Gradebook', 'route' => 'teacher.gradebook.index'],
            ['label' => 'Announcements', 'route' => 'teacher.announcements.index'],
            ['label' => 'Leave Request', 'route' => 'teacher.leave.index'],
        ],
    ],
    'treasurer' => [
        'portal_label' => 'Treasurer Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'treasurer.dashboard'],
            ['label' => 'Source Prep', 'route' => 'treasurer.info.source-prep'],
            ['label' => 'Import Records', 'route' => 'treasurer.import.index'],
            ['label' => 'Validate & Match', 'route' => 'treasurer.validate.index'],
            ['label' => 'Imported Records', 'route' => 'treasurer.records.index'],
            ['label' => 'Fee Reports', 'route' => 'treasurer.reports.index'],
            ['label' => 'History', 'route' => 'treasurer.history.index'],
            ['label' => 'Visibility Rules', 'route' => 'treasurer.info.visibility-rules'],
        ],
    ],
    'hr_office' => [
        'portal_label' => 'HR Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'hr_office.dashboard'],
            ['label' => 'Staff Records', 'route' => 'hr_office.staff.index'],
            ['label' => 'Attendance', 'route' => 'hr_office.attendance.index'],
            ['label' => 'Leave Management', 'route' => 'hr_office.leave.index'],
        ],
    ],
    'guardian' => [
        'portal_label' => 'Guardian Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'guardian.dashboard'],
            ['label' => 'Attendance', 'route' => 'guardian.attendance.index'],
            ['label' => 'Grades & Reports', 'route' => 'guardian.grades.index'],
            ['label' => 'Fees', 'route' => 'guardian.fees.index'],
            ['label' => 'Notices', 'route' => 'guardian.notices.index'],
            ['label' => 'Notify Absence', 'route' => 'guardian.absence-notices.index'],
        ],
    ],
    'student' => [
        'portal_label' => 'Student Portal',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'student.dashboard'],
            ['label' => 'Grades', 'route' => 'student.grades.index'],
            ['label' => 'Schedule', 'route' => 'student.schedule.index'],
            ['label' => 'Attendance', 'route' => 'student.attendance.index'],
            ['label' => 'Notices', 'route' => 'student.notices.index'],
        ],
    ],
];
