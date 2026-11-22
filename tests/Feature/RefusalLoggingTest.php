<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\BehaviorLedgerEntry;
use App\Models\LessonPlan;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Behavior\BehaviorService;
use App\Services\LessonPlans\LessonPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsBehaviorFixtures;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * What a refused action writes to laravel.log.
 *  - Normal refusals (refresh, double click, two tabs, a stale page, a value past a limit): nothing.
 *  - Refusals of actions the pages never offer: one WARNING line, user ID + action key, no stack
 *    trace, no names.
 * In both cases the person sees the same warning on the page they came from.
 */
class RefusalLoggingTest extends TestCase
{
    use BuildsBehaviorFixtures, RefreshDatabase, SeedsCoreData;

    /** @var list<MessageLogged> */
    private array $logged = [];

    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();

        // A real file channel, so the test reads exactly what would land in laravel.log.
        $this->logFile = tempnam(sys_get_temp_dir(), 'refusal-log');
        config([
            'logging.channels.refusal_test' => ['driver' => 'single', 'path' => $this->logFile, 'level' => 'debug'],
            'logging.default' => 'refusal_test',
        ]);
        Log::forgetChannel('refusal_test');
        Event::listen(MessageLogged::class, fn (MessageLogged $e) => $this->logged[] = $e);
    }

    protected function tearDown(): void
    {
        @unlink($this->logFile);
        parent::tearDown();
    }

    private function logText(): string
    {
        return (string) file_get_contents($this->logFile);
    }

    /** Group 1: nothing logged at all. */
    private function assertNothingLogged(): void
    {
        $this->assertSame([], array_map(fn ($e) => $e->level.': '.$e->message, $this->logged));
        $this->assertSame('', $this->logText());
    }

    /** Group 2: exactly one WARNING with only user ID and action; one line, no trace, none of $forbidden. */
    private function assertOneWarning(User $actor, string $action, array $forbidden): void
    {
        $this->assertCount(1, $this->logged);
        $entry = $this->logged[0];
        $this->assertSame('warning', $entry->level);
        $this->assertSame('Refused a request the page does not offer.', $entry->message);
        $this->assertSame(['user_id' => $actor->id, 'action' => $action], $entry->context, 'only the user ID and the action, no exception');

        $text = $this->logText();
        $lines = array_values(array_filter(explode("\n", trim($text))));
        $this->assertCount(1, $lines, 'a single line: no stack trace');
        $this->assertStringContainsString('.WARNING: Refused a request the page does not offer.', $lines[0]);
        $this->assertStringContainsString('"action":"'.$action.'"', $lines[0]);
        $this->assertStringNotContainsString('#0', $text);
        $this->assertStringNotContainsString('stacktrace', $text);
        foreach ($forbidden as $value) {
            $this->assertStringNotContainsString($value, $text);
        }
    }

    // ── Group 1: normal use, nothing logged ────────────────────────────────

    public function test_restoring_more_points_than_are_left_shows_the_warning_and_logs_nothing(): void
    {
        $deduction = app(BehaviorService::class)->createAndDeduct($this->vp, $this->student, $this->reportFields(), 3, 'Disrupted the lesson repeatedly.');
        $page = route('behavior.students.show', $this->student);
        $this->logged = [];

        $this->actingAs($this->vp)->from($page)
            ->post(route('behavior.entries.restore', $deduction), ['points' => 10, 'reason' => 'Trying to restore too much'])
            ->assertRedirect($page)
            ->assertSessionHas('warning', 'Only 3 point(s) of that deduction are left to restore.');

        $this->assertNothingLogged();
        $this->actingAs($this->vp)->get($page)->assertSee('Only 3 point(s) of that deduction are left to restore.');
    }

    public function test_activating_an_account_that_is_already_active_like_a_refresh_logs_nothing(): void
    {
        $page = route('admin.users.index');

        $this->actingAs($this->admin)->from($page)
            ->post(route('admin.users.activate', $this->guardianUser))
            ->assertRedirect($page)
            ->assertSessionHas('warning', "{$this->guardianUser->name}'s account is Active, not waiting for setup, so it can't be activated with a temporary password.");

        $this->assertNothingLogged();
    }

    public function test_deleting_a_plan_the_vp_reviewed_meanwhile_logs_nothing(): void
    {
        Storage::fake('lesson_plans');
        $assignment = TeachingAssignment::where('teacher_id', $this->teacher->id)->firstOrFail();
        $plans = app(LessonPlanService::class);
        $plan = $plans->create($this->teacher, $assignment->id, $this->year->terms()->value('id'), 'Week 1', null,
            UploadedFile::fake()->createWithContent('plan.pdf', "%PDF-1.4\n%%EOF\n"));
        // The teacher's page still shows Delete; the VP reviews in the meantime.
        $plans->review($this->vp, $plan, LessonPlan::APPROVED, null, $plan->currentVersion->id);
        $page = route('lesson-plans.show', $plan);
        $this->logged = [];
        file_put_contents($this->logFile, '');

        $this->actingAs($this->teacher)->from($page)
            ->delete(route('lesson-plans.destroy', $plan))
            ->assertRedirect($page)
            ->assertSessionHas('warning', 'This plan has been reviewed, so it cannot be deleted. Upload a new version instead.');

        $this->assertNothingLogged();
    }

    // ── Group 2: not offered by any page, one WARNING ──────────────────────

    public function test_deactivating_yourself_logs_one_warning_with_user_id_and_action_only(): void
    {
        $page = route('admin.users.index');

        $this->actingAs($this->admin)->from($page)
            ->post(route('admin.users.deactivate', $this->admin))
            ->assertRedirect($page)
            ->assertSessionHas('warning', 'You cannot deactivate your own account. Ask another Admin to do it.');

        $this->assertSame('Active', $this->admin->fresh()->status);
        $this->assertOneWarning($this->admin, 'account.deactivate_self', [$this->admin->name, $this->admin->email]);
        $this->actingAs($this->admin)->get($page)->assertSee('You cannot deactivate your own account.');
    }

    public function test_reactivating_a_record_without_portal_access_logs_one_warning_without_the_name(): void
    {
        $record = User::create(['name' => 'Hla Hla Myint', 'email' => 'hla.hla@internal.yasis.edu', 'password' => Hash::make('x'), 'status' => 'Inactive']);
        $page = route('admin.users.index');

        $this->actingAs($this->admin)->from($page)
            ->post(route('admin.users.reactivate', $record))
            ->assertRedirect($page)
            ->assertSessionHas('warning', fn ($m) => str_starts_with($m, 'You cannot reactivate Hla Hla Myint: this is an HR staff record without portal access'));

        $this->assertOneWarning($this->admin, 'account.reactivate_no_portal_access', ['Hla Hla Myint', 'hla.hla@internal.yasis.edu', 'Hla Hla']);
    }

    public function test_activating_an_erased_account_logs_one_warning(): void
    {
        $erased = User::create(['name' => 'Erased User #77', 'email' => 'erased-77@deleted.yasis.edu', 'password' => Hash::make('x'), 'status' => 'Inactive']);
        $erased->assignRole('guardian');

        $this->actingAs($this->admin)->from(route('admin.users.index'))
            ->post(route('admin.users.activate', $erased))
            ->assertSessionHas('warning', fn ($m) => str_starts_with($m, 'You cannot activate this account: it was erased'));

        $this->assertOneWarning($this->admin, 'account.activate_erased', ['erased-77@deleted.yasis.edu']);
    }

    public function test_restoring_something_that_is_not_a_deduction_logs_one_warning_without_the_student_name(): void
    {
        $service = app(BehaviorService::class);
        $deduction = $service->createAndDeduct($this->vp, $this->student, $this->reportFields(), 5, 'Disrupted the lesson repeatedly.');
        $restoration = $service->restore($this->vp, $deduction, 2, 'Part of it was a misunderstanding.');
        $this->assertNotSame(BehaviorLedgerEntry::DEDUCTION, $restoration->type);
        $page = route('behavior.students.show', $this->student);
        $this->logged = [];
        file_put_contents($this->logFile, '');

        $this->actingAs($this->vp)->from($page)
            ->post(route('behavior.entries.restore', $restoration), ['points' => 1, 'reason' => 'Hand-made request'])
            ->assertRedirect($page)
            ->assertSessionHas('warning', 'Only a deduction can be restored.');

        $this->assertOneWarning($this->vp, 'behavior.restore_non_deduction', [$this->student->name, $this->guardianUser->name, $this->vp->name]);
    }

    // ── Missing active year: a dashboard notice, not a log entry ───────────

    public function test_the_admin_dashboard_shows_a_notice_when_no_school_year_is_active(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('There is no active school year.');

        AcademicYear::query()->update(['is_active' => false]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('There is no active school year.')
            ->assertSee(route('admin.academic-year.index'));
    }

    public function test_the_missing_year_refusal_itself_logs_nothing(): void
    {
        AcademicYear::query()->update(['is_active' => false]);
        $page = route('behavior.students.show', $this->student);

        // The VP sees every student, so the request reaches the year check (a teacher has no
        // students in scope without an active year and gets 403 first).
        $this->actingAs($this->vp)->from($page)
            ->post(route('behavior.reports.vp-store', $this->student), $this->reportFields() + ['points' => 5, 'reason' => 'Fighting in the corridor.'])
            ->assertRedirect($page)
            ->assertSessionHas('warning', 'There is no active school year. Ask the Admin to activate one first.');

        $this->assertNothingLogged();
    }
}
