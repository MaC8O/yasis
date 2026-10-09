<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class TimezoneSettingReadOnlyTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_settings_screen_shows_the_configured_timezone_read_only(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin');
        config(['app.timezone' => 'Asia/Yangon']);

        $this->actingAs($admin->user)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Asia/Yangon (UTC+06:30)')
            ->assertDontSee('name="timezone"', false);
    }

    public function test_timezone_cannot_be_changed_through_the_settings_form(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin');

        $this->actingAs($admin->user)
            ->post(route('admin.settings.update'), ['timezone' => 'UTC'])
            ->assertSessionHasNoErrors();

        $this->assertNull(SystemSetting::get('timezone'));
    }
}
