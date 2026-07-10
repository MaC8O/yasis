<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\GradeScaleBand;
use App\Models\Guardian;
use App\Models\ImportBatch;
use App\Models\ImportedFeeRecord;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
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

/**
 * Full K-12 structure for Yangon Adventist Seminary (YASIS): the school is a
 * Seventh-day Adventist institution (est. 1975, Bahan Township, Yangon)
 * offering kindergarten through grade 12, accredited by the Adventist
 * Accrediting Association (AAA). This seeder builds the complete class
 * ladder — Nursery/KG through Grade 12 — a realistic subject catalogue per
 * department (Bible-based curriculum, English medium, Myanmar language),
 * homeroom teachers, teaching assignments, student rosters with guardians,
 * attendance history, and fee records, so every portal opens with real data.
 */
class K12StructureSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::where('is_active', true)->firstOrFail();
        $departments = Department::pluck('id', 'name');

        // ------------------------------------------------------------------
        // Grade scale: descriptive (no GPA) for the lower school, per §8.4.
        // ------------------------------------------------------------------
        foreach (['Pre-School', 'Elementary'] as $deptName) {
            $deptId = $departments[$deptName];
            GradeScaleBand::where('department_id', $deptId)->delete();
            foreach ([
                ['letter' => 'E', 'min_score' => 90],   // Excellent
                ['letter' => 'G', 'min_score' => 80],   // Good
                ['letter' => 'S', 'min_score' => 70],   // Satisfactory
                ['letter' => 'P', 'min_score' => 60],   // Progressing
                ['letter' => 'N', 'min_score' => 0],    // Needs support
            ] as $band) {
                GradeScaleBand::create(array_merge($band, ['department_id' => $deptId, 'gpa_point' => null]));
            }
        }

        // ------------------------------------------------------------------
        // Homeroom teachers — one per class, K-12.
        // ------------------------------------------------------------------
        $teacherNames = [
            'Naw Eh Say Paw', 'Saw Kler Htoo', 'Daw Khin Mar Lar', 'U Aung Kyaw Moe',
            'Naw Mu Mu Phaw', 'Saw David Htoo', 'Ma Phyu Phyu Win', 'U Tun Tun Oo',
            'Naw Paw Wah', 'Daw Aye Mya Thanda', 'Saw Eh Doh Soe', 'Ma Thiri Aung',
            'U Myo Min Thant', 'Naw Hser Gay Moo',
        ];

        $teachers = [];
        foreach ($teacherNames as $i => $name) {
            $user = User::firstOrCreate(
                ['email' => Str::slug($name, '.').'@yasis.edu'],
                ['name' => $name, 'password' => Hash::make('password'), 'status' => 'Active']
            );
            $user->assignRole('teacher');

            $teachers[] = StaffProfile::firstOrCreate(
                ['id' => $user->id],
                [
                    'staff_id_number' => 'USR-01'.str_pad($i + 1, 2, '0', STR_PAD_LEFT),
                    'role_type' => 'Teacher',
                    'job_title' => 'Teacher',
                    'department_id' => null,
                    'status' => 'Active',
                    'joined_date' => now()->subYears(2 + ($i % 6))->toDateString(),
                    'phone' => '+95 9'.str_pad(770000100 + $i, 9, '0', STR_PAD_LEFT),
                ]
            );
        }

        $annual = LeaveType::where('name', 'Annual')->first();
        $sick = LeaveType::where('name', 'Sick')->first();
        foreach ($teachers as $teacher) {
            LeaveBalance::firstOrCreate(['staff_id' => $teacher->id, 'leave_type_id' => $annual->id, 'year' => now()->year], ['allocated' => 12, 'pending' => 0, 'used' => 0]);
            LeaveBalance::firstOrCreate(['staff_id' => $teacher->id, 'leave_type_id' => $sick->id, 'year' => now()->year], ['allocated' => 10, 'pending' => 0, 'used' => 0]);
        }

        // ------------------------------------------------------------------
        // Classes: the full K-12 ladder, one section per grade level.
        // ------------------------------------------------------------------
        $gradeLadder = [
            'Pre-School' => ['Nursery', 'Kindergarten 1', 'Kindergarten 2'],
            'Elementary' => ['Grade 1-A', 'Grade 2-A', 'Grade 3-A', 'Grade 4-A', 'Grade 5-A'],
            'Middle School' => ['Grade 6-A', 'Grade 7-A', 'Grade 8-A'],
            'High School' => ['Grade 9-A', 'Grade 10-A', 'Grade 11-A', 'Grade 12-A'],
        ];

        $sections = [];
        $teacherIdx = 0;
        foreach ($gradeLadder as $deptName => $grades) {
            foreach ($grades as $grade) {
                $section = Section::firstOrCreate(
                    ['academic_year_id' => $year->id, 'name' => $grade],
                    ['department_id' => $departments[$deptName], 'capacity' => 30]
                );
                if (! $section->homeroom_teacher_id) {
                    $section->update(['homeroom_teacher_id' => $teachers[$teacherIdx % count($teachers)]->id]);
                }
                // Homeroom teachers belong to the department they teach in.
                StaffProfile::where('id', $section->homeroom_teacher_id)
                    ->whereNull('department_id')
                    ->update(['department_id' => $departments[$deptName]]);
                $sections[$grade] = $section;
                $teacherIdx++;
            }
        }

        // ------------------------------------------------------------------
        // Subject catalogue — Bible-based Adventist curriculum, English medium,
        // Myanmar language at every level.
        // ------------------------------------------------------------------
        $catalogue = [
            'Pre-School' => [
                ['PS-BIB', 'Bible Stories & Values'],
                ['PS-ENG', 'English Phonics & Reading Readiness'],
                ['PS-NUM', 'Numeracy'],
                ['PS-MYA', 'Myanmar Language'],
                ['PS-ART', 'Arts & Crafts'],
                ['PS-MUS', 'Music & Movement'],
                ['PS-PE', 'Play & Motor Skills'],
            ],
            'Elementary' => [
                ['EL-BIB', 'Bible'],
                ['EL-ENG', 'English Language Arts'],
                ['EL-MAT', 'Mathematics'],
                ['EL-SCI', 'Science'],
                ['EL-SOC', 'Social Studies'],
                ['EL-MYA', 'Myanmar Language'],
                ['EL-ICT', 'Computer Basics'],
                ['EL-PE', 'Physical Education'],
                ['EL-ART', 'Art'],
                ['EL-MUS', 'Music'],
            ],
            'Middle School' => [
                ['MS-BIB', 'Bible'],
                ['MS-ENG', 'English Language Arts'],
                ['MS-MAT', 'Mathematics (Pre-Algebra)'],
                ['MS-SCI', 'General Science'],
                ['MS-SOC', 'Social Studies'],
                ['MS-GEO', 'Geography'],
                ['MS-MYA', 'Myanmar Language'],
                ['MS-ICT', 'ICT'],
                ['MS-PE', 'Physical Education & Health'],
                ['MS-ART', 'Art & Music'],
            ],
            'High School' => [
                ['HS-BIB', 'Bible & Religious Studies'],
                ['ENG9', 'English 9'],
                ['ENG10', 'English 10'],
                ['ENG11', 'English 11 (American Literature)'],
                ['ENG12', 'English 12 (World Literature)'],
                ['MATH9', 'Mathematics 9 (Algebra I)'],
                ['MATH10', 'Mathematics 10 (Geometry)'],
                ['MATH11', 'Mathematics 11 (Algebra II)'],
                ['MATH12', 'Mathematics 12 (Pre-Calculus)'],
                ['HS-BIO', 'Biology'],
                ['HS-CHEM', 'Chemistry'],
                ['HS-PHY', 'Physics'],
                ['HS-HIS', 'World History'],
                ['HS-ECON', 'Economics'],
                ['HS-MYA', 'Myanmar Language & Literature'],
                ['HS-ICT', 'Computer Science'],
                ['HS-PE', 'Physical Education & Health'],
                ['HS-MUS', 'Choir & Music'],
            ],
        ];

        $subjects = [];
        foreach ($catalogue as $deptName => $items) {
            foreach ($items as [$code, $name]) {
                $subject = Subject::firstOrCreate(['code' => $code], ['name' => $name, 'department_id' => $departments[$deptName]]);
                // Upgrade the placeholder names left by earlier seeds (e.g. MATH9 "Mathematics").
                if ($subject->name !== $name) {
                    $subject->update(['name' => $name]);
                }
                $subjects[$code] = $subject;
            }
        }

        // ------------------------------------------------------------------
        // Teaching assignments — each class gets its level's subject set;
        // grade-specific English/Math courses in High School.
        // ------------------------------------------------------------------
        $plan = [
            'Nursery' => ['PS-BIB', 'PS-ENG', 'PS-NUM', 'PS-MYA', 'PS-ART', 'PS-MUS', 'PS-PE'],
            'Kindergarten 1' => ['PS-BIB', 'PS-ENG', 'PS-NUM', 'PS-MYA', 'PS-ART', 'PS-MUS', 'PS-PE'],
            'Kindergarten 2' => ['PS-BIB', 'PS-ENG', 'PS-NUM', 'PS-MYA', 'PS-ART', 'PS-MUS', 'PS-PE'],
            'Grade 1-A' => ['EL-BIB', 'EL-ENG', 'EL-MAT', 'EL-SCI', 'EL-SOC', 'EL-MYA', 'EL-PE', 'EL-ART'],
            'Grade 2-A' => ['EL-BIB', 'EL-ENG', 'EL-MAT', 'EL-SCI', 'EL-SOC', 'EL-MYA', 'EL-PE', 'EL-MUS'],
            'Grade 3-A' => ['EL-BIB', 'EL-ENG', 'EL-MAT', 'EL-SCI', 'EL-SOC', 'EL-MYA', 'EL-ICT', 'EL-PE'],
            'Grade 4-A' => ['EL-BIB', 'EL-ENG', 'EL-MAT', 'EL-SCI', 'EL-SOC', 'EL-MYA', 'EL-ICT', 'EL-PE'],
            'Grade 5-A' => ['EL-BIB', 'EL-ENG', 'EL-MAT', 'EL-SCI', 'EL-SOC', 'EL-MYA', 'EL-ICT', 'EL-PE'],
            'Grade 6-A' => ['MS-BIB', 'MS-ENG', 'MS-MAT', 'MS-SCI', 'MS-SOC', 'MS-MYA', 'MS-ICT', 'MS-PE', 'MS-ART'],
            'Grade 7-A' => ['MS-BIB', 'MS-ENG', 'MS-MAT', 'MS-SCI', 'MS-GEO', 'MS-MYA', 'MS-ICT', 'MS-PE', 'MS-ART'],
            'Grade 8-A' => ['MS-BIB', 'MS-ENG', 'MS-MAT', 'MS-SCI', 'MS-SOC', 'MS-MYA', 'MS-ICT', 'MS-PE', 'MS-ART'],
            'Grade 9-A' => ['HS-BIB', 'ENG9', 'MATH9', 'HS-BIO', 'HS-HIS', 'HS-MYA', 'HS-ICT', 'HS-PE'],
            'Grade 10-A' => ['HS-BIB', 'ENG10', 'MATH10', 'HS-BIO', 'HS-HIS', 'HS-MYA', 'HS-ICT', 'HS-PE'],
            'Grade 11-A' => ['HS-BIB', 'ENG11', 'MATH11', 'HS-CHEM', 'HS-ECON', 'HS-MYA', 'HS-ICT', 'HS-PE'],
            'Grade 12-A' => ['HS-BIB', 'ENG12', 'MATH12', 'HS-PHY', 'HS-ECON', 'HS-MYA', 'HS-MUS', 'HS-PE'],
        ];

        $assignIdx = 0;
        foreach ($plan as $grade => $codes) {
            $section = $sections[$grade];
            foreach ($codes as $code) {
                TeachingAssignment::firstOrCreate(
                    ['section_id' => $section->id, 'subject_id' => $subjects[$code]->id],
                    ['teacher_id' => $teachers[$assignIdx++ % count($teachers)]->id]
                );
            }
        }

        // ------------------------------------------------------------------
        // Student rosters: five per class with guardians (two children share
        // one family guardian), ages matched to grade level.
        // ------------------------------------------------------------------
        $firstNames = [
            'Saw Eh Kaw', 'Naw Paw Ku', 'Aung Khant', 'Su Myat', 'Kaung Htet',
            'Ei Thandar', 'Saw Doh Say', 'Naw Wah Paw', 'Min Khant', 'Hnin Yati',
            'Thura', 'May Thet', 'Saw Hsa Moo', 'Naw Say Blut', 'Zwe Pyae',
        ];
        $lastNames = ['Aung', 'Htoo', 'Lin', 'Win', 'Moe', 'Paw', 'Oo', 'Thant', 'Say', 'Myint'];
        $religions = ['Seventh-day Adventist', 'Baptist', 'Buddhist', 'Other Christian', 'Seventh-day Adventist', 'Hindu', 'Buddhist', 'Seventh-day Adventist', 'Muslim', 'Baptist'];

        // Approximate age at admission per class (Nursery 3 … Grade 12 → 17).
        $gradeAges = [
            'Nursery' => 3, 'Kindergarten 1' => 4, 'Kindergarten 2' => 5,
            'Grade 1-A' => 6, 'Grade 2-A' => 7, 'Grade 3-A' => 8, 'Grade 4-A' => 9, 'Grade 5-A' => 10,
            'Grade 6-A' => 11, 'Grade 7-A' => 12, 'Grade 8-A' => 13,
            'Grade 9-A' => 14, 'Grade 10-A' => 15, 'Grade 11-A' => 16, 'Grade 12-A' => 17,
        ];

        $seq = 100;
        $counter = 0;
        $pendingGuardian = null;

        foreach ($gradeLadder as $deptName => $grades) {
            foreach ($grades as $grade) {
                $section = $sections[$grade];
                for ($i = 0; $i < 5; $i++) {
                    $idNumber = 'YAS-2026-'.str_pad($seq, 4, '0', STR_PAD_LEFT);
                    $seq++;

                    $first = $firstNames[$counter % count($firstNames)];
                    $last = $lastNames[($counter + $i) % count($lastNames)];

                    $student = Student::firstOrCreate(
                        ['student_id_number' => $idNumber],
                        [
                            'name' => $first.' '.$last,
                            'date_of_birth' => now()->subYears($gradeAges[$grade])->subMonths(($counter % 10) + 1)->toDateString(),
                            'gender' => $counter % 2 === 0 ? 'Male' : 'Female',
                            'religious_background' => $religions[$counter % count($religions)],
                            'admission_date' => now()->subYears(min($gradeAges[$grade] - 3, 3))->startOfYear()->addMonths(5)->toDateString(),
                            'department_id' => $departments[$deptName],
                            'enrollment_status' => 'Enrolled',
                        ]
                    );

                    Enrollment::firstOrCreate(
                        ['student_id' => $student->id, 'section_id' => $section->id],
                        ['status' => 'Active']
                    );

                    // Every two students share a family guardian (non-portal: Pending login).
                    if ($pendingGuardian === null) {
                        $guardianName = 'Daw '.Str::of($first)->explode(' ')->first().' '.$last;
                        $guardianUser = User::firstOrCreate(
                            ['email' => 'family.'.Str::lower($last).'.'.$seq.'@family.yasis.edu'],
                            ['name' => $guardianName, 'password' => Hash::make(Str::password(16)), 'status' => 'Pending']
                        );
                        $guardianUser->assignRole('guardian');
                        $pendingGuardian = Guardian::firstOrCreate(
                            ['user_id' => $guardianUser->id],
                            ['relationship' => $counter % 3 === 0 ? 'Father' : 'Mother', 'phone' => '+95 9'.str_pad(660000100 + $counter, 9, '0', STR_PAD_LEFT)]
                        );
                        $pendingGuardian->students()->syncWithoutDetaching([$student->id => ['is_primary' => true]]);
                    } else {
                        $pendingGuardian->students()->syncWithoutDetaching([$student->id => ['is_primary' => true]]);
                        $pendingGuardian = null;
                    }

                    $counter++;
                }
            }
        }

        // ------------------------------------------------------------------
        // Attendance history: the last 8 weekdays for every class, mostly
        // Present with a deterministic sprinkle of Absent/Tardy/Excused.
        // ------------------------------------------------------------------
        $currentTerm = Term::where('academic_year_id', $year->id)
            ->where('start_date', '<=', today())->where('end_date', '>=', today())->first()
            ?? Term::where('academic_year_id', $year->id)->orderBy('sequence')->first();

        $days = [];
        for ($d = 1; count($days) < 8; $d++) {
            $day = today()->subDays($d);
            if (! $day->isWeekend()) {
                $days[] = $day->toDateString();
            }
        }

        foreach ($sections as $section) {
            $recorder = $section->homeroom_teacher_id ?? $teachers[0]->id;
            $studentIds = Enrollment::where('section_id', $section->id)->where('status', 'Active')->pluck('student_id');

            foreach ($days as $dayIdx => $date) {
                foreach ($studentIds as $sIdx => $studentId) {
                    $roll = ($studentId + $dayIdx) % 17;
                    $status = match (true) {
                        $roll === 3 => 'Absent',
                        $roll === 7 => 'Tardy',
                        $roll === 11 => 'Excused',
                        default => 'Present',
                    };

                    AttendanceRecord::firstOrCreate(
                        ['student_id' => $studentId, 'section_id' => $section->id, 'attendance_date' => $date],
                        ['term_id' => $currentTerm->id, 'status' => $status, 'recorded_by' => $recorder]
                    );
                }
            }
        }

        // ------------------------------------------------------------------
        // Fee records: a published Q2 batch covering the high-school roster,
        // so treasurer/leadership fee reports and charts have real spread.
        // ------------------------------------------------------------------
        $treasurer = StaffProfile::where('staff_id_number', 'USR-0006')->first();
        if ($treasurer) {
            $batch = ImportBatch::firstOrCreate(
                ['period' => 'Q2 2026', 'uploaded_by' => $treasurer->id],
                ['source_file' => 'sun_account_Q2_2026.xlsx', 'row_count' => 0, 'uploaded_at' => now()->subDays(3), 'published_at' => now()->subDays(2)]
            );

            $hsStudents = Student::where('department_id', $departments['High School'])
                ->where('enrollment_status', 'Enrolled')->orderBy('id')->get();

            foreach ($hsStudents as $i => $student) {
                [$amount, $balance, $status] = match ($i % 4) {
                    0 => [1500000, 0, 'Paid'],
                    1 => [1500000, 500000, 'Partial'],
                    2 => [1500000, 1500000, 'Outstanding'],
                    default => [1500000, 250000, 'Partial'],
                };

                ImportedFeeRecord::firstOrCreate(
                    ['import_batch_id' => $batch->id, 'student_id' => $student->id, 'txn_date' => now()->subDays(5)->toDateString()],
                    ['raw_student_key' => null, 'amount' => $amount, 'balance' => $balance, 'status' => $status, 'is_restricted' => false]
                );
            }

            $batch->update(['row_count' => $batch->importedFeeRecords()->count()]);
        }
    }
}
