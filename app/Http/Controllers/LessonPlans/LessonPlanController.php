<?php

namespace App\Http\Controllers\LessonPlans;

use App\Http\Controllers\Controller;
use App\Http\Requests\LessonPlans\LessonPlanRequest;
use App\Models\Department;
use App\Models\LessonPlan;
use App\Models\LessonPlanVersion;
use App\Models\Section;
use App\Models\Term;
use App\Services\LessonPlans\LessonPlanAccess;
use App\Services\LessonPlans\LessonPlanService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Lesson plans for Teacher, VP, Principal and Admin. The route group admits only those roles and
 * each write route needs its permission; LessonPlanAccess decides every plan and file on every
 * request, and LessonPlanService checks again before changing anything.
 */
class LessonPlanController extends Controller
{
    public function __construct(private LessonPlanAccess $access, private LessonPlanService $plans) {}

    /** Teacher: their plans + upload form. VP/Principal/Admin: all plans, filterable. */
    public function index(Request $request)
    {
        $user = $request->user();
        $canSubmit = $this->access->canSubmit($user);
        $canViewAll = $this->access->canViewAll($user);
        if (! $canSubmit && ! $canViewAll) {
            throw new AuthorizationException('You do not have access to lesson plans.');
        }

        $data = ['canSubmit' => $canSubmit, 'canViewAll' => $canViewAll, 'year' => $this->access->activeYear()];

        if ($canSubmit) {
            $assignments = $this->access->currentAssignments($user);
            $current = $assignments->map(fn ($a) => $a->section_id.'-'.$a->subject_id)->flip();
            $mine = LessonPlan::with(['section.academicYear', 'subject', 'term', 'currentVersion'])
                ->where('teacher_id', $user->id)->latest('updated_at')->get();

            $data += [
                'assignments' => $assignments,
                'terms' => $data['year'] ? Term::where('academic_year_id', $data['year']->id)->orderBy('sequence')->get() : collect(),
                'myPlansBySubject' => $mine->groupBy(fn ($p) => $p->subject?->name ?? 'Other')->sortKeys(),
                'changeable' => $mine->filter(fn ($p) => $current->has($p->section_id.'-'.$p->subject_id))->pluck('id')->flip(),
            ];
        }

        if ($canViewAll) {
            $filters = $request->validate([
                'department' => ['nullable', 'integer'],
                'section' => ['nullable', 'integer'],
                'status' => ['nullable', 'in:'.implode(',', LessonPlan::STATUSES)],
                'term' => ['nullable', 'integer'],
            ]);

            $query = LessonPlan::with(['teacher.user', 'section.department', 'subject', 'term'])
                ->when($filters['department'] ?? null, fn ($q, $id) => $q->whereHas('section', fn ($s) => $s->where('department_id', $id)))
                ->when($filters['section'] ?? null, fn ($q, $id) => $q->where('section_id', $id))
                ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                ->when($filters['term'] ?? null, fn ($q, $id) => $q->where('term_id', $id))
                ->latest('submitted_at');

            $data += [
                'filters' => $filters,
                'allPlans' => $query->paginate(20)->withQueryString(),
                'departments' => Department::academic()->orderBy('name')->get(),
                'sections' => Section::with('academicYear')->whereIn('id', LessonPlan::select('section_id'))
                    ->orWhere('academic_year_id', $data['year']?->id)->orderBy('name')->get(),
                'filterTerms' => Term::with('academicYear')->orderByDesc('academic_year_id')->orderBy('sequence')->get(),
                'queueCount' => LessonPlan::where('status', LessonPlan::SUBMITTED)->count(),
            ];
        }

        return view('lesson-plans.index', $data);
    }

