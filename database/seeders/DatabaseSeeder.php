<?php

namespace Database\Seeders;

use App\Models\AbsenceNotice;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\Department;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\GradeScaleBand;
use App\Models\ImportBatch;
use App\Models\ImportedFeeRecord;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PromotionBatch;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = ['admin', 'principal', 'vp_academic', 'registrar', 'teacher', 'treasurer', 'hr_office', 'guardian', 'student'];
        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
        }

        $departments = [
            ['name' => 'High School', 'level' => 'Secondary'],
            ['name' => 'Middle School', 'level' => 'Secondary'],
            ['name' => 'Elementary', 'level' => 'Primary'],
            ['name' => 'Pre-School', 'level' => 'Early Years'],
        ];
        foreach ($departments as $department) {
            Department::firstOrCreate(['name' => $department['name']], $department);
        }
        $highSchool = Department::where('name', 'High School')->first();

        // Non-academic, HR-org-chart departments (§3.2) — reuse the same departments table since both are
        // just organisational groupings; 'level' distinguishes academic from administrative branches.
        $hrDepartments = [
            ['name' => 'Administration', 'level' => 'Administrative'],
            ['name' => 'Finance', 'level' => 'Administrative'],
            ['name' => 'Human Resource', 'level' => 'Administrative'],
            ['name' => 'IT & Media', 'level' => 'Administrative'],
            ['name' => 'Transportation', 'level' => 'Administrative'],
            ['name' => 'Canteen', 'level' => 'Administrative'],
            ['name' => 'Maintenance', 'level' => 'Administrative'],
        ];
        foreach ($hrDepartments as $department) {
            Department::firstOrCreate(['name' => $department['name']], $department);
        }

        // Placeholder A–F scale per department, flagged pending the school's actual grade-scale documents (§3.6).
        $bands = [
            ['letter' => 'A', 'min_score' => 90, 'gpa_point' => 4.00],
            ['letter' => 'B', 'min_score' => 80, 'gpa_point' => 3.00],
            ['letter' => 'C', 'min_score' => 70, 'gpa_point' => 2.00],
            ['letter' => 'D', 'min_score' => 60, 'gpa_point' => 1.00],
            ['letter' => 'F', 'min_score' => 0, 'gpa_point' => 0.00],
        ];
        foreach (Department::all() as $department) {
            foreach ($bands as $band) {
                GradeScaleBand::firstOrCreate(array_merge(['department_id' => $department->id], $band));
            }
        }

        $academicYear = AcademicYear::firstOrCreate(
            ['year_label' => '2026-2027'],
            ['is_active' => true]
        );
        $termDates = [
            ['name' => 'Term 1', 'sequence' => 1, 'start_date' => '2026-06-01', 'end_date' => '2026-08-02'],
            ['name' => 'Term 2', 'sequence' => 2, 'start_date' => '2026-08-03', 'end_date' => '2026-10-04'],
            ['name' => 'Term 3', 'sequence' => 3, 'start_date' => '2026-10-05', 'end_date' => '2026-12-06'],
            ['name' => 'Term 4', 'sequence' => 4, 'start_date' => '2026-12-07', 'end_date' => '2027-02-06'],
        ];
        foreach ($termDates as $term) {
            Term::firstOrCreate(
                ['academic_year_id' => $academicYear->id, 'sequence' => $term['sequence']],
                array_merge(['academic_year_id' => $academicYear->id], $term)
            );
        }

        $leaveTypes = [
            ['name' => 'Annual', 'is_paid' => true],
            ['name' => 'Sick', 'is_paid' => true],
            ['name' => 'Unpaid', 'is_paid' => false],
        ];
        foreach ($leaveTypes as $type) {
            LeaveType::firstOrCreate(['name' => $type['name']], $type);
        }

        // Demo staff accounts — one per staff role, matching the Figma login screen's demo-account list.
        $staffDemo = [
            ['role' => 'admin', 'role_type' => 'Admin', 'staff_id_number' => 'USR-0001', 'name' => 'Matthew P. Morgan', 'email' => 'admin@yasis.edu', 'job_title' => 'IT & Media Head', 'department' => 'IT & Media'],
            ['role' => 'principal', 'role_type' => 'Principal', 'staff_id_number' => 'USR-0002', 'name' => 'Sonia Shine', 'email' => 'principal@yasis.edu', 'job_title' => 'Principal', 'department' => 'Administration'],
            ['role' => 'vp_academic', 'role_type' => 'VP_Academic', 'staff_id_number' => 'USR-0003', 'name' => 'Dennis Thein', 'email' => 'vp.academic@yasis.edu', 'job_title' => 'VP Academic', 'department' => 'Administration'],
            ['role' => 'registrar', 'role_type' => 'Registrar', 'staff_id_number' => 'USR-0004', 'name' => 'Mercy Sein', 'email' => 'registrar@yasis.edu', 'job_title' => 'Registrar', 'department' => 'Administration'],
            ['role' => 'teacher', 'role_type' => 'Teacher', 'staff_id_number' => 'USR-0005', 'name' => 'Teacher A', 'email' => 'teacher@yasis.edu', 'job_title' => 'Teacher', 'department' => 'High School'],
            ['role' => 'treasurer', 'role_type' => 'Treasurer', 'staff_id_number' => 'USR-0006', 'name' => 'Zaw Min Latt', 'email' => 'treasurer@yasis.edu', 'job_title' => 'Treasurer', 'department' => 'Finance'],
            ['role' => 'hr_office', 'role_type' => 'HR_Office', 'staff_id_number' => 'USR-0007', 'name' => 'HR Office', 'email' => 'hr@yasis.edu', 'job_title' => 'HR Officer', 'department' => 'Human Resource'],
        ];

        foreach ($staffDemo as $demo) {
            $user = User::firstOrCreate(
                ['email' => $demo['email']],
                ['name' => $demo['name'], 'password' => Hash::make('password'), 'status' => 'Active']
            );
            $user->syncRoles([$demo['role']]);

            StaffProfile::firstOrCreate(
                ['id' => $user->id],
                [
                    'staff_id_number' => $demo['staff_id_number'],
                    'role_type' => $demo['role_type'],
                    'job_title' => $demo['job_title'],
                    'department_id' => Department::where('name', $demo['department'])->first()->id,
                    'status' => 'Active',
                    'joined_date' => '2020-06-01',
                    'phone' => '+95 900-000-000',
                ]
            );
        }

        // Portal-login staff onboarded through the HR Office "Add Staff" flow (role restricted to
        // hrAssignableRoles() — no Admin/Principal), so HR-created logins are testable end-to-end.
        // The Active one is loginable with the demo password; the Pending one mirrors a freshly
        // invited account that has not completed its setup link yet, for testing the invite/activation state.
        $hrOnboardedStaff = [
            ['role' => 'teacher', 'role_type' => 'Teacher', 'staff_id_number' => 'USR-0201', 'name' => 'Naw Eh Ler', 'email' => 'hr.teacher@yasis.edu', 'job_title' => 'Teacher', 'department' => 'High School', 'status' => 'Active'],
            ['role' => 'treasurer', 'role_type' => 'Treasurer', 'staff_id_number' => 'USR-0202', 'name' => 'Ko Ko Naing', 'email' => 'hr.treasurer@yasis.edu', 'job_title' => 'Assistant Treasurer', 'department' => 'Finance', 'status' => 'Pending'],
        ];
        foreach ($hrOnboardedStaff as $demo) {
            $user = User::firstOrCreate(
                ['email' => $demo['email']],
                ['name' => $demo['name'], 'password' => Hash::make('password'), 'status' => $demo['status']]
            );
            $user->syncRoles([$demo['role']]);

            StaffProfile::firstOrCreate(
                ['id' => $user->id],
                [
                    'staff_id_number' => $demo['staff_id_number'],
                    'role_type' => $demo['role_type'],
                    'job_title' => $demo['job_title'],
                    'department_id' => Department::where('name', $demo['department'])->first()->id,
                    'status' => 'Active',
                    'joined_date' => '2026-06-01',
                    'phone' => '+95 900-000-000',
                ]
            );
        }

        // Non-portal auxiliary staff — not ISMS users, but tracked as personnel records by HR.
        $auxiliaryStaff = [
            ['name' => 'Kyaw Zin Oo', 'job_title' => 'Bus Driver', 'department' => 'Transportation', 'staff_id_number' => 'S008'],
            ['name' => 'Ma Ei Ei', 'job_title' => 'Receptionist', 'department' => 'Human Resource', 'staff_id_number' => 'S009'],
            ['name' => 'Daw Nilar', 'job_title' => 'Canteen Head', 'department' => 'Canteen', 'staff_id_number' => 'S010'],
            ['name' => 'U Tin Maung', 'job_title' => 'Maintenance', 'department' => 'Maintenance', 'staff_id_number' => 'S011'],
        ];
        $auxiliaryProfiles = [];
        foreach ($auxiliaryStaff as $aux) {
            $email = Str::slug($aux['name']).'.'.Str::lower($aux['staff_id_number']).'@internal.yasis.edu';
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $aux['name'], 'password' => Hash::make(Str::password(16)), 'status' => 'Inactive']
            );
            $auxiliaryProfiles[$aux['staff_id_number']] = StaffProfile::firstOrCreate(
                ['id' => $user->id],
                [
                    'staff_id_number' => $aux['staff_id_number'],
                    'role_type' => 'Staff',
                    'job_title' => $aux['job_title'],
                    'department_id' => Department::where('name', $aux['department'])->first()->id,
                    'status' => 'Active',
                    'joined_date' => '2021-01-15',
                    'phone' => '+95 900-000-000',
                ]
            );
        }

        // Demo guardian with two children (one has a Student portal login, matching the login screen's demo list).
        $guardianUser = User::firstOrCreate(
            ['email' => 'guardian@yasis.edu'],
            ['name' => 'Daw Hla Myint', 'password' => Hash::make('password'), 'status' => 'Active']
        );
        $guardianUser->syncRoles(['guardian']);
        $guardian = Guardian::firstOrCreate(
            ['user_id' => $guardianUser->id],
            ['relationship' => 'Mother', 'phone' => '+95 900-111-222']
        );

        $studentUser = User::firstOrCreate(
            ['email' => 'student@yasis.edu'],
            ['name' => 'Saw Htoo Aung', 'password' => Hash::make('password'), 'status' => 'Active']
        );
        $studentUser->syncRoles(['student']);
        $childA = Student::firstOrCreate(
            ['student_id_number' => 'YAS-2026-0001'],
            [
                'user_id' => $studentUser->id,
                'name' => 'Saw Htoo Aung',
                'date_of_birth' => '2011-03-14',
                'gender' => 'Male',
                'religious_background' => 'Seventh-day Adventist',
                'admission_date' => '2020-06-01',
                'department_id' => $highSchool->id,
                'enrollment_status' => 'Enrolled',
            ]
        );

        $middleSchool = Department::where('name', 'Middle School')->first();
        $childB = Student::firstOrCreate(
            ['student_id_number' => 'YAS-2026-0002'],
            [
                'user_id' => null,
                'name' => 'Su Su Aung',
                'date_of_birth' => '2013-07-22',
                'gender' => 'Female',
                'religious_background' => 'Other Christian',
                'admission_date' => '2021-06-01',
                'department_id' => $middleSchool->id,
                'enrollment_status' => 'Enrolled',
            ]
        );

        $guardian->students()->syncWithoutDetaching([
            $childA->id => ['is_primary' => true],
            $childB->id => ['is_primary' => true],
        ]);

        // A third student with no guardian linked yet, to populate the "missing guardian" registrar metric.
        $childC = Student::firstOrCreate(
            ['student_id_number' => 'YAS-2026-0003'],
            [
                'user_id' => null,
                'name' => 'Naw Paw Eh',
                'date_of_birth' => '2012-01-10',
                'gender' => 'Female',
                'religious_background' => 'Buddhist',
                'admission_date' => '2026-06-01',
                'department_id' => $highSchool->id,
                'enrollment_status' => 'Enrolled',
            ]
        );

        // Sections for the active year, with the demo Teacher as homeroom for Grade 9-A.
        $teacherProfile = StaffProfile::where('staff_id_number', 'USR-0005')->first();
        $sectionDefs = [
            ['name' => 'Grade 9-A', 'department_id' => $highSchool->id, 'homeroom_teacher_id' => $teacherProfile->id],
            ['name' => 'Grade 8-B', 'department_id' => $middleSchool->id, 'homeroom_teacher_id' => null],
        ];
        foreach ($sectionDefs as $def) {
            Section::firstOrCreate(
                ['academic_year_id' => $academicYear->id, 'name' => $def['name']],
                array_merge(['academic_year_id' => $academicYear->id, 'capacity' => 35], $def)
            );
        }
        $grade9A = Section::where('name', 'Grade 9-A')->first();

        Enrollment::firstOrCreate(
            ['student_id' => $childA->id, 'section_id' => $grade9A->id],
            ['status' => 'Active']
        );
        Enrollment::firstOrCreate(
            ['student_id' => $childC->id, 'section_id' => $grade9A->id],
            ['status' => 'Active']
        );

        // Subjects for the Teaching Assignment demo.
        $subjects = [
            ['code' => 'MATH9', 'name' => 'Mathematics', 'department_id' => $highSchool->id],
            ['code' => 'ENG9', 'name' => 'English', 'department_id' => $highSchool->id],
        ];
        foreach ($subjects as $subject) {
            Subject::firstOrCreate(['code' => $subject['code']], $subject);
        }
        $math9 = Subject::where('code', 'MATH9')->first();

        // Match on the (section_id, subject_id) unique key only; teacher_id is an attribute so
        // a re-seed updates rather than double-inserts.
        TeachingAssignment::firstOrCreate(
            ['section_id' => $grade9A->id, 'subject_id' => $math9->id],
            ['teacher_id' => $teacherProfile->id],
        );

        // Leave balances, current calendar year — the demo Teacher plus a couple of auxiliary staff.
        $annual = LeaveType::where('name', 'Annual')->first();
        $sick = LeaveType::where('name', 'Sick')->first();
        $hrProfile = StaffProfile::where('staff_id_number', 'USR-0007')->first();

        LeaveBalance::firstOrCreate(
            ['staff_id' => $teacherProfile->id, 'leave_type_id' => $annual->id, 'year' => now()->year],
            ['allocated' => 12, 'pending' => 0, 'used' => 2]
        );
        LeaveBalance::firstOrCreate(
            ['staff_id' => $teacherProfile->id, 'leave_type_id' => $sick->id, 'year' => now()->year],
            ['allocated' => 10, 'pending' => 0, 'used' => 4]
        );
        foreach ([$auxiliaryProfiles['S008'], $auxiliaryProfiles['S009'], $auxiliaryProfiles['S010'], $auxiliaryProfiles['S011'], $hrProfile] as $profile) {
            LeaveBalance::firstOrCreate(['staff_id' => $profile->id, 'leave_type_id' => $annual->id, 'year' => now()->year], ['allocated' => 12, 'pending' => 0, 'used' => 1]);
            LeaveBalance::firstOrCreate(['staff_id' => $profile->id, 'leave_type_id' => $sick->id, 'year' => now()->year], ['allocated' => 10, 'pending' => 0, 'used' => 0]);
        }

        // Sample leave requests across all three states, so HR's Leave Management tabs are demoable.
        $unpaid = LeaveType::where('name', 'Unpaid')->first();

        $pendingReq = LeaveRequest::firstOrCreate(
            ['staff_id' => $auxiliaryProfiles['S010']->id, 'leave_type_id' => $sick->id, 'from_date' => today()->addDays(2)->toDateString(), 'to_date' => today()->addDays(5)->toDateString()],
            ['days' => 4, 'reason' => 'Medical — flu, doctor\'s note attached', 'status' => 'Pending', 'submitted_by' => $hrProfile->id]
        );
        LeaveBalance::where('staff_id', $auxiliaryProfiles['S010']->id)->where('leave_type_id', $sick->id)->where('year', now()->year)->increment('pending', $pendingReq->wasRecentlyCreated ? 4 : 0);

        $pendingReq2 = LeaveRequest::firstOrCreate(
            ['staff_id' => $auxiliaryProfiles['S011']->id, 'leave_type_id' => $annual->id, 'from_date' => today()->addDays(7)->toDateString(), 'to_date' => today()->addDays(9)->toDateString()],
            ['days' => 3, 'reason' => 'Family event · entered by HR on behalf (Maintenance staff)', 'status' => 'Pending', 'submitted_by' => $hrProfile->id]
        );
        LeaveBalance::where('staff_id', $auxiliaryProfiles['S011']->id)->where('leave_type_id', $annual->id)->where('year', now()->year)->increment('pending', $pendingReq2->wasRecentlyCreated ? 3 : 0);

        LeaveRequest::firstOrCreate(
            ['staff_id' => $auxiliaryProfiles['S009']->id, 'leave_type_id' => $unpaid->id, 'from_date' => today()->subDays(20)->toDateString(), 'to_date' => today()->subDays(20)->toDateString()],
            ['days' => 1, 'reason' => 'Personal matter', 'status' => 'Approved', 'submitted_by' => $auxiliaryProfiles['S009']->id, 'decided_by' => $hrProfile->id, 'decided_at' => today()->subDays(19)]
        );

        LeaveRequest::firstOrCreate(
            ['staff_id' => $auxiliaryProfiles['S008']->id, 'leave_type_id' => $annual->id, 'from_date' => today()->subDays(30)->toDateString(), 'to_date' => today()->subDays(29)->toDateString()],
            ['days' => 2, 'reason' => 'Requested during peak transport schedule', 'status' => 'Rejected', 'submitted_by' => $hrProfile->id, 'decided_by' => $hrProfile->id, 'decided_at' => today()->subDays(28)]
        );

        // A guardian absence notice covering today, so the teacher's attendance Excused-flag flow is
        // testable end-to-end before the Guardian portal (which will submit these) lands in a later phase.
        AbsenceNotice::firstOrCreate(
            ['student_id' => $childA->id, 'guardian_id' => $guardian->id, 'from_date' => today()->toDateString(), 'to_date' => today()->toDateString()],
            ['reason' => 'Family travel', 'status' => 'Submitted']
        );

        // A published fee-import batch with matched, restricted, and unmatched rows, so the Treasurer
        // workflow (validate/match, imported records, reports) is demoable without an upload first.
        $treasurerProfile = StaffProfile::where('staff_id_number', 'USR-0006')->first();
        $feeBatch = ImportBatch::firstOrCreate(
            ['period' => 'Q1 2026', 'uploaded_by' => $treasurerProfile->id],
            ['source_file' => 'sun_account_Q1_2026.xlsx', 'row_count' => 4, 'uploaded_at' => now()->subDays(10), 'published_at' => now()->subDays(9)]
        );

        $feeRows = [
            ['student_id' => $childA->id, 'raw_student_key' => null, 'amount' => 1200000, 'balance' => 300000, 'status' => 'Partial', 'is_restricted' => false],
            ['student_id' => $childA->id, 'raw_student_key' => null, 'amount' => 200000, 'balance' => 0, 'status' => 'Paid', 'is_restricted' => true],
            ['student_id' => $childB->id, 'raw_student_key' => null, 'amount' => 1000000, 'balance' => 1000000, 'status' => 'Outstanding', 'is_restricted' => false],
            ['student_id' => null, 'raw_student_key' => 'SUN-9911', 'amount' => 500000, 'balance' => 500000, 'status' => 'Owed', 'is_restricted' => false],
        ];
        foreach ($feeRows as $i => $row) {
            ImportedFeeRecord::firstOrCreate(
                ['import_batch_id' => $feeBatch->id, 'student_id' => $row['student_id'], 'raw_student_key' => $row['raw_student_key'], 'txn_date' => now()->subDays(10 - $i)->toDateString()],
                ['amount' => $row['amount'], 'balance' => $row['balance'], 'status' => $row['status'], 'is_restricted' => $row['is_restricted']]
            );
        }

        // A VP-approved promotion batch (childC graduating from Grade 9-A) awaiting Principal co-approval,
        // and a VP-approved transcript, so the Principal's Approvals queue is demoable immediately.
        $registrarProfile = StaffProfile::where('staff_id_number', 'USR-0004')->first();
        $vpProfile = StaffProfile::where('staff_id_number', 'USR-0003')->first();

        $promotionBatch = PromotionBatch::firstOrCreate(
            ['from_section_id' => $grade9A->id, 'prepared_by' => $registrarProfile->id, 'status' => 'VP_Approved'],
            ['vp_approved_by' => $vpProfile->id, 'vp_approved_at' => now()->subDay()]
        );
        $promotionBatch->items()->firstOrCreate(['student_id' => $childC->id], ['action' => 'Graduate']);

        DocumentRequest::firstOrCreate(
            ['student_id' => $childA->id, 'type' => 'Transcript', 'status' => 'Approved'],
            ['prepared_by' => $registrarProfile->id, 'approved_by' => $vpProfile->id, 'approved_at' => now()->subDay()]
        );

        // Term 1 is finalised (locked + results released) so the Guardian/Student report-card download and
        // grade views have real, released data to show.
        $term1 = Term::where('academic_year_id', $academicYear->id)->where('sequence', 1)->first();
        $term1->update(['is_locked' => true, 'results_released' => true]);

        // English teaching assignment + a full weighted gradebook (Math + English) for childA in Term 1.
        $english9 = Subject::where('code', 'ENG9')->first();
        TeachingAssignment::firstOrCreate(['section_id' => $grade9A->id, 'subject_id' => $english9->id], ['teacher_id' => $teacherProfile->id]);

        foreach ([$math9->id => 'Mathematics', $english9->id => 'English'] as $subjectId => $label) {
            $quiz = AssessmentCategory::firstOrCreate(
                ['section_id' => $grade9A->id, 'subject_id' => $subjectId, 'term_id' => $term1->id, 'name' => 'Quiz'],
                ['weight_pct' => 40]
            );
            $test = AssessmentCategory::firstOrCreate(
                ['section_id' => $grade9A->id, 'subject_id' => $subjectId, 'term_id' => $term1->id, 'name' => 'Test'],
                ['weight_pct' => 60]
            );

            $quiz1 = Assessment::firstOrCreate(['category_id' => $quiz->id, 'name' => 'Quiz 1'], ['max_score' => 100]);
            $test1 = Assessment::firstOrCreate(['category_id' => $test->id, 'name' => 'Term Test'], ['max_score' => 100]);

            foreach ([$childA->id => 90, $childC->id => 78] as $studentId => $score) {
                Grade::firstOrCreate(
                    ['assessment_id' => $quiz1->id, 'student_id' => $studentId],
                    ['score' => $score, 'entered_by' => $teacherProfile->id]
                );
                Grade::firstOrCreate(
                    ['assessment_id' => $test1->id, 'student_id' => $studentId],
                    ['score' => $score - 5, 'entered_by' => $teacherProfile->id]
                );
            }
        }

        // A homeroom report-card comment for childA, Term 1.
        \App\Models\ReportCardComment::firstOrCreate(
            ['student_id' => $childA->id, 'term_id' => $term1->id],
            ['staff_id' => $teacherProfile->id, 'comment' => 'Consistent effort this term; encouraged to participate more in class discussion.']
        );

        // A school-wide announcement so Guardian/Student Notices feeds aren't empty.
        Announcement::firstOrCreate(
            ['title' => 'Term 1 report cards released', 'audience_type' => 'School'],
            ['author_id' => StaffProfile::where('staff_id_number', 'USR-0002')->first()->id, 'body' => 'Term 1 report cards are now visible in the guardian and student portals.', 'published_at' => now()->subDays(1)]
        );

        // Absence notice history for childA — one Acknowledged (past, already classified), one Cancelled —
        // alongside the Submitted one above, so "My notices" shows the full status range.
        AbsenceNotice::firstOrCreate(
            ['student_id' => $childA->id, 'guardian_id' => $guardian->id, 'from_date' => today()->subDays(14)->toDateString(), 'to_date' => today()->subDays(13)->toDateString()],
            ['reason' => 'Family event', 'status' => 'Acknowledged', 'acknowledged_by' => $teacherProfile->id, 'acknowledged_at' => today()->subDays(14)]
        );
        AbsenceNotice::firstOrCreate(
            ['student_id' => $childA->id, 'guardian_id' => $guardian->id, 'from_date' => today()->addDays(20)->toDateString(), 'to_date' => today()->addDays(20)->toDateString()],
            ['reason' => 'Travel', 'status' => 'Cancelled']
        );

        // Full K-12 ladder (Nursery → Grade 12), subject catalogue, homeroom
        // teachers, rosters, attendance history, and fee spread.
        $this->call(K12StructureSeeder::class);
    }
}
