<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class AdminUserFeaturesTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_admin_can_upload_and_delete_a_user_photo(): void
    {
        Storage::fake('public');
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $this->actingAs($admin->user)->post(route('admin.users.photo.upload', $teacher->user), [
            'photo' => UploadedFile::fake()->image('portrait.jpg', 400, 400),
        ])->assertSessionHasNoErrors();

        $teacher->user->refresh();
        $this->assertNotNull($teacher->user->photo_path);
        Storage::disk('public')->assertExists($teacher->user->photo_path);

        $path = $teacher->user->photo_path;
        $this->actingAs($admin->user)->delete(route('admin.users.photo.delete', $teacher->user))
            ->assertSessionHasNoErrors();

        $teacher->user->refresh();
        $this->assertNull($teacher->user->photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_photo_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $this->actingAs($admin->user)->post(route('admin.users.photo.upload', $teacher->user), [
            'photo' => UploadedFile::fake()->create('malware.exe', 100),
        ])->assertSessionHasErrors('photo');
    }

    public function test_per_page_selector_controls_the_user_list(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');

        for ($i = 1; $i <= 12; $i++) {
            $user = User::create(['name' => "Bulk User {$i}", 'email' => "bulk{$i}@test.local", 'password' => Hash::make('password'), 'status' => 'Active']);
            $user->assignRole('teacher');
        }

        $paged = $this->actingAs($admin->user)->get('/admin/users?per_page=10');
        $paged->assertOk();
        $this->assertSame(10, $paged->viewData('users')->count());

        $all = $this->actingAs($admin->user)->get('/admin/users?per_page=all');
        $all->assertOk();
        $this->assertSame(13, $all->viewData('users')->count());
    }

    public function test_user_creation_stores_personal_details(): void
    {
        $this->seedRoles();
        $this->seedDepartment();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');

        $this->actingAs($admin->user)->post('/admin/users', [
            'name' => 'Daw Mya Mya',
            'email' => 'mya.mya@test.local',
            'role' => 'teacher',
            'staff_id_number' => 'USR-0300',
            'joined_date' => '2026-06-01',
            'date_of_birth' => '1990-04-12',
            'gender' => 'Female',
            'phone' => '+95 900-123-456',
            'address' => 'Bahan Township, Yangon',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'mya.mya@test.local',
            'gender' => 'Female',
            'phone' => '+95 900-123-456',
            'address' => 'Bahan Township, Yangon',
        ]);
        $this->assertDatabaseHas('staff_profiles', ['staff_id_number' => 'USR-0300', 'phone' => '+95 900-123-456']);
    }

    public function test_admin_can_edit_an_academic_year_and_its_term_dates(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $term = $this->seedAcademicCalendar();
        $year = AcademicYear::findOrFail($term->academic_year_id);

        $this->actingAs($admin->user)->put(route('admin.academic-year.update', $year), [
            'year_label' => '2030-2031',
            'terms' => [
                ['id' => $term->id, 'start_date' => '2030-06-03', 'end_date' => '2030-08-04'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('2030-2031', $year->fresh()->year_label);
        $this->assertSame('2030-06-03', $term->fresh()->start_date->toDateString());
    }

    public function test_active_year_or_year_with_sections_cannot_be_deleted(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $term = $this->seedAcademicCalendar();
        $activeYear = AcademicYear::findOrFail($term->academic_year_id);

        // Active year is protected.
        $this->actingAs($admin->user)->delete(route('admin.academic-year.destroy', $activeYear))
            ->assertSessionHasErrors('year');
        $this->assertDatabaseHas('academic_years', ['id' => $activeYear->id]);

        // Inactive year with sections is protected.
        $oldYear = AcademicYear::create(['year_label' => '2020-2021', 'is_active' => false]);
        Section::create(['academic_year_id' => $oldYear->id, 'department_id' => $department->id, 'name' => 'Old 9-A', 'capacity' => 35]);
        $this->actingAs($admin->user)->delete(route('admin.academic-year.destroy', $oldYear))
            ->assertSessionHasErrors('year');
        $this->assertDatabaseHas('academic_years', ['id' => $oldYear->id]);

        // Inactive, empty year deletes cleanly.
        $emptyYear = AcademicYear::create(['year_label' => '2019-2020', 'is_active' => false]);
        $this->actingAs($admin->user)->delete(route('admin.academic-year.destroy', $emptyYear))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('academic_years', ['id' => $emptyYear->id]);
    }

    public function test_bulk_user_import_creates_accounts_and_reports_bad_rows(): void
    {
        $this->seedRoles();
        $this->seedDepartment('High School');
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');

        User::create(['name' => 'Existing', 'email' => 'existing@test.local', 'password' => Hash::make('password'), 'status' => 'Active']);

        $csv = "name,email,role,staff_id_number,department,joined_date,date_of_birth,gender,phone,address\n"
            ."New Teacher,new.teacher@test.local,teacher,USR-0500,High School,2026-06-01,1992-01-15,Male,+95 900-555-111,Yangon\n"
            ."New Guardian,new.guardian@test.local,guardian,,,,,,,\n"
            ."Existing User,existing@test.local,teacher,USR-0501,High School,2026-06-01,,,,\n"
            ."Bad Role,bad.role@test.local,superhero,,,,,,,\n"
            ."No StaffId,no.staffid@test.local,teacher,,High School,2026-06-01,,,,\n";

        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $response = $this->actingAs($admin->user)->post(route('admin.users.import.store'), ['file' => $file]);
        $response->assertRedirect(route('admin.users.import'));

        $this->assertDatabaseHas('users', ['email' => 'new.teacher@test.local', 'status' => 'Pending', 'gender' => 'Male']);
        $this->assertDatabaseHas('staff_profiles', ['staff_id_number' => 'USR-0500']);
        $this->assertDatabaseHas('users', ['email' => 'new.guardian@test.local']);
        $this->assertTrue(User::where('email', 'new.guardian@test.local')->first()->hasRole('guardian'));
        $this->assertDatabaseMissing('users', ['email' => 'bad.role@test.local']);
        $this->assertDatabaseMissing('users', ['email' => 'no.staffid@test.local']);

        $results = session('importResults');
        $this->assertCount(2, $results['created']);
        $this->assertCount(1, $results['skipped']);
        $this->assertCount(2, $results['errors']);
    }
}
