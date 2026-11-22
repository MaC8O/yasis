<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LessonPlan;
use App\Models\LessonPlanReview;
use App\Models\LessonPlanVersion;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Notifications\LessonPlanNotice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Concerns\BuildsBehaviorFixtures;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;
use ZipArchive;

/**
 * Lesson plans: who sees what, file storage and type checks, the review flow, delete rules,
 * notifications and audit entries.
 */
class LessonPlanTest extends TestCase
{
    use BuildsBehaviorFixtures, RefreshDatabase, SeedsCoreData;

    private TeachingAssignment $assignment;

    private TeachingAssignment $otherAssignment;

    private int $termId;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('lesson_plans');
        $this->buildSchool();

        $this->assignment = TeachingAssignment::where('teacher_id', $this->teacher->id)->firstOrFail();
        $maths = Subject::create(['code' => 'MAT9', 'name' => 'Mathematics', 'department_id' => $this->section->department_id]);
        $this->otherAssignment = TeachingAssignment::create(['section_id' => $this->section->id, 'subject_id' => $maths->id, 'teacher_id' => $this->otherTeacher->id]);
        $this->termId = $this->year->terms()->value('id');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    // ── File fixtures ───────────────────────────────────────────────────────

    private function tempPath(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lp');
        file_put_contents($path, $contents);

        return $this->tempFiles[] = $path;
    }

    private function upload(string $contents, string $name): UploadedFile
    {
        return new UploadedFile($this->tempPath($contents), $name, null, null, true);
    }

    private function pdf(string $name = 'plan.pdf'): UploadedFile
    {
        return $this->upload("%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n", $name);
    }

