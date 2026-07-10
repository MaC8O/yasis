<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * ui-spec §7.8: registrar-scoped announcement composer — parity with the
 * Principal composer; delivery respects the audience scope on read.
 */
class RegistrarAnnouncementTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_registrar_can_publish_a_school_wide_announcement(): void
    {
        $this->seedRoles();
        $registrar = $this->makeStaff('registrar', 'Registrar');

        $this->actingAs($registrar->user)->post('/registrar/announcements', [
            'audience' => 'All',
            'title' => 'Registration window opens Monday',
            'body' => 'Bring the completed forms to the records office.',
        ])->assertSessionHasNoErrors();

        $announcement = Announcement::firstOrFail();
        $this->assertSame('School', $announcement->audience_type);
        $this->assertSame($registrar->id, $announcement->author_id);
        $this->assertNotNull($announcement->published_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Published announcement', 'entity_id' => $announcement->id]);
    }

    public function test_department_targeted_announcement_reaches_only_that_departments_guardians(): void
    {
        $this->seedRoles();
        $highSchool = $this->seedDepartment('High School');
        $elementary = $this->seedDepartment('Elementary');
        $registrar = $this->makeStaff('registrar', 'Registrar');

        $this->actingAs($registrar->user)->post('/registrar/announcements', [
            'audience' => 'High School',
            'title' => 'HS transcript pickups',
            'body' => 'Transcripts for grade 12 are ready.',
        ])->assertSessionHasNoErrors();

        $makeGuardianWithChild = function (string $email, int $departmentId) {
            $user = User::create(['name' => 'G '.$email, 'email' => $email, 'password' => Hash::make('password'), 'status' => 'Active']);
            $user->assignRole('guardian');
            $guardian = Guardian::create(['user_id' => $user->id, 'relationship' => 'Mother', 'phone' => '0911']);
            $student = Student::create([
                'student_id_number' => 'S-'.fake()->unique()->numberBetween(1000, 99999),
                'name' => 'Kid Of '.$email,
                'date_of_birth' => now()->subYears(10), 'gender' => 'M',
                'department_id' => $departmentId,
                'enrollment_status' => 'Enrolled', 'admission_date' => now()->subYear(),
            ]);
            $guardian->students()->attach($student->id, ['is_primary' => true]);

            return $user;
        };

        $hsGuardian = $makeGuardianWithChild('hs@test.local', $highSchool->id);
        $elemGuardian = $makeGuardianWithChild('elem@test.local', $elementary->id);

        $this->actingAs($hsGuardian)->get('/guardian/notices')->assertOk()->assertSee('HS transcript pickups');
        $this->actingAs($elemGuardian)->get('/guardian/notices')->assertOk()->assertDontSee('HS transcript pickups');
    }

    public function test_the_composer_page_renders(): void
    {
        $this->seedRoles();
        $this->seedDepartment();
        $registrar = $this->makeStaff('registrar', 'Registrar');

        $this->actingAs($registrar->user)->get('/registrar/announcements')
            ->assertOk()
            ->assertSee('New announcement')
            ->assertSee('Publish announcement');
    }

    public function test_title_and_body_are_required(): void
    {
        $this->seedRoles();
        $registrar = $this->makeStaff('registrar', 'Registrar');

        $this->actingAs($registrar->user)->post('/registrar/announcements', [
            'audience' => 'All', 'title' => '', 'body' => '',
        ])->assertSessionHasErrors(['title', 'body']);

        $this->assertSame(0, Announcement::count());
    }

    public function test_other_roles_cannot_use_the_registrar_composer(): void
    {
        $this->seedRoles();
        $teacher = $this->makeStaff('teacher', 'Teacher');

        $this->actingAs($teacher->user)->post('/registrar/announcements', [
            'audience' => 'All', 'title' => 'Nope', 'body' => 'Nope',
        ])->assertForbidden();
    }
}
