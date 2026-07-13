<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Bulk score import for a single assessment: rows are matched to the roster by student_id_number,
 * valid scores are saved, and blank / non-numeric / out-of-range / non-enrolled rows are reported
 * and never partially saved. The term lock and teach-only scoping apply as everywhere else.
 */
class GradebookScoreImportTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    /**
     * Seeds a teacher with an assigned class, a Quiz category, a max-100 assessment, and four
     * enrolled students (G-0001..G-0004) ready to be scored.
     */
    private function makeGradebook(): array
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);
        $subject = Subject::create(['code' => 'MATH9', 'name' => 'Mathematics', 'department_id' => $department->id]);
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $students = collect(['G-0001', 'G-0002', 'G-0003', 'G-0004'])->map(function ($idNumber) use ($department, $section) {
            $student = Student::create(['student_id_number' => $idNumber, 'name' => "Student {$idNumber}", 'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);
            Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'status' => 'Active']);

            return $student;
        })->keyBy('student_id_number');

        $category = AssessmentCategory::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'term_id' => $term->id, 'name' => 'Quiz', 'weight_pct' => 100]);
        $assessment = Assessment::create(['category_id' => $category->id, 'name' => 'Quiz 1', 'max_score' => 100]);

        return compact('term', 'teacher', 'section', 'subject', 'students', 'category', 'assessment');
    }

    public function test_bulk_import_saves_valid_scores_and_reports_bad_rows(): void
    {
        ['teacher' => $teacher, 'students' => $students, 'assessment' => $assessment] = $this->makeGradebook();

        // Valid, blank (skip), non-numeric (error), out-of-range (error), and a not-enrolled id (error).
        $csv = "student_id_number,name,score\n"
            ."G-0001,Student G-0001,88\n"
            ."G-0002,Student G-0002,\n"
            ."G-0003,Student G-0003,abc\n"
            ."G-0004,Student G-0004,150\n"
            ."X-9999,Ghost Student,50\n";

        $file = UploadedFile::fake()->createWithContent('scores.csv', $csv);

        $response = $this->actingAs($teacher->user)
            ->post("/teacher/gradebook/assessments/{$assessment->id}/scores-import", ['file' => $file]);

        $response->assertSessionHasNoErrors();

        // Only the one valid row is persisted.
        $this->assertDatabaseHas('grades', ['assessment_id' => $assessment->id, 'student_id' => $students['G-0001']->id, 'score' => 88]);
        $this->assertDatabaseMissing('grades', ['assessment_id' => $assessment->id, 'student_id' => $students['G-0002']->id]);
        $this->assertDatabaseMissing('grades', ['assessment_id' => $assessment->id, 'student_id' => $students['G-0003']->id]);
        $this->assertDatabaseMissing('grades', ['assessment_id' => $assessment->id, 'student_id' => $students['G-0004']->id]);
        $this->assertSame(1, \App\Models\Grade::where('assessment_id', $assessment->id)->count());

        $results = session('scoreImportResults');
        $this->assertCount(1, $results['updated']);
        $this->assertCount(1, $results['skipped']);
        $this->assertCount(3, $results['errors']);
    }

    public function test_import_re_upload_updates_an_existing_score(): void
    {
        ['teacher' => $teacher, 'students' => $students, 'assessment' => $assessment] = $this->makeGradebook();

        $first = UploadedFile::fake()->createWithContent('scores.csv', "student_id_number,name,score\nG-0001,Student,40\n");
        $this->actingAs($teacher->user)->post("/teacher/gradebook/assessments/{$assessment->id}/scores-import", ['file' => $first]);

        $second = UploadedFile::fake()->createWithContent('scores.csv', "student_id_number,name,score\nG-0001,Student,72\n");
        $this->actingAs($teacher->user)->post("/teacher/gradebook/assessments/{$assessment->id}/scores-import", ['file' => $second]);

        $this->assertSame(1, \App\Models\Grade::where('assessment_id', $assessment->id)->where('student_id', $students['G-0001']->id)->count());
        $this->assertDatabaseHas('grades', ['assessment_id' => $assessment->id, 'student_id' => $students['G-0001']->id, 'score' => 72]);
    }

    public function test_locked_term_blocks_score_import(): void
    {
        ['term' => $term, 'teacher' => $teacher, 'students' => $students, 'assessment' => $assessment] = $this->makeGradebook();

        $term->update(['is_locked' => true]);

        $csv = "student_id_number,name,score\nG-0001,Student G-0001,88\n";
        $file = UploadedFile::fake()->createWithContent('scores.csv', $csv);

        $this->actingAs($teacher->user)
            ->post("/teacher/gradebook/assessments/{$assessment->id}/scores-import", ['file' => $file])
            ->assertSessionHasErrors('term');

        $this->assertDatabaseMissing('grades', ['assessment_id' => $assessment->id, 'student_id' => $students['G-0001']->id]);
    }

    public function test_teacher_cannot_import_into_a_class_they_do_not_teach(): void
    {
        ['assessment' => $assessment] = $this->makeGradebook();

        $intruder = $this->makeStaff('teacher', 'Teacher', 'intruder@test.local');

        $csv = "student_id_number,name,score\nG-0001,Student G-0001,88\n";
        $file = UploadedFile::fake()->createWithContent('scores.csv', $csv);

        $this->actingAs($intruder->user)
            ->post("/teacher/gradebook/assessments/{$assessment->id}/scores-import", ['file' => $file])
            ->assertForbidden();

        $this->assertDatabaseCount('grades', 0);
    }

    public function test_template_download_lists_the_roster(): void
    {
        ['teacher' => $teacher, 'assessment' => $assessment] = $this->makeGradebook();

        $response = $this->actingAs($teacher->user)
            ->get("/teacher/gradebook/assessments/{$assessment->id}/scores-template");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $response->assertSee('student_id_number,name,score', false);
        $response->assertSee('G-0001', false);
        $response->assertSee('G-0004', false);
    }
}
