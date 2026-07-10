<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeChangeRequest;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * §3.6 governance: grade changes after a term is locked require two-key co-approval —
 * the teacher requests, the VP Academic reviews (first key), the Principal co-approves
 * and the system applies the change (second key). Same shape as promotion approval.
 */
class GradeChangeTwoKeyTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private function makeLockedGradebook(): array
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();

        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $vp = $this->makeStaff('vp_academic', 'VP_Academic', 'vp@test.local');
        $principal = $this->makeStaff('principal', 'Principal', 'principal@test.local');

        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);
        $subject = Subject::create(['code' => 'MATH9', 'name' => 'Mathematics', 'department_id' => $department->id]);
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $student = Student::create(['student_id_number' => 'GC-0001', 'first_name' => 'Fix', 'last_name' => 'MyGrade', 'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);
        Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'status' => 'Active']);

        $category = AssessmentCategory::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'term_id' => $term->id, 'name' => 'Test', 'weight_pct' => 100]);
        $assessment = Assessment::create(['category_id' => $category->id, 'name' => 'Term Test', 'max_score' => 100]);
        $grade = Grade::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 58, 'entered_by' => $teacher->id]);

        $term->update(['is_locked' => true]);

        return compact('term', 'teacher', 'vp', 'principal', 'section', 'subject', 'student', 'assessment', 'grade');
    }

    private function submitRequest(array $fixture, float $newScore = 85): GradeChangeRequest
    {
        $this->actingAs($fixture['teacher']->user)->post('/teacher/gradebook/change-requests', [
            'assessment_id' => $fixture['assessment']->id,
            'student_id' => $fixture['student']->id,
            'new_score' => $newScore,
            'reason' => 'Transcription error on the paper mark sheet',
        ])->assertSessionHasNoErrors();

        return GradeChangeRequest::firstOrFail();
    }

    public function test_grade_change_applies_only_after_both_keys(): void
    {
        $fixture = $this->makeLockedGradebook();
        $request = $this->submitRequest($fixture);

        $this->assertSame('Pending', $request->status);
        $this->assertSame(58.0, (float) $request->old_score);

        // Principal cannot act before the VP's first key.
        $this->actingAs($fixture['principal']->user)
            ->post("/principal/approvals/grade-changes/{$request->id}/approve")
            ->assertForbidden();
        $this->assertSame(58.0, (float) $fixture['grade']->fresh()->score);

        // VP first key — nothing applied yet.
        $this->actingAs($fixture['vp']->user)
            ->post("/vp_academic/approvals/grade-changes/{$request->id}/approve")
            ->assertSessionHasNoErrors();
        $this->assertSame('VP_Approved', $request->fresh()->status);
        $this->assertSame(58.0, (float) $fixture['grade']->fresh()->score);

        // Principal second key — the score is applied.
        $this->actingAs($fixture['principal']->user)
            ->post("/principal/approvals/grade-changes/{$request->id}/approve")
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('Applied', $request->status);
        $this->assertNotNull($request->applied_at);
        $this->assertSame(85.0, (float) $fixture['grade']->fresh()->score);

        $this->assertDatabaseHas('audit_logs', ['action' => 'Requested post-lock grade change', 'entity_id' => $request->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'VP approved grade-change request (first key)', 'entity_id' => $request->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Principal co-approved and applied grade change', 'entity_id' => $request->id]);
    }

    public function test_request_is_rejected_when_the_term_is_not_locked(): void
    {
        $fixture = $this->makeLockedGradebook();
        $fixture['term']->update(['is_locked' => false]);

        $this->actingAs($fixture['teacher']->user)->post('/teacher/gradebook/change-requests', [
            'assessment_id' => $fixture['assessment']->id,
            'student_id' => $fixture['student']->id,
            'new_score' => 85,
            'reason' => 'Should be edited directly instead',
        ])->assertSessionHasErrors('assessment_id');

        $this->assertDatabaseCount('grade_change_requests', 0);
    }

    public function test_score_above_assessment_maximum_is_rejected(): void
    {
        $fixture = $this->makeLockedGradebook();

        $this->actingAs($fixture['teacher']->user)->post('/teacher/gradebook/change-requests', [
            'assessment_id' => $fixture['assessment']->id,
            'student_id' => $fixture['student']->id,
            'new_score' => 120,
            'reason' => 'Too high',
        ])->assertSessionHasErrors('new_score');
    }

    public function test_vp_rejection_stops_the_request_without_touching_the_grade(): void
    {
        $fixture = $this->makeLockedGradebook();
        $request = $this->submitRequest($fixture);

        $this->actingAs($fixture['vp']->user)
            ->post("/vp_academic/approvals/grade-changes/{$request->id}/reject")
            ->assertSessionHasNoErrors();

        $this->assertSame('Rejected', $request->fresh()->status);
        $this->assertSame(58.0, (float) $fixture['grade']->fresh()->score);

        // A decided request cannot be re-approved through either key.
        $this->actingAs($fixture['vp']->user)
            ->post("/vp_academic/approvals/grade-changes/{$request->id}/approve")
            ->assertForbidden();
        $this->actingAs($fixture['principal']->user)
            ->post("/principal/approvals/grade-changes/{$request->id}/approve")
            ->assertForbidden();
    }

    public function test_teacher_can_cancel_only_while_pending_and_only_their_own(): void
    {
        $fixture = $this->makeLockedGradebook();
        $request = $this->submitRequest($fixture);

        $otherTeacher = $this->makeStaff('teacher', 'Teacher', 'other.teacher@test.local');
        $this->actingAs($otherTeacher->user)
            ->post("/teacher/gradebook/change-requests/{$request->id}/cancel")
            ->assertForbidden();

        $this->actingAs($fixture['teacher']->user)
            ->post("/teacher/gradebook/change-requests/{$request->id}/cancel")
            ->assertSessionHasNoErrors();
        $this->assertSame('Cancelled', $request->fresh()->status);

        // Once decided (cancelled), it is locked — no further cancel or approval.
        $this->actingAs($fixture['teacher']->user)
            ->post("/teacher/gradebook/change-requests/{$request->id}/cancel")
            ->assertForbidden();
        $this->actingAs($fixture['vp']->user)
            ->post("/vp_academic/approvals/grade-changes/{$request->id}/approve")
            ->assertForbidden();
    }

    public function test_teacher_cannot_request_changes_for_a_class_they_do_not_teach(): void
    {
        $fixture = $this->makeLockedGradebook();
        $outsider = $this->makeStaff('teacher', 'Teacher', 'outsider@test.local');

        $this->actingAs($outsider->user)->post('/teacher/gradebook/change-requests', [
            'assessment_id' => $fixture['assessment']->id,
            'student_id' => $fixture['student']->id,
            'new_score' => 85,
            'reason' => 'Not my class',
        ])->assertForbidden();
    }

    public function test_locked_gradebook_page_shows_the_request_form_and_my_requests(): void
    {
        $fixture = $this->makeLockedGradebook();
        $this->submitRequest($fixture);

        $this->actingAs($fixture['teacher']->user)->get('/teacher/gradebook')
            ->assertOk()
            ->assertSee('Request a grade change')
            ->assertSee('My grade-change requests')
            ->assertSee('Fix MyGrade');
    }

    public function test_approval_pages_show_the_request_to_each_key_holder_in_turn(): void
    {
        $fixture = $this->makeLockedGradebook();
        $request = $this->submitRequest($fixture);

        $this->actingAs($fixture['vp']->user)->get('/vp_academic/approvals')
            ->assertOk()
            ->assertSee('Post-lock grade changes')
            ->assertSee('Fix MyGrade');

        $this->actingAs($fixture['vp']->user)->post("/vp_academic/approvals/grade-changes/{$request->id}/approve");

        $this->actingAs($fixture['principal']->user)->get('/principal/approvals')
            ->assertOk()
            ->assertSee('Post-lock grade changes')
            ->assertSee('Fix MyGrade');
    }

    public function test_duplicate_request_for_the_same_grade_is_blocked_while_one_is_in_flight(): void
    {
        $fixture = $this->makeLockedGradebook();
        $this->submitRequest($fixture);

        $this->actingAs($fixture['teacher']->user)->post('/teacher/gradebook/change-requests', [
            'assessment_id' => $fixture['assessment']->id,
            'student_id' => $fixture['student']->id,
            'new_score' => 90,
            'reason' => 'Second thoughts',
        ])->assertSessionHasErrors('assessment_id');

        $this->assertDatabaseCount('grade_change_requests', 1);
    }
}
