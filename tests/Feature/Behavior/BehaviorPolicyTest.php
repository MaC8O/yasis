<?php

namespace Tests\Feature\Behavior;

use App\Models\AuditLog;
use App\Models\BehaviorCategory;
use App\Models\SystemSetting;
use App\Services\Behavior\BehaviorPolicySettings;
use App\Services\Behavior\BehaviorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsBehaviorFixtures;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/** The behavior policy page (thresholds) and the incident categories. */
class BehaviorPolicyTest extends TestCase
{
    use BuildsBehaviorFixtures, RefreshDatabase, SeedsCoreData;

    private const DEFAULTS = ['Warning' => 80, 'FinalWarning' => 60, 'ExpulsionReview' => 40];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
    }

    private function thresholds(): array
    {
        return app(BehaviorPolicySettings::class)->thresholds();
    }

    public function test_defaults_come_from_settings_seeded_by_the_migration(): void
    {
        $this->assertSame(self::DEFAULTS, $this->thresholds());
        $this->assertSame('80', SystemSetting::get('behavior_threshold_warning'));
        $this->assertSame(10, BehaviorCategory::count());
    }

    public function test_vp_saves_new_thresholds_and_the_audit_has_old_new_and_role(): void
    {
        $this->actingAs($this->vp)->get(route('behavior.policy.show'))->assertOk()->assertSee('Save thresholds');

        $this->actingAs($this->vp)->put(route('behavior.policy.update'), ['warning' => 85, 'final_warning' => 65, 'expulsion_review' => 45])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertSame(['Warning' => 85, 'FinalWarning' => 65, 'ExpulsionReview' => 45], $this->thresholds());
        $audit = AuditLog::where('action', 'Updated behavior thresholds')->firstOrFail();
        $this->assertSame('vp_academic', $audit->role);
        $this->assertSame(['warning' => 80, 'final_warning' => 60, 'expulsion_review' => 40], $audit->details['old']);
        $this->assertSame(['warning' => 85, 'final_warning' => 65, 'expulsion_review' => 45], $audit->details['new']);
    }

    public function test_principal_can_view_but_not_save_and_teacher_cannot_do_either(): void
    {
        $this->actingAs($this->principal)->get(route('behavior.policy.show'))->assertOk()->assertSee('view only')->assertDontSee('Save thresholds');

        $new = ['warning' => 90, 'final_warning' => 70, 'expulsion_review' => 50];
        $this->actingAs($this->principal)->put(route('behavior.policy.update'), $new)->assertForbidden();
        $this->actingAs($this->principal)->get(route('behavior.policy.preview', $new))->assertForbidden();
        $this->actingAs($this->teacher)->put(route('behavior.policy.update'), $new)->assertForbidden();
        $this->actingAs($this->teacher)->get(route('behavior.policy.show'))->assertForbidden();

        foreach ([$this->admin, $this->registrar, $this->guardianUser, $this->studentUser] as $user) {
            $this->actingAs($user)->get(route('behavior.policy.show'))->assertForbidden();
            $this->actingAs($user)->put(route('behavior.policy.update'), $new)->assertForbidden();
        }

        $this->assertSame(self::DEFAULTS, $this->thresholds());
        $this->assertSame(0, AuditLog::where('action', 'Updated behavior thresholds')->count());
    }

    public function test_admin_technical_settings_endpoint_cannot_write_the_thresholds(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'behavior_threshold_warning' => 99, 'behavior_threshold_final_warning' => 98, 'behavior_threshold_expulsion_review' => 97,
            'institution_name' => 'Yangon Adventist Seminary',
        ]);

        $this->assertSame(self::DEFAULTS, $this->thresholds());
    }

    public function test_principal_governance_endpoint_cannot_write_the_thresholds(): void
    {
        $this->actingAs($this->principal)->put(route('principal.governance.update'), [
            'behavior_threshold_warning' => 99, 'behavior_threshold_final_warning' => 98, 'behavior_threshold_expulsion_review' => 97,
        ]);

        $this->assertSame(self::DEFAULTS, $this->thresholds());
    }

    public function test_out_of_order_or_out_of_range_thresholds_are_refused_with_a_clear_message(): void
    {
        $cases = [
            [['warning' => 60, 'final_warning' => 80, 'expulsion_review' => 40], 'final_warning', 'must be lower than the Warning threshold'],
            [['warning' => 80, 'final_warning' => 60, 'expulsion_review' => 60], 'expulsion_review', 'must be lower than the Final warning threshold'],
            [['warning' => 100, 'final_warning' => 60, 'expulsion_review' => 40], 'warning', 'between 1 and 99'],
            [['warning' => 80, 'final_warning' => 60, 'expulsion_review' => 0], 'expulsion_review', 'between 1 and 99'],
            [['warning' => 80.5, 'final_warning' => 60, 'expulsion_review' => 40], 'warning', 'whole number'],
            [['warning' => 'abc', 'final_warning' => 60, 'expulsion_review' => 40], 'warning', 'whole number'],
            [['final_warning' => 60, 'expulsion_review' => 40], 'warning', 'Enter a Warning threshold'],
        ];

        foreach ($cases as [$input, $field, $message]) {
            $this->actingAs($this->vp)->put(route('behavior.policy.update'), $input)->assertSessionHasErrors($field);
            $shown = implode(' ', session('errors')->get($field));
            $this->assertStringContainsString($message, $shown, "For input ".json_encode($input));
            $this->actingAs($this->vp)->getJson(route('behavior.policy.preview', $input))->assertStatus(422);
        }

        $this->assertSame(self::DEFAULTS, $this->thresholds());
    }

    public function test_preview_counts_students_newly_flagged_or_unflagged_per_stage(): void
    {
        $service = app(BehaviorService::class);
        $service->createAndDeduct($this->vp, $this->student, $this->reportFields(), 25, 'Repeated disruption.');      // 75 → Warning
        $service->createAndDeduct($this->vp, $this->otherStudent, $this->reportFields(), 12, 'Late to class often.');  // 88 → none

        $pick = fn (array $s) => array_intersect_key($s, array_flip(['current', 'proposed', 'open', 'newly_flagged', 'unflagged']));

        // Raise Warning to 90 (88 now inside) and Final warning to 76 (75 now inside).
        $stages = $this->actingAs($this->vp)->getJson(route('behavior.policy.preview', ['warning' => 90, 'final_warning' => 76, 'expulsion_review' => 40]))
            ->assertOk()->json('stages');
        $this->assertSame(['current' => 80, 'proposed' => 90, 'open' => 1, 'newly_flagged' => 1, 'unflagged' => 0], $pick($stages['Warning']));
        $this->assertSame(['current' => 60, 'proposed' => 76, 'open' => 0, 'newly_flagged' => 1, 'unflagged' => 0], $pick($stages['FinalWarning']));

        // Lower Warning to 70: the student at 75 would be unflagged.
        $stages = $this->actingAs($this->vp)->getJson(route('behavior.policy.preview', ['warning' => 70, 'final_warning' => 60, 'expulsion_review' => 40]))->json('stages');
        $this->assertSame(['current' => 80, 'proposed' => 70, 'open' => 1, 'newly_flagged' => 0, 'unflagged' => 1], $pick($stages['Warning']));

        // A preview never changes anything.
        $this->assertSame(self::DEFAULTS, $this->thresholds());
    }

    public function test_vp_manages_categories_and_inactive_ones_are_not_offered(): void
    {
        $this->actingAs($this->vp)->post(route('behavior.categories.store'), ['name' => 'Littering', 'description' => 'Leaving rubbish around school.'])->assertRedirect();
        $littering = BehaviorCategory::where('name', 'Littering')->firstOrFail();
        $this->assertNotNull(AuditLog::where('action', 'Added behavior category')->where('entity_id', $littering->id)->first());

        $this->actingAs($this->vp)->post(route('behavior.categories.store'), ['name' => 'Littering'])->assertSessionHasErrors('name');

        $this->actingAs($this->vp)->put(route('behavior.categories.update', $littering), ['name' => 'Littering', 'is_active' => 0, 'sort_order' => 5])->assertRedirect();
        $this->assertFalse($littering->fresh()->is_active);

        $this->actingAs($this->teacher)->get(route('behavior.reports.create', $this->student))->assertOk()->assertDontSee('Littering');
        $this->actingAs($this->teacher)->post(route('behavior.reports.store', $this->student), $this->reportFields(['behavior_category_id' => $littering->id]))->assertSessionHas('warning');

        $this->actingAs($this->principal)->post(route('behavior.categories.store'), ['name' => 'Principal category'])->assertForbidden();
        $this->actingAs($this->teacher)->get(route('behavior.categories.index'))->assertForbidden();
    }
}