    /** Plans awaiting review, oldest first. The VP reviews; Principal and Admin read. */
    public function queue(Request $request)
    {
        return view('lesson-plans.queue', [
            'plans' => LessonPlan::with(['teacher.user', 'section.department', 'subject', 'term', 'currentVersion'])
                ->where('status', LessonPlan::SUBMITTED)->orderBy('submitted_at')->orderBy('id')->get(),
            'canReview' => $request->user()->can(\App\Support\LessonPlanPermissions::REVIEW),
        ]);
    }

    public function show(Request $request, LessonPlan $lessonPlan)
    {
        $user = $request->user();
        $this->access->assertCanView($user, $lessonPlan);

        $lessonPlan->load(['teacher.user', 'section.department', 'section.academicYear', 'subject', 'term', 'versions.uploader.user', 'reviews.reviewer.user', 'reviews.version']);
        $canChange = $this->access->canChange($user, $lessonPlan);
        $usedBytes = (int) $lessonPlan->versions->sum('size_bytes');

        return view('lesson-plans.show', [
            'plan' => $lessonPlan,
            'isOwner' => (int) $lessonPlan->teacher_id === (int) $user->id,
            'canChange' => $canChange,
            'canDelete' => $canChange && $lessonPlan->reviews->isEmpty(),
            'canReview' => $this->access->canReview($user, $lessonPlan) && $lessonPlan->status === LessonPlan::SUBMITTED,
            'currentVersion' => $lessonPlan->versions->firstWhere('version', $lessonPlan->current_version),
            'usedBytes' => $usedBytes,
            'atLimit' => $usedBytes >= LessonPlan::MAX_TOTAL_BYTES,
        ]);
    }

    public function store(LessonPlanRequest $request)
    {
        $plan = $this->plans->create(
            $request->user(),
            (int) $request->validated('teaching_assignment_id'),
            (int) $request->validated('term_id'),
            trim($request->validated('title')),
            $request->note(),
            $request->file('file'),
        );

        return redirect()->route('lesson-plans.show', $plan)
            ->with('status', 'Lesson plan uploaded. It is waiting for the Vice Principal’s review.');
    }

    public function update(LessonPlanRequest $request, LessonPlan $lessonPlan)
    {
        $this->plans->update($request->user(), $lessonPlan, trim($request->validated('title')), $request->note());

        return redirect()->route('lesson-plans.show', $lessonPlan)->with('status', 'Title and note saved.');
    }

    public function destroy(Request $request, LessonPlan $lessonPlan)
    {
        $title = $lessonPlan->title;
        $this->plans->delete($request->user(), $lessonPlan);

        return redirect()->route('lesson-plans.index')->with('status', "“{$title}” and its files were deleted.");
    }

    public function storeVersion(LessonPlanRequest $request, LessonPlan $lessonPlan)
    {
        $version = $this->plans->addVersion($request->user(), $lessonPlan, $request->file('file'));

        return redirect()->route('lesson-plans.show', $lessonPlan)
            ->with('status', "Version {$version->version} uploaded. The plan is waiting for review again.");
    }

    public function review(LessonPlanRequest $request, LessonPlan $lessonPlan)
    {
        $review = $this->plans->review(
            $request->user(), $lessonPlan, $request->validated('decision'), $request->validated('comment'), (int) $request->validated('version_id'),
        );

        $message = $review->decision === LessonPlan::APPROVED
            ? 'Plan approved. The teacher has been notified.'
            : 'Sent back for changes. The teacher has been notified in the portal and by e-mail.';

        return redirect()->route('lesson-plans.queue')->with('status', $message);
    }

    /** The only way to read a file. Permission is checked on every request; always a download. */
    public function download(Request $request, LessonPlan $lessonPlan, LessonPlanVersion $version)
    {
        $this->access->assertCanView($request->user(), $lessonPlan);

        $disk = Storage::disk(LessonPlanService::DISK);
        abort_unless($disk->exists($version->stored_path), 404, 'The file for this version is missing.');

        return $disk->download($version->stored_path, $version->original_name, [
            'Content-Type' => $version->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
