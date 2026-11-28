<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\StudentElective;
use App\Models\SubjectOffering;
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
 * ladder — Nursery/KG through Grade 12 — homeroom teachers, teaching
 * assignments for each class's grade-level curriculum (the school's subjects,
 * seeded by migration), Grade 11-12 elective choices, student rosters with
 * guardians, attendance history, and fee records, so every portal opens with
 * real data.
 */
class K12StructureSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::where('is_active', true)->firstOrFail();
        $departments = Department::pluck('id', 'name');

        // Grades come from the grading schemes per grade level, seeded by migration; the old
        // department grade scale is retired (its table is kept for past records).

        // ------------------------------------------------------------------
        // Academic calendar: a few representative events for the active year.
        // ------------------------------------------------------------------
        foreach ([
            ['title' => 'First Day of School', 'event_type' => 'Activity', 'start_date' => $year->terms()->orderBy('sequence')->value('start_date')],
            ['title' => 'Term 1 Examinations', 'event_type' => 'Exam', 'start_date' => now()->addWeeks(2)->toDateString(), 'end_date' => now()->addWeeks(2)->addDays(4)->toDateString()],
            ['title' => 'Parent–Teacher Conference', 'event_type' => 'Meeting', 'start_date' => now()->addWeeks(3)->toDateString()],
            ['title' => 'Founders\' Day (YASIS Event)', 'event_type' => 'Activity', 'start_date' => now()->addWeeks(4)->toDateString()],
            ['title' => 'Term Break', 'event_type' => 'Term Break', 'start_date' => now()->addWeeks(9)->toDateString(), 'end_date' => now()->addWeeks(10)->toDateString()],
            ['title' => 'Christmas Holiday', 'event_type' => 'Holiday', 'start_date' => now()->startOfYear()->addMonths(11)->addDays(24)->toDateString()],
        ] as $event) {
            \App\Models\CalendarEvent::firstOrCreate(
                ['title' => $event['title'], 'academic_year_id' => $year->id],
                array_merge($event, ['academic_year_id' => $year->id])
            );
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
        // Classes: the full K-12 ladder, one section per grade level. Nursery has no
        // grade level: the school's levels start at KG1 (rules file, Q2).
        // ------------------------------------------------------------------
        $gradeLadder = [
            'Pre-School' => ['Nursery' => null, 'Kindergarten 1' => 'KG1', 'Kindergarten 2' => 'KG2'],
            'Elementary' => ['Grade 1-A' => '1', 'Grade 2-A' => '2', 'Grade 3-A' => '3', 'Grade 4-A' => '4', 'Grade 5-A' => '5'],
            'Middle School' => ['Grade 6-A' => '6', 'Grade 7-A' => '7', 'Grade 8-A' => '8'],
            'High School' => ['Grade 9-A' => '9', 'Grade 10-A' => '10', 'Grade 11-A' => '11', 'Grade 12-A' => '12'],
        ];
        $levelIds = GradeLevel::pluck('id', 'code');

        $sections = [];
        $teacherIdx = 0;
        foreach ($gradeLadder as $deptName => $grades) {
            foreach ($grades as $grade => $levelCode) {
                $section = Section::firstOrCreate(
                    ['academic_year_id' => $year->id, 'name' => $grade],
                    ['department_id' => $departments[$deptName], 'capacity' => 30]
                );
                if ($levelCode !== null && ! $section->grade_level_id) {
                    $section->update(['grade_level_id' => $levelIds[$levelCode]]);
                }
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
        // Teaching assignments — each class gets its grade level's curriculum
        // (the school's subjects, seeded by the 2026_11_26_090300 migration).
        // Nursery has no grade level, so no curriculum and no assignments.
        // ------------------------------------------------------------------
        $assignIdx = 0;
        foreach ($sections as $section) {
            if (! $section->grade_level_id) {
                continue;
            }
            $curriculum = SubjectOffering::active()->where('grade_level_id', $section->grade_level_id)->ordered()->pluck('subject_id');
            foreach ($curriculum as $subjectId) {
                TeachingAssignment::firstOrCreate(
                    ['section_id' => $section->id, 'subject_id' => $subjectId],
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
            foreach (array_keys($grades) as $grade) {
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
        // Grades 11-12 elective choices: the first four students of each class
        // with slots choose one subject per slot (science or arts side, mixed for
        // one student); the fifth is left for the Registrar to set.
        // ------------------------------------------------------------------
        foreach ($sections as $section) {
            if (! $section->grade_level_id) {
                continue;
            }
            $slots = SubjectOffering::active()->where('grade_level_id', $section->grade_level_id)
                ->whereNotNull('elective_slot')->ordered()->get()->groupBy('elective_slot');
            if ($slots->isEmpty()) {
                continue;
            }
            $studentIds = Enrollment::where('section_id', $section->id)->where('status', 'Active')->orderBy('student_id')->pluck('student_id');
            foreach ($studentIds->take(4)->values() as $i => $studentId) {
                foreach ($slots as $slot => $options) {
                    $pick = $i === 3 ? ($slot === 'A' ? 0 : 1) : $i % 2; // student 4 mixes sides (Q7 default allows it)
                    StudentElective::firstOrCreate(
                        ['student_id' => $studentId, 'academic_year_id' => $section->academic_year_id, 'elective_slot' => $slot],
                        ['subject_offering_id' => $options[min($pick, $options->count() - 1)]->id]
                    );
                }
            }
        }
    }
}
