<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * The VP (Academic) publishes announcements with the Principal's composer: same screen, same
 * audiences, same service. Roles with no announcement ability get 403 on the publish routes.
 */
class VpAnnouncementTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private User $vp;

    private User $principal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedDepartment('High School');
        $this->seedDepartment('Elementary');
        $this->vp = $this->makeStaff('vp_academic', 'VP_Academic', 'vp@test.local')->user;
        $this->principal = $this->makeStaff('principal', 'Principal', 'principal@test.local')->user;
    }

    private function portalUser(string $role, string $email): User
    {
        $user = User::create(['name' => ucfirst($role).' User', 'email' => $email, 'password' => Hash::make('password'), 'status' => 'Active']);
        $user->assignRole($role);

        return $user;
    }

    /** @return list<string> the audience options offered on a composer page */
    private function audiences(TestResponse $page): array
    {
        preg_match_all('/name="audience" value="([^"]+)"/', $page->getContent(), $m);

        return $m[1];
    }

    private function publish(User $user, string $portal, string $audience, string $title)
    {
        return $this->actingAs($user)->post(route("{$portal}.announcements.store"), [
            'audience' => $audience, 'title' => $title, 'body' => 'Details for '.$title,
        ]);
    }

    public function test_the_vp_gets_the_principals_composer_with_the_same_audiences(): void
    {
        $vpPage = $this->actingAs($this->vp)->get(route('vp_academic.announcements.index'))->assertOk();
        $principalPage = $this->actingAs($this->principal)->get(route('principal.announcements.index'))->assertOk();

        $this->assertSame(['All', 'Staff', 'Guardians', 'Students', 'Elementary', 'High School'], $this->audiences($vpPage));
        $this->assertSame($this->audiences($principalPage), $this->audiences($vpPage));

        $vpPage->assertSee('action="'.route('vp_academic.announcements.store').'"', false)
            ->assertDontSee(route('principal.announcements.store'))
            ->assertSee('VP Academic Portal');
        $principalPage->assertSee('action="'.route('principal.announcements.store').'"', false);
    }

    public function test_the_vp_publishes_to_each_audience_the_principal_can(): void
    {
        $hs = \App\Models\Department::where('name', 'High School')->firstOrFail();
        $expected = [
            'All' => ['School', null],
            'Staff' => ['Staff', null],
            'Guardians' => ['Guardians', null],
            'Students' => ['Students', null],
            'High School' => ['Department', $hs->id],
        ];

        foreach ($expected as $audience => [$type, $id]) {
            $this->publish($this->vp, 'vp_academic', $audience, "VP notice for {$audience}")
                ->assertRedirect()->assertSessionHas('status', 'Announcement published.');

            $announcement = Announcement::where('title', "VP notice for {$audience}")->sole();
            $this->assertSame($type, $announcement->audience_type);
            $this->assertSame($id, $announcement->audience_id);
            $this->assertSame($this->vp->id, $announcement->author_id);
            $this->assertNotNull($announcement->published_at);

            $audit = AuditLog::where('action', 'Published announcement')->where('entity_id', $announcement->id)->sole();
            $this->assertSame('vp_academic', $audit->role);
            $this->assertSame($this->vp->id, $audit->user_id);
        }
    }

    public function test_a_staff_announcement_from_the_vp_reaches_teachers(): void
    {
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user;
        $this->publish($this->vp, 'vp_academic', 'Staff', 'Curriculum meeting Thursday');

        $this->actingAs($teacher)->get(route('teacher.announcements.index'))->assertOk()->assertSee('Curriculum meeting Thursday');
    }

    public function test_the_principal_still_publishes_from_the_shared_composer(): void
    {
        $this->publish($this->principal, 'principal', 'All', 'Sports day moved to Friday')->assertSessionHas('status');

        $announcement = Announcement::where('title', 'Sports day moved to Friday')->sole();
        $this->assertSame($this->principal->id, $announcement->author_id);
        $this->assertSame('principal', AuditLog::where('entity_id', $announcement->id)->where('action', 'Published announcement')->sole()->role);
    }

    public function test_roles_without_the_ability_get_403_on_the_publish_routes(): void
    {
        $others = [
            $this->portalUser('student', 'student@test.local'),
            $this->portalUser('guardian', 'guardian@test.local'),
            $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user,
            $this->makeStaff('registrar', 'Registrar', 'registrar@test.local')->user,
            $this->makeStaff('admin', 'Admin', 'admin@test.local')->user,
        ];

        foreach ($others as $user) {
            foreach (['vp_academic', 'principal'] as $portal) {
                $this->actingAs($user)->get(route("{$portal}.announcements.index"))->assertForbidden();
                $this->publish($user, $portal, 'All', 'Should not be published')->assertForbidden();
            }
        }

        // Each of the two can only use its own route.
        $this->publish($this->vp, 'principal', 'All', 'Should not be published')->assertForbidden();
        $this->publish($this->principal, 'vp_academic', 'All', 'Should not be published')->assertForbidden();

        $this->assertSame(0, Announcement::count());
        $this->assertSame(0, AuditLog::where('action', 'Published announcement')->count());
    }

    public function test_the_vp_composer_validates_like_the_principals(): void
    {
        $this->actingAs($this->vp)->post(route('vp_academic.announcements.store'), ['audience' => 'All', 'title' => '', 'body' => ''])
            ->assertSessionHasErrors(['title', 'body']);
        $this->publish($this->vp, 'vp_academic', 'No Such Department', 'Unknown audience')->assertNotFound();

        $this->assertSame(0, Announcement::count());
    }
}