    /** A minimal Office Open XML package. $kind: docx | pptx; $macro adds a VBA project. */
    private function office(string $kind, string $name, bool $macro = false, bool $contentTypes = true): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'lp');
        $this->tempFiles[] = $path;
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        [$part, $type] = $kind === 'docx'
            ? ['word/document.xml', $macro ? 'application/vnd.ms-word.document.macroEnabled.main+xml' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml']
            : ['ppt/presentation.xml', $macro ? 'application/vnd.ms-powerpoint.presentation.macroEnabled.main+xml' : 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml'];

        if ($contentTypes) {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Override PartName="/'.$part.'" ContentType="'.$type.'"/></Types>');
        }
        $zip->addFromString($part, '<?xml version="1.0"?><root/>');
        if ($macro) {
            $zip->addFromString(dirname($part).'/vbaProject.bin', 'VBA');
        }
        $zip->close();

        return new UploadedFile($path, $name, null, null, true);
    }

    private function createPlan(?User $teacher = null, ?UploadedFile $file = null, array $overrides = []): LessonPlan
    {
        $teacher ??= $this->teacher;
        $assignment = $teacher->is($this->teacher) ? $this->assignment : $this->otherAssignment;

        $this->actingAs($teacher)->post(route('lesson-plans.store'), array_merge([
            'teaching_assignment_id' => $assignment->id,
            'term_id' => $this->termId,
            'title' => 'Week 1 plan by '.$teacher->email,
            'note' => 'First draft.',
            'file' => $file ?? $this->pdf(),
        ], $overrides))->assertRedirect();

        return LessonPlan::where('teacher_id', $teacher->id)->latest('id')->firstOrFail();
    }

    private function audits(LessonPlan|int $plan, string $action)
    {
        return AuditLog::where('entity_type', 'LessonPlan')->where('entity_id', is_int($plan) ? $plan : $plan->id)->where('action', $action)->get();
    }

    private function review(User $user, LessonPlan $plan, string $decision, ?string $comment = null, ?int $versionId = null)
    {
        return $this->actingAs($user)->post(route('lesson-plans.reviews.store', $plan), [
            'decision' => $decision, 'comment' => $comment,
            'version_id' => $versionId ?? $plan->fresh()->currentVersion->id,
        ]);
    }

    private function downloadUrl(LessonPlan $plan): string
    {
        return route('lesson-plans.versions.download', [$plan, $plan->fresh()->currentVersion]);
    }

    // ── Create and storage ──────────────────────────────────────────────────

    public function test_a_teacher_uploads_a_plan_stored_privately_with_an_audit_entry_and_the_vp_notified(): void
    {
        Notification::fake();
        $plan = $this->createPlan();

        $this->assertSame(LessonPlan::SUBMITTED, $plan->status);
        $this->assertSame(1, $plan->current_version);
        $this->assertSame($this->assignment->section_id, $plan->section_id);
        $this->assertSame($this->assignment->subject_id, $plan->subject_id);

        $version = $plan->currentVersion;
        $this->assertSame('plan.pdf', $version->original_name);
        $this->assertSame('application/pdf', $version->mime_type);
        $this->assertMatchesRegularExpression('#^'.$plan->id.'/[A-Za-z0-9]{40}\.pdf$#', $version->stored_path, 'stored under a random name');
        Storage::disk('lesson_plans')->assertExists($version->stored_path);
        $this->assertStringStartsWith(storage_path('app/private'), config('filesystems.disks.lesson_plans.root'), 'outside the public web root');
        $this->assertFalse(config('filesystems.disks.lesson_plans.serve'));

        $audit = $this->audits($plan, 'Created lesson plan')->sole();
        $this->assertSame('teacher', $audit->role);
        $this->assertSame($version->sha256, $audit->details['file']['sha256']);

        Notification::assertSentTo($this->vp, LessonPlanNotice::class, fn ($n) => $n->kind === 'lesson_plan.submitted' && $n->via($this->vp) === ['database']);
        Notification::assertNotSentTo([$this->principal, $this->admin, $this->teacher], LessonPlanNotice::class);
    }

    public function test_the_download_is_an_attachment_with_nosniff_and_no_store(): void
    {
        $plan = $this->createPlan();

        $response = $this->actingAs($this->teacher)->get($this->downloadUrl($plan));

        $response->assertOk();
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('plan.pdf', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }

    public function test_a_teacher_cannot_create_a_plan_for_a_class_they_do_not_teach(): void
    {
        $this->actingAs($this->teacher)->post(route('lesson-plans.store'), [
            'teaching_assignment_id' => $this->otherAssignment->id, 'term_id' => $this->termId, 'title' => 'Not mine', 'file' => $this->pdf(),
        ])->assertForbidden();

        $this->assertSame(0, LessonPlan::count());
        $this->assertSame([], Storage::disk('lesson_plans')->allFiles());
    }

    public function test_the_term_must_be_in_the_school_year_of_the_class(): void
    {
        $oldYear = \App\Models\AcademicYear::create(['year_label' => '2000-2001', 'is_active' => false]);
        $oldTerm = \App\Models\Term::create(['academic_year_id' => $oldYear->id, 'name' => 'Old term', 'sequence' => 1, 'start_date' => '2000-06-01', 'end_date' => '2000-09-01']);

        $this->actingAs($this->teacher)->post(route('lesson-plans.store'), [
            'teaching_assignment_id' => $this->assignment->id, 'term_id' => $oldTerm->id, 'title' => 'Wrong term', 'file' => $this->pdf(),
        ])->assertSessionHasErrors('term_id');

        $this->assertSame(0, LessonPlan::count());
    }

    // ── Visibility ──────────────────────────────────────────────────────────

    public function test_a_teacher_sees_only_their_own_plans_and_is_refused_another_teachers_plan_and_file(): void
    {
        $mine = $this->createPlan();
        $theirs = $this->createPlan($this->otherTeacher);

        $this->actingAs($this->teacher)->get(route('lesson-plans.index'))
            ->assertOk()->assertSee($mine->title)->assertDontSee($theirs->title)->assertDontSee('All lesson plans');

        $this->actingAs($this->teacher)->get(route('lesson-plans.show', $theirs))->assertForbidden();
        $this->actingAs($this->teacher)->get($this->downloadUrl($theirs))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('lesson-plans.queue'))->assertForbidden();
        $this->actingAs($this->teacher)->post(route('lesson-plans.versions.store', $theirs), ['file' => $this->pdf()])->assertForbidden();
        $this->actingAs($this->teacher)->put(route('lesson-plans.update', $theirs), ['title' => 'Taken over'])->assertForbidden();
        $this->actingAs($this->teacher)->delete(route('lesson-plans.destroy', $theirs))->assertForbidden();

        $this->assertSame(1, $theirs->versions()->count());
        $this->assertNotSame('Taken over', $theirs->fresh()->title);
    }

    public function test_a_teacher_keeps_read_only_access_to_their_plans_after_the_class_is_reassigned(): void
    {
        $plan = $this->createPlan();
        $this->assignment->update(['teacher_id' => $this->otherTeacher->id]);

        $this->actingAs($this->teacher)->get(route('lesson-plans.index'))->assertOk()->assertSee($plan->title)->assertSee('Read only');
        $this->actingAs($this->teacher)->get(route('lesson-plans.show', $plan))->assertOk()
            ->assertSee('Read only: you no longer teach')->assertDontSee('Upload a new version')->assertDontSee('Delete plan');
        $this->actingAs($this->teacher)->get($this->downloadUrl($plan))->assertOk();

        $this->actingAs($this->teacher)->post(route('lesson-plans.versions.store', $plan), ['file' => $this->pdf()])->assertForbidden();
        $this->actingAs($this->teacher)->put(route('lesson-plans.update', $plan), ['title' => 'Changed'])->assertForbidden();
        $this->actingAs($this->teacher)->delete(route('lesson-plans.destroy', $plan))->assertForbidden();
        $this->assertNotNull($plan->fresh());

        // The new teacher of that class does not see it: plans are their author's.
        $this->actingAs($this->otherTeacher)->get(route('lesson-plans.show', $plan))->assertForbidden();
    }

    public function test_a_teacher_keeps_read_only_access_to_plans_from_an_earlier_school_year(): void
    {
        $plan = $this->createPlan();
        $this->year->update(['is_active' => false]);
        \App\Models\AcademicYear::create(['year_label' => '2099-2100', 'is_active' => true]);

        $this->actingAs($this->teacher)->get(route('lesson-plans.show', $plan))->assertOk()->assertSee('Read only');
        $this->actingAs($this->teacher)->get($this->downloadUrl($plan))->assertOk();
        $this->actingAs($this->teacher)->post(route('lesson-plans.versions.store', $plan), ['file' => $this->pdf()])->assertForbidden();
    }

    public function test_the_vp_principal_and_admin_see_every_plan_and_file(): void
    {
        $mine = $this->createPlan();
        $theirs = $this->createPlan($this->otherTeacher);

        foreach ([$this->vp, $this->principal, $this->admin] as $user) {
            $this->actingAs($user)->get(route('lesson-plans.index'))->assertOk()->assertSee($mine->title)->assertSee($theirs->title)->assertDontSee('Upload a lesson plan');
            $this->actingAs($user)->get(route('lesson-plans.queue'))->assertOk()->assertSee($mine->title)->assertSee($theirs->title);
            $this->actingAs($user)->get(route('lesson-plans.show', $theirs))->assertOk();
            $this->actingAs($user)->get($this->downloadUrl($theirs))->assertOk();
        }

        $this->actingAs($this->vp)->get(route('lesson-plans.show', $mine))->assertSee('Save review');
        $this->actingAs($this->principal)->get(route('lesson-plans.show', $mine))->assertDontSee('Save review')->assertDontSee('Upload a new version');
        $this->actingAs($this->admin)->get(route('lesson-plans.show', $mine))->assertDontSee('Save review')->assertDontSee('Delete plan');
    }

    public function test_the_all_plans_list_filters_by_department_section_and_status(): void
    {
        $approved = $this->createPlan();
        $this->review($this->vp, $approved, LessonPlan::APPROVED);
        $waiting = $this->createPlan($this->otherTeacher);

        $this->actingAs($this->vp)->get(route('lesson-plans.index', ['status' => LessonPlan::APPROVED]))
            ->assertSee($approved->title)->assertDontSee($waiting->title);
        $this->actingAs($this->vp)->get(route('lesson-plans.index', ['section' => $this->section->id, 'department' => $this->section->department_id]))
            ->assertSee($approved->title)->assertSee($waiting->title);
        $this->actingAs($this->vp)->get(route('lesson-plans.index', ['department' => 999999]))
            ->assertSee('No lesson plans match.');
        $this->actingAs($this->vp)->get(route('lesson-plans.queue'))->assertSee($waiting->title)->assertDontSee($approved->title);
    }

    public function test_students_and_guardians_are_refused_every_list_plan_and_file(): void
    {
        $plan = $this->createPlan();
        $download = $this->downloadUrl($plan);

        foreach ([$this->studentUser, $this->guardianUser, $this->registrar] as $user) {
            $this->actingAs($user)->get(route('lesson-plans.index'))->assertForbidden();
            $this->actingAs($user)->get(route('lesson-plans.queue'))->assertForbidden();
            $this->actingAs($user)->get(route('lesson-plans.show', $plan))->assertForbidden();
            $this->actingAs($user)->get($download)->assertForbidden();
            $this->actingAs($user)->post(route('lesson-plans.store'), ['teaching_assignment_id' => $this->assignment->id, 'term_id' => $this->termId, 'title' => 'x', 'file' => $this->pdf()])->assertForbidden();
            $this->actingAs($user)->post(route('lesson-plans.reviews.store', $plan), ['decision' => 'Approved', 'version_id' => 1])->assertForbidden();
        }

        $this->assertSame(1, LessonPlan::count());
    }

    public function test_a_signed_out_request_for_a_file_is_sent_to_sign_in_without_the_file(): void
    {
        $plan = $this->createPlan();
        $download = $this->downloadUrl($plan);
        auth()->logout();

        $response = $this->get($download);

        $response->assertRedirect(route('login'));
        $this->assertStringNotContainsString('%PDF-', (string) $response->getContent());
    }

    public function test_a_version_of_another_plan_cannot_be_reached_through_this_plan(): void
    {
        $mine = $this->createPlan();
        $theirs = $this->createPlan($this->otherTeacher);

        $this->actingAs($this->teacher)
            ->get(route('lesson-plans.versions.download', [$mine, $theirs->currentVersion]))
            ->assertNotFound();
    }

    // ── File validation ─────────────────────────────────────────────────────

    public function test_pdf_word_and_powerpoint_files_are_accepted_by_their_content(): void
    {
        $docx = $this->createPlan(file: $this->office('docx', 'unit.docx'));
        $pptx = $this->createPlan(file: $this->office('pptx', 'slides.pptx'));

        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $docx->currentVersion->mime_type);
        $this->assertSame('application/vnd.openxmlformats-officedocument.presentationml.presentation', $pptx->currentVersion->mime_type);
    }

    public function test_files_that_are_not_really_pdf_word_or_powerpoint_are_refused(): void
    {
        $cases = [
            'an exe renamed to .pdf' => $this->upload("MZ\x90\x00\x03\x00\x00\x00 This program cannot be run in DOS mode.", 'plan.pdf'),
            'a PDF renamed to .docx' => $this->pdf('plan.docx'),
            'a plain zip renamed to .docx' => $this->office('docx', 'plan.docx', contentTypes: false),
            'a macro-enabled file renamed to .docx' => $this->office('docx', 'plan.docx', macro: true),
            'a macro-enabled .pptm' => $this->office('pptx', 'plan.pptm', macro: true),
            'a text file' => $this->upload('Just some notes', 'plan.txt'),
            'an HTML page renamed to .pdf' => $this->upload('<html><script>alert(1)</script></html>', 'plan.pdf'),
            'an empty file' => $this->upload('', 'plan.pdf'),
        ];

        foreach ($cases as $label => $file) {
            $this->actingAs($this->teacher)->post(route('lesson-plans.store'), [
                'teaching_assignment_id' => $this->assignment->id, 'term_id' => $this->termId, 'title' => 'Bad file', 'file' => $file,
            ])->assertSessionHasErrors('file', null, 'default');
            $this->assertSame(0, LessonPlan::count(), "$label must not create a plan");
        }

        $this->assertSame([], Storage::disk('lesson_plans')->allFiles(), 'nothing stored for refused files');
        $this->assertSame(0, AuditLog::where('entity_type', 'LessonPlan')->count());
    }

    public function test_the_refusal_says_why(): void
    {
        $this->actingAs($this->teacher)->post(route('lesson-plans.store'), [
            'teaching_assignment_id' => $this->assignment->id, 'term_id' => $this->termId, 'title' => 'Bad', 'file' => $this->pdf('plan.docx'),
        ])->assertSessionHasErrors(['file' => 'The file name ends in .docx but the file is a PDF document. Rename it to .pdf and upload it again.']);

        $this->actingAs($this->teacher)->post(route('lesson-plans.store'), [
            'teaching_assignment_id' => $this->assignment->id, 'term_id' => $this->termId, 'title' => 'Bad', 'file' => $this->office('docx', 'plan.docx', macro: true),
        ])->assertSessionHasErrors(['file' => 'Files with macros are not accepted. Save it as a normal .docx or .pptx and upload that.']);
    }

    public function test_files_over_10_mb_are_refused_and_10_mb_is_accepted(): void
    {
        $this->actingAs($this->teacher)->post(route('lesson-plans.store'), [
            'teaching_assignment_id' => $this->assignment->id, 'term_id' => $this->termId, 'title' => 'Too big',
            'file' => $this->upload('%PDF-1.4'.str_repeat('x', 10 * 1024 * 1024 + 1), 'big.pdf'),
        ])->assertSessionHasErrors(['file' => 'The file is larger than 10 MB.']);
        $this->assertSame(0, LessonPlan::count());

        $plan = $this->createPlan(file: $this->upload('%PDF-1.4'.str_repeat('x', 10 * 1024 * 1024 - 8), 'exact.pdf'));
        $this->assertSame(10 * 1024 * 1024, $plan->currentVersion->size_bytes);
    }

    public function test_a_new_version_is_checked_the_same_way(): void
    {
        $plan = $this->createPlan();

        $this->actingAs($this->teacher)->post(route('lesson-plans.versions.store', $plan), ['file' => $this->upload('MZ fake', 'v2.pdf')])
            ->assertSessionHasErrors('file');

        $this->assertSame(1, $plan->fresh()->current_version);
        $this->assertCount(1, Storage::disk('lesson_plans')->allFiles());
    }

    // ── Review ──────────────────────────────────────────────────────────────

    public function test_the_vp_approves_and_the_teacher_is_notified_in_app_only(): void
    {
        $plan = $this->createPlan();
        Notification::fake();

        $this->review($this->vp, $plan, LessonPlan::APPROVED)->assertSessionHas('status');

        $plan->refresh();
        $this->assertSame(LessonPlan::APPROVED, $plan->status);
        $this->assertNotNull($plan->reviewed_at);
        $review = LessonPlanReview::sole();
        $this->assertSame($plan->currentVersion->id, $review->lesson_plan_version_id);
        $this->assertNull($review->comment);

        $audit = $this->audits($plan, 'Reviewed lesson plan')->sole();
        $this->assertSame('vp_academic', $audit->role);
        $this->assertSame(LessonPlan::APPROVED, $audit->details['decision']);

        Notification::assertSentTo($this->teacher, LessonPlanNotice::class,
            fn ($n) => $n->kind === 'lesson_plan.approved' && $n->via($this->teacher) === ['database']);
    }

    public function test_needs_changes_requires_a_comment_and_also_emails_the_teacher(): void
    {
        $plan = $this->createPlan();
        Notification::fake();

        $this->review($this->vp, $plan, LessonPlan::NEEDS_CHANGES, '')->assertSessionHasErrors('comment');
        $this->assertSame(LessonPlan::SUBMITTED, $plan->fresh()->status);
        Notification::assertNothingSent();

        $this->review($this->vp, $plan, LessonPlan::NEEDS_CHANGES, 'Add the learning objectives for each lesson.')->assertSessionHas('status');

        $this->assertSame(LessonPlan::NEEDS_CHANGES, $plan->fresh()->status);
        Notification::assertSentTo($this->teacher, LessonPlanNotice::class, function ($n) {
            return $n->kind === 'lesson_plan.needs_changes'
                && $n->via($this->teacher) === ['database', 'mail']
                && str_contains($n->body, 'Add the learning objectives');
        });

        $this->actingAs($this->teacher)->get(route('lesson-plans.show', $plan))->assertSee('Add the learning objectives for each lesson.');
    }

    public function test_only_the_vp_can_review(): void
    {
        $plan = $this->createPlan();

        foreach ([$this->teacher, $this->otherTeacher, $this->principal, $this->admin, $this->registrar, $this->studentUser, $this->guardianUser] as $user) {
            $this->review($user, $plan, LessonPlan::APPROVED)->assertForbidden();
        }

        $this->assertSame(0, LessonPlanReview::count());
        $this->assertSame(LessonPlan::SUBMITTED, $plan->fresh()->status);
    }

    public function test_a_vp_who_also_teaches_cannot_review_their_own_plan(): void
    {
        $this->teacher->assignRole('vp_academic');
        $plan = $this->createPlan();

        $this->review($this->teacher, $plan, LessonPlan::APPROVED)->assertForbidden();
        $this->assertSame(0, LessonPlanReview::count());
    }

    public function test_a_review_is_refused_for_an_outdated_version_or_an_already_reviewed_plan(): void
    {
        $plan = $this->createPlan();
        $v1 = $plan->currentVersion->id;
        $this->actingAs($this->teacher)->post(route('lesson-plans.versions.store', $plan), ['file' => $this->pdf('v2.pdf')]);

        $this->review($this->vp, $plan, LessonPlan::APPROVED, versionId: $v1)->assertSessionHas('warning');
        $this->assertSame(0, LessonPlanReview::count());

        $this->review($this->vp, $plan, LessonPlan::APPROVED)->assertSessionHas('status');
        $this->review($this->vp, $plan, LessonPlan::NEEDS_CHANGES, 'Second thoughts')->assertSessionHas('warning');
        $this->assertSame(1, LessonPlanReview::count());
        $this->assertSame(LessonPlan::APPROVED, $plan->fresh()->status);
    }

    // ── New versions ────────────────────────────────────────────────────────

    public function test_a_new_version_keeps_the_old_one_returns_the_plan_to_review_and_notifies_the_vp(): void
    {
        $plan = $this->createPlan();
        $this->review($this->vp, $plan, LessonPlan::APPROVED);
        $firstPath = $plan->currentVersion->stored_path;
        Notification::fake();

        $this->actingAs($this->teacher)->post(route('lesson-plans.versions.store', $plan), ['file' => $this->office('docx', 'v2.docx')])
            ->assertSessionHas('status');

        $plan->refresh();
        $this->assertSame(2, $plan->current_version);
        $this->assertSame(LessonPlan::SUBMITTED, $plan->status, 'an approved plan returns to Awaiting review');
        $this->assertNull($plan->reviewed_at);
        $this->assertSame([1, 2], $plan->versions()->pluck('version')->sort()->values()->all());
        Storage::disk('lesson_plans')->assertExists($firstPath);

        $audit = $this->audits($plan, 'Uploaded lesson plan version')->sole();
        $this->assertSame('teacher', $audit->role);
        $this->assertSame(['from' => LessonPlan::APPROVED, 'to' => LessonPlan::SUBMITTED], $audit->details['status']);

        Notification::assertSentTo($this->vp, LessonPlanNotice::class, fn ($n) => $n->kind === 'lesson_plan.new_version' && $n->via($this->vp) === ['database']);

        // Each version stays downloadable, and the review history still points at version 1.
        foreach ($plan->versions as $version) {
            $this->actingAs($this->vp)->get(route('lesson-plans.versions.download', [$plan, $version]))->assertOk();
        }
        $this->assertSame(1, LessonPlanReview::sole()->version->version);
    }

    public function test_versions_are_capped_at_50_mb_in_total_per_plan(): void
    {
        $plan = $this->createPlan();
        // Pretend the plan already holds close to 50 MB.
        LessonPlanVersion::whereKey($plan->currentVersion->id)->update(['size_bytes' => LessonPlan::MAX_TOTAL_BYTES - 10]);

        $this->actingAs($this->teacher)->post(route('lesson-plans.versions.store', $plan), ['file' => $this->pdf('v2.pdf')])
            ->assertSessionHas('warning', fn ($m) => str_contains($m, '50 MB limit'));

        $this->assertSame(1, $plan->fresh()->current_version);
        $this->assertCount(1, Storage::disk('lesson_plans')->allFiles(), 'the refused file is not kept');
        $this->assertCount(0, $this->audits($plan, 'Uploaded lesson plan version'));
    }

    // ── Edit and delete ─────────────────────────────────────────────────────

    public function test_the_teacher_edits_the_title_and_note_with_an_audit_entry(): void
    {
        $plan = $this->createPlan();
        $this->review($this->vp, $plan, LessonPlan::APPROVED);

        $this->actingAs($this->teacher)->put(route('lesson-plans.update', $plan), ['title' => 'Week 1: Narrative writing', 'note' => ''])
            ->assertSessionHas('status');

        $plan->refresh();
        $this->assertSame('Week 1: Narrative writing', $plan->title);
        $this->assertNull($plan->note);
        $this->assertSame(LessonPlan::APPROVED, $plan->status, 'editing the title and note does not change the review');
        $audit = $this->audits($plan, 'Edited lesson plan')->sole();
        $this->assertSame('teacher', $audit->role);
        $this->assertSame(['from' => 'First draft.', 'to' => null], $audit->details['note']);

        // Saving without changes writes nothing.
        $this->actingAs($this->teacher)->put(route('lesson-plans.update', $plan), ['title' => 'Week 1: Narrative writing']);
        $this->assertCount(1, $this->audits($plan, 'Edited lesson plan'));
    }

    public function test_a_teacher_deletes_an_unreviewed_plan_with_its_rows_and_files(): void
    {
        $plan = $this->createPlan();
        $this->actingAs($this->teacher)->post(route('lesson-plans.versions.store', $plan), ['file' => $this->pdf('v2.pdf')]);
        $this->assertCount(2, Storage::disk('lesson_plans')->allFiles());
        $id = $plan->id;

        $this->actingAs($this->teacher)->delete(route('lesson-plans.destroy', $plan))->assertRedirect(route('lesson-plans.index'));

        $this->assertNull(LessonPlan::find($id));
        $this->assertSame(0, LessonPlanVersion::where('lesson_plan_id', $id)->count());
        $this->assertSame([], Storage::disk('lesson_plans')->allFiles());

        $audit = $this->audits($id, 'Deleted lesson plan')->sole();
        $this->assertSame('teacher', $audit->role);
        $this->assertCount(2, $audit->details['versions']);
    }

    public function test_a_teacher_cannot_delete_a_reviewed_plan(): void
    {
        $plan = $this->createPlan();
        $this->review($this->vp, $plan, LessonPlan::NEEDS_CHANGES, 'Please add assessment details.');

        $this->actingAs($this->teacher)->get(route('lesson-plans.show', $plan))->assertSee('has been reviewed, so it can’t be deleted', false);
        $this->actingAs($this->teacher)->delete(route('lesson-plans.destroy', $plan))->assertSessionHas('warning');

        $this->assertNotNull($plan->fresh());
        $this->assertCount(1, Storage::disk('lesson_plans')->allFiles());
        $this->assertCount(0, $this->audits($plan, 'Deleted lesson plan'), 'a refused action writes no audit entry');

        // A refusal is an expected answer, not an error: nothing is written to the error log.
        $logged = [];
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Log\Events\MessageLogged::class, function ($e) use (&$logged) {
            $logged[] = $e->level.': '.$e->message;
        });
        $this->actingAs($this->teacher)->delete(route('lesson-plans.destroy', $plan))->assertSessionHas('warning');
        $this->assertSame([], $logged);

        // The database refuses it too.
        $this->expectException(QueryException::class);
        DB::table('lesson_plans')->where('id', $plan->id)->delete();
    }

    public function test_the_vp_principal_and_admin_cannot_edit_or_delete_plans(): void
    {
        $plan = $this->createPlan();

        foreach ([$this->vp, $this->principal, $this->admin] as $user) {
            $this->actingAs($user)->put(route('lesson-plans.update', $plan), ['title' => 'Changed'])->assertForbidden();
            $this->actingAs($user)->delete(route('lesson-plans.destroy', $plan))->assertForbidden();
            $this->actingAs($user)->post(route('lesson-plans.versions.store', $plan), ['file' => $this->pdf()])->assertForbidden();
        }

        $this->assertNotNull($plan->fresh());
        $this->assertSame(1, $plan->fresh()->current_version);
    }

    // ── Audit and permissions ──────────────────────────────────────────────

    public function test_every_action_writes_its_audit_entry_with_the_actors_role(): void
    {
        $plan = $this->createPlan();
        $this->review($this->vp, $plan, LessonPlan::NEEDS_CHANGES, 'More detail please.');
        $this->actingAs($this->teacher)->post(route('lesson-plans.versions.store', $plan), ['file' => $this->pdf('v2.pdf')]);
        $this->actingAs($this->teacher)->put(route('lesson-plans.update', $plan), ['title' => 'Renamed']);
        $deletable = $this->createPlan($this->otherTeacher);
        $this->actingAs($this->otherTeacher)->delete(route('lesson-plans.destroy', $deletable));

        $rows = AuditLog::where('entity_type', 'LessonPlan')->orderBy('id')->get(['action', 'role', 'user_id', 'entity_id'])
            ->map(fn ($a) => [$a->action, $a->role, $a->user_id, $a->entity_id])->all();

        $this->assertSame([
            ['Created lesson plan', 'teacher', $this->teacher->id, $plan->id],
            ['Reviewed lesson plan', 'vp_academic', $this->vp->id, $plan->id],
            ['Uploaded lesson plan version', 'teacher', $this->teacher->id, $plan->id],
            ['Edited lesson plan', 'teacher', $this->teacher->id, $plan->id],
            ['Created lesson plan', 'teacher', $this->otherTeacher->id, $deletable->id],
            ['Deleted lesson plan', 'teacher', $this->otherTeacher->id, $deletable->id],
        ], $rows);
    }

    public function test_the_permission_migration_can_run_twice_without_duplicating_rows(): void
    {
        $migration = require database_path('migrations/2026_11_24_090300_seed_lesson_plan_permissions.php');
        $count = fn () => [
            Permission::where('name', 'like', 'lesson_plans.%')->count(),
            DB::table('role_has_permissions')->whereIn('permission_id', Permission::where('name', 'like', 'lesson_plans.%')->pluck('id'))->count(),
        ];

        $before = $count();
        $migration->up();
        $migration->up();

        $this->assertSame([3, 5], $before);
        $this->assertSame($before, $count());
    }
}
