<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\PromotionBatch;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Exercises §4.4's two-key co-approval rule: a promotion batch is not applied to
 * enrollment/graduation records until BOTH VP Academic (first key) and Principal
 * (second key) have signed off, in that order.
 */
class PromotionTwoKeyApprovalTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private function makeBatch(): array
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();

        $registrar = $this->makeStaff('registrar', 'Registrar', 'registrar@test.local');
        $vp = $this->makeStaff('vp_academic', 'VP_Academic', 'vp@test.local');
        $principal = $this->makeStaff('principal', 'Principal', 'principal@test.local');

        $fromSection = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);
        $toSection = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 10-A', 'capacity' => 35]);

        $promotee = Student::create(['student_id_number' => 'P-0001', 'name' => 'Promote Me', 'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);
        $graduate = Student::create(['student_id_number' => 'P-0002', 'name' => 'Graduate Me', 'admission_date' => now()->subYears(4), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);

        $promoteeEnrollment = Enrollment::create(['student_id' => $promotee->id, 'section_id' => $fromSection->id, 'status' => 'Active']);
        $graduateEnrollment = Enrollment::create(['student_id' => $graduate->id, 'section_id' => $fromSection->id, 'status' => 'Active']);

        $this->actingAs($registrar->user)->post("/registrar/promotions/{$fromSection->id}", [
            'actions' => [
                ['student_id' => $promotee->id, 'action' => 'Promote', 'to_section_id' => $toSection->id],
                ['student_id' => $graduate->id, 'action' => 'Graduate'],
            ],
        ])->assertSessionHasNoErrors();

        $batch = PromotionBatch::firstOrFail();

        return compact('batch', 'vp', 'principal', 'fromSection', 'toSection', 'promotee', 'graduate', 'promoteeEnrollment', 'graduateEnrollment');
    }

    public function test_promotion_only_takes_effect_after_both_vp_and_principal_approve(): void
    {
        ['batch' => $batch, 'vp' => $vp, 'principal' => $principal, 'toSection' => $toSection, 'promotee' => $promotee, 'graduate' => $graduate, 'promoteeEnrollment' => $promoteeEnrollment, 'graduateEnrollment' => $graduateEnrollment] = $this->makeBatch();

        $this->assertSame('Pending', $batch->fresh()->status);

        // Principal cannot act before the VP's first key.
        $this->actingAs($principal->user)->post("/principal/approvals/promotions/{$batch->id}/approve")
            ->assertForbidden();
        $this->assertSame('Pending', $batch->fresh()->status);
        $this->assertSame('Active', $promoteeEnrollment->fresh()->status);

        // VP applies the first key.
        $this->actingAs($vp->user)->post("/vp_academic/approvals/promotions/{$batch->id}/approve")
            ->assertSessionHasNoErrors();
        $this->assertSame('VP_Approved', $batch->fresh()->status);

        // Nothing applied to enrollment/graduation yet — only the second key does that.
        $this->assertSame('Active', $promoteeEnrollment->fresh()->status);
        $this->assertSame('Enrolled', $promotee->fresh()->enrollment_status);

        // Principal applies the second key.
        $this->actingAs($principal->user)->post("/principal/approvals/promotions/{$batch->id}/approve")
            ->assertSessionHasNoErrors();

        $batch->refresh();
        $this->assertSame('Applied', $batch->status);
        $this->assertNotNull($batch->vp_approved_by);
        $this->assertNotNull($batch->principal_approved_by);

        $this->assertSame('Completed', $promoteeEnrollment->fresh()->status);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $promotee->id,
            'section_id' => $toSection->id,
            'status' => 'Active',
        ]);

        $this->assertSame('Completed', $graduateEnrollment->fresh()->status);
        $this->assertSame('Graduated', $graduate->fresh()->enrollment_status);
    }

    public function test_vp_rejection_stops_the_batch_without_touching_enrollments(): void
    {
        ['batch' => $batch, 'vp' => $vp, 'promoteeEnrollment' => $promoteeEnrollment] = $this->makeBatch();

        $this->actingAs($vp->user)->post("/vp_academic/approvals/promotions/{$batch->id}/reject")
            ->assertSessionHasNoErrors();

        $this->assertSame('Rejected', $batch->fresh()->status);
        $this->assertSame('Active', $promoteeEnrollment->fresh()->status);
    }

    public function test_principal_rejection_after_vp_approval_stops_the_batch_without_applying_it(): void
    {
        ['batch' => $batch, 'vp' => $vp, 'principal' => $principal, 'promoteeEnrollment' => $promoteeEnrollment] = $this->makeBatch();

        $this->actingAs($vp->user)->post("/vp_academic/approvals/promotions/{$batch->id}/approve")->assertSessionHasNoErrors();
        $this->actingAs($principal->user)->post("/principal/approvals/promotions/{$batch->id}/reject")->assertSessionHasNoErrors();

        $this->assertSame('Rejected', $batch->fresh()->status);
        $this->assertSame('Active', $promoteeEnrollment->fresh()->status);
    }
}
