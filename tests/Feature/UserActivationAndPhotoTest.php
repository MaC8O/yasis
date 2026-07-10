<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Admin user creation must produce accounts that can actually sign in:
 * either immediately (initial password) or after completing the emailed
 * setup link (Pending -> Active). Profile photos are normalized to a
 * square 512px JPEG on upload.
 */
class UserActivationAndPhotoTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_completing_the_setup_link_activates_a_pending_account(): void
    {
        $this->seedRoles();

        $user = User::create([
            'name' => 'New Teacher', 'email' => 'new.teacher@test.local',
            'password' => Hash::make('temporary-random'), 'status' => 'Pending',
        ]);
        $user->assignRole('teacher');

        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'new.teacher@test.local',
            'password' => 'chosen-password1',
            'password_confirmation' => 'chosen-password1',
        ])->assertRedirect('/login');

        $user->refresh();
        $this->assertSame('Active', $user->status);
        $this->assertNotNull($user->email_verified_at);

        // And the confirmed account can actually sign in.
        $this->post('/login', ['email' => 'new.teacher@test.local', 'password' => 'chosen-password1'])
            ->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_admin_can_create_a_user_with_an_initial_password_that_is_active_immediately(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin');

        $this->actingAs($admin->user)->post('/admin/users', [
            'name' => 'Front Desk', 'email' => 'frontdesk@test.local', 'role' => 'registrar',
            'staff_id_number' => 'USR-0900', 'joined_date' => now()->toDateString(),
            'password' => 'welcome-2026!', 'password_confirmation' => 'welcome-2026!',
        ])->assertSessionHasNoErrors();

        $created = User::where('email', 'frontdesk@test.local')->firstOrFail();
        $this->assertSame('Active', $created->status);

        $this->post('/logout');
        $this->post('/login', ['email' => 'frontdesk@test.local', 'password' => 'welcome-2026!'])
            ->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_creating_without_a_password_leaves_the_account_pending(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin');

        $this->actingAs($admin->user)->post('/admin/users', [
            'name' => 'Invited User', 'email' => 'invited@test.local', 'role' => 'teacher',
            'staff_id_number' => 'USR-0901', 'joined_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame('Pending', User::where('email', 'invited@test.local')->value('status'));
    }

    public function test_mismatched_initial_password_confirmation_is_rejected(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin');

        $this->actingAs($admin->user)->post('/admin/users', [
            'name' => 'Typo User', 'email' => 'typo@test.local', 'role' => 'teacher',
            'staff_id_number' => 'USR-0902', 'joined_date' => now()->toDateString(),
            'password' => 'welcome-2026!', 'password_confirmation' => 'welcome-2027!',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'typo@test.local']);
    }

    public function test_uploaded_photo_is_normalized_to_a_square_512px_jpeg(): void
    {
        $this->seedRoles();
        Storage::fake('public');
        $admin = $this->makeStaff('admin', 'Admin');

        // A large landscape photo — previously rejected (2 MB cap) and stored unprocessed.
        $photo = UploadedFile::fake()->image('holiday.png', 1600, 900);

        $this->actingAs($admin->user)
            ->post("/admin/users/{$admin->user->id}/photo", ['photo' => $photo])
            ->assertSessionHasNoErrors();

        $path = $admin->user->fresh()->photo_path;
        $this->assertNotNull($path);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(512, $width);
        $this->assertSame(512, $height);
    }
}
