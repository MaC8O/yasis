<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * §9.2 Treasurer Source Prep: the downloadable template carries the agreed
 * ISMS student-ID matching key and the headers the importer consumes.
 */
class FeeImportTemplateTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_treasurer_can_download_the_import_template(): void
    {
        $this->seedRoles();
        $treasurer = $this->makeStaff('treasurer', 'Treasurer');

        $response = $this->actingAs($treasurer->user)->get('/treasurer/import-template');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('fee_import_template.csv', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('student_id,date,amount,balance,status,restricted', $response->getContent());
        $this->assertStringContainsString('YAS-2026-', $response->getContent());
    }

    public function test_the_template_round_trips_through_the_importer(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $treasurer = $this->makeStaff('treasurer', 'Treasurer');

        Student::create([
            'student_id_number' => 'YAS-2026-0001',
            'name' => 'Aye Min',
            'date_of_birth' => now()->subYears(14), 'gender' => 'M',
            'department_id' => $department->id,
            'enrollment_status' => 'Enrolled', 'admission_date' => now()->subYear(),
        ]);

        $csv = $this->actingAs($treasurer->user)->get('/treasurer/import-template')->getContent();
        $file = UploadedFile::fake()->createWithContent('fee_import_template.csv', $csv);

        $this->actingAs($treasurer->user)
            ->post('/treasurer/import', ['period' => 'Q1 2026', 'file' => $file])
            ->assertSessionHasNoErrors();

        $batch = ImportBatch::latest('id')->firstOrFail();
        $this->assertSame(3, $batch->row_count);

        // Row 1 matches the seeded student via the ISMS key; rows 2–3 flag as unmatched.
        $this->assertSame(1, $batch->importedFeeRecords()->whereNotNull('student_id')->count());
        $this->assertSame(2, $batch->importedFeeRecords()->whereNull('student_id')->count());
        // The SDA sample row is restricted.
        $this->assertSame(1, $batch->importedFeeRecords()->where('is_restricted', true)->count());
    }

    public function test_other_roles_cannot_download_the_template(): void
    {
        $this->seedRoles();
        $teacher = $this->makeStaff('teacher', 'Teacher');

        $this->actingAs($teacher->user)->get('/treasurer/import-template')->assertForbidden();
    }
}
