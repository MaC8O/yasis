<?php

namespace Tests\Feature\Concerns;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentTrack;
use App\Models\Subject;
use App\Models\SubjectStatus;
use App\Models\Term;

/**
 * Fixtures for result calculation tests: a school year with four terms, sections on a grade level,
 * enrolled students, and one 100-mark exam per subject per term, so a student's subject mark is
 * exactly the score given.
 */
trait BuildsResults
{
    protected Department $resultsDepartment;

    protected AcademicYear $year;

    /** @var list<Term> */
    protected array $terms = [];

    /** The teacher every fixture mark is entered by. */
    protected ?int $markTeacherId = null;

    /** The Grade 12 statement in the rules file, section 6. */
    protected const G12_EXAMPLE = [
        'MORAL' => 77, 'MYAN' => 66, 'ENG' => 87, 'MATH' => 67, 'COMP' => 95, 'MUSIC' => 86, 'PE' => 92,
        'BIO' => 76, 'CHEM' => 72, 'PHYS' => 71,
    ];

    /**
     * The KG-4 statement in the rules file, section 4.2 (a Grade 2 student), with Character Building,
     * which counts since the school's answer to Q4: at 90 the average is still 90.
     */
    protected const KG_EXAMPLE = ['MORAL' => 89, 'MYAN' => 90, 'ENG' => 91, 'MATH' => 82, 'SCI' => 94, 'MAPE' => 94, 'CHAR' => 90];

    /** The KG-4 counted subjects (Q4): the sample's six plus Character Building. */
    protected const KG_CODES = ['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'MAPE', 'CHAR'];

    protected function seedYearWithTerms(int $count = 4): void
    {
        $this->resultsDepartment ??= Department::create(['name' => 'Results School', 'level' => 'Secondary']);
        $this->year = AcademicYear::create(['year_label' => '2026-2027', 'is_active' => true]);
        // The first term is the current one, so the Principal's grade lock applies to it.
        $start = today()->subWeeks(2);
        for ($i = 1; $i <= $count; $i++) {
            $this->terms[] = Term::create([
                'academic_year_id' => $this->year->id,
                'name' => ['First', 'Second', 'Third', 'Final'][$i - 1] ?? "Term {$i}",
                'sequence' => $i,
                'start_date' => $start->copy()->addWeeks(9 * ($i - 1)),
                'end_date' => $start->copy()->addWeeks(9 * $i)->subDay(),
            ]);
        }
    }

    protected function levelSection(string $name, string $levelCode): Section
    {
        return Section::create([
            'academic_year_id' => $this->year->id,
            'department_id' => $this->resultsDepartment->id,
            'grade_level_id' => GradeLevel::where('code', $levelCode)->value('id'),
            'name' => $name,
            'capacity' => 30,
        ]);
    }

    protected function pupil(string $name, Section $section, string $status = 'Active'): Student
    {
        $student = Student::create([
            'student_id_number' => 'R-'.fake()->unique()->numberBetween(10000, 99999),
            'name' => $name,
            'admission_date' => now()->subYear(),
            'department_id' => $this->resultsDepartment->id,
            'enrollment_status' => 'Enrolled',
        ]);
        Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'status' => $status]);

        return $student;
    }

    /** Give a student a subject mark for a term (one 100-mark exam). Null removes the mark. */
    protected function setMark(Student $student, Section $section, string $subjectCode, Term $term, int|float|string|null $score): void
    {
        $subjectId = Subject::where('code', $subjectCode)->whereNull('department_id')->value('id');
        $category = AssessmentCategory::firstOrCreate(
            ['section_id' => $section->id, 'subject_id' => $subjectId, 'term_id' => $term->id, 'name' => 'Exam'],
            ['weight_pct' => 100],
        );
        $assessment = Assessment::firstOrCreate(['category_id' => $category->id, 'name' => 'Exam'], ['type' => 'test', 'max_score' => 100]);

        if ($score === null) {
            Grade::where('assessment_id', $assessment->id)->where('student_id', $student->id)->delete();

            return;
        }
        $this->markTeacherId ??= $this->makeStaff('teacher', 'Teacher', 'marks-teacher@test.local')->id;
        Grade::updateOrCreate(['assessment_id' => $assessment->id, 'student_id' => $student->id], ['score' => (string) $score, 'entered_by' => $this->markTeacherId]);
    }

    /** @param  array<string, int|float|string|null>  $marks  subject code => score */
    protected function setMarks(Student $student, Section $section, Term $term, array $marks): void
    {
        foreach ($marks as $code => $score) {
            $this->setMark($student, $section, $code, $term, $score);
        }
    }

    /** Set a Grade 10-12 student's track (science or arts) for the fixture year. */
    protected function setTrack(Student $student, string $track): void
    {
        StudentTrack::updateOrCreate(['student_id' => $student->id, 'academic_year_id' => $this->year->id], ['track' => $track]);
    }

    /** SUPW's word for a term: Pass, Incomplete or Complete. */
    protected function setWord(Student $student, Section $section, Term $term, string $word, string $subjectCode = 'SUPW'): void
    {
        SubjectStatus::updateOrCreate(
            ['student_id' => $student->id, 'subject_id' => Subject::where('code', $subjectCode)->whereNull('department_id')->value('id'), 'term_id' => $term->id],
            ['section_id' => $section->id, 'status' => $word],
        );
    }

    /** A Grade 12 student with the section 6 sample marks, the science track and SUPW Pass. */
    protected function grade12Example(Section $section, Term $term, string $name = 'Sample Twelve', array $overrides = []): Student
    {
        $student = $this->pupil($name, $section);
        $this->setTrack($student, 'science');
        $this->setMarks($student, $section, $term, array_merge(self::G12_EXAMPLE, $overrides));
        $this->setWord($student, $section, $term, 'Pass');

        return $student;
    }
}
