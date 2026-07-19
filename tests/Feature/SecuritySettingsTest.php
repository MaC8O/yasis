<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Support\SecurityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_admin_can_configure_the_per_ip_login_throttle(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin');

        // The field is exposed on the Security policy section of the settings screen.
        $this->actingAs($admin->user)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Failed logins per minute per IP');

        // Saving it writes the same setting key SecurityPolicy reads at enforcement time.
        $this->actingAs($admin->user)
            ->post(route('admin.settings.update'), ['login_throttle_ip_per_min' => '7'])
            ->assertSessionHasNoErrors();

        $this->assertSame('7', SystemSetting::get('login_throttle_ip_per_min'));
        $this->assertSame(7, SecurityPolicy::loginThrottlePerIp());
    }
}
