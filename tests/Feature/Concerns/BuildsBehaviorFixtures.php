<?php

namespace Tests\Feature\Concerns;

use App\Models\AcademicYear;
use App\Models\BehaviorCategory;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * A small school for behavior tests: an active year with one section, a subject teacher who
 * teaches it, a student (with login) and their guardian, plus another family and every staff role.
 * Needs {@see SeedsCoreData}.
 */
trait BuildsBehaviorFixtures
{
    protected AcademicYear $year;

    protected Section $section;

    protected User $vp;

    protected User $principal;

    protected User $admin;

    protected User $registrar;

    protected User $teacher;

    protected User $otherTeacher;

    protected Student $student;

    protected User $studentUser;

    protected User $guardianUser;

    protected Student $otherStudent;

    protected User $otherGuardianUser;

    protected BehaviorCategory $category;

    protected function buildSchool(): void
    {
        $this->seedRoles();
        $term = $this->seedAcademicCalendar();
        $this->year = AcademicYear::findOrFail($term->academic_year_id);
        $department = $this->seedDepartment();

        $this->vp = $this->makeStaff('vp_academic', 'VP_Academic', 'vp@test.local')->user;
        $this->principal = $this->makeStaff('principal', 'Principal', 'principal@test.local')->user;
        $this->admin = $this->makeStaff('admin', 'Admin', 'admin@test.local')->user;
        $this->registrar = $this->makeStaff('registrar', 'Registrar', 'registrar@test.local')->user;
        $this->teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user;
        $this->otherTeacher = $this->makeStaff('teacher', 'Teacher', 'teacher2@test.local')->user;

        $this->section = Section::create(['academic_year_id' => $this->year->id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);
        $subject = Subject::create(['code' => 'ENG9', 'name' => 'English', 'department_id' => $department->id]);
        TeachingAssignment::create(['section_id' => $this->section->id, 'subject_id' => $subject->id, 'teacher_id' => $this->teacher->id]);

        [$this->student, $this->studentUser, $this->guardianUser] = $this->makeFamily('aye', $department->id);
        [$this->otherStudent, , $this->otherGuardianUser] = $this->makeFamily('other', $department->id);

        $this->category = BehaviorCategory::where('name', 'Classroom disruption')->firstOrFail();
    }

    /** @return array{0: Student, 1: User, 2: User} student, student login, guardian login */
    protected function makeFamily(string $key, int $departmentId): array
    {
        $studentUser = $this->makePortalUser('student', "{$key}.student@test.local");
        $student = Student::create([
            'user_id' => $studentUser->id, 'student_id_number' => 'S-'.strtoupper($key), 'name' => ucfirst($key).' Student',
            'admission_date' => now()->subYear(), 'department_id' => $departmentId, 'enrollment_status' => 'Enrolled',
        ]);
        Enrollment::create(['student_id' => $student->id, 'section_id' => $this->section->id, 'status' => 'Active']);

        $guardianUser = $this->makePortalUser('guardian', "{$key}.guardian@test.local");
        $guardian = Guardian::create(['user_id' => $guardianUser->id, 'relationship' => 'Mother']);
        $guardian->students()->attach($student->id, ['is_primary' => true]);

        return [$student, $studentUser, $guardianUser];
    }

    protected function makePortalUser(string $role, string $email): User
    {
        $user = User::create(['name' => ucfirst(strtok($email, '.@')).' '.ucfirst($role), 'email' => $email, 'password' => Hash::make('password'), 'status' => 'Active']);
        $user->assignRole($role);

        return $user;
    }

    protected function reportFields(array $overrides = []): array
    {
        return array_merge([
            'incident_date' => now()->subDay()->toDateString(),
            'behavior_category_id' => $this->category->id,
            'description' => 'Talked over the teacher repeatedly during the lesson.',
            'is_office_referral' => 0,
        ], $overrides);
    }
}
