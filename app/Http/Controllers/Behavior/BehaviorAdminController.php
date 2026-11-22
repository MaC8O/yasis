<?php

namespace App\Http\Controllers\Behavior;

use App\Http\Controllers\Controller;
use App\Http\Requests\Behavior\CategoryRequest;
use App\Http\Requests\Behavior\DecideCaseRequest;
use App\Http\Requests\Behavior\PointsRequest;
use App\Http\Requests\Behavior\ReasonRequest;
use App\Http\Requests\Behavior\UpdatePolicyRequest;
use App\Models\BehaviorCase;
use App\Models\BehaviorCategory;
use App\Models\BehaviorLedgerEntry;
use App\Models\StudentExitRequest;
use App\Services\AuditService;
use App\Services\Behavior\BehaviorPolicySettings;
use App\Services\Behavior\BehaviorService;
use App\Services\Behavior\BehaviorStanding;
use App\Services\Behavior\StudentExitRequestService;
use App\Support\BehaviorPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** VP/Principal behavior management: restore, cases, exit requests, policy, categories. */
class BehaviorAdminController extends Controller
{
    public function __construct(private BehaviorService $behavior) {}

    public function restore(PointsRequest $request, BehaviorLedgerEntry $entry)
    {
        $restoration = $this->behavior->restore($request->user(), $entry, (int) $request->validated('points'), trim($request->validated('reason')));

        return back()->with('status', "{$restoration->points_applied} point(s) restored. Score is now {$restoration->score_after}.");
    }

    // ---- Expulsion review cases

    public function cases(Request $request)
    {
        return view('behavior.cases.index', [
            'cases' => BehaviorCase::with(['student', 'decisions'])->orderByRaw("FIELD(status, 'Open', 'Decided')")->latest('id')->paginate(25),
        ]);
    }

    public function showCase(Request $request, BehaviorCase $case, BehaviorStanding $standing)
    {
        $case->load(['student.department', 'decisions.decider.user', 'flag', 'exitRequest.withdrawer.user', 'exitRequest.processor.user']);
        $yearId = $standing->activeYearId();

        return view('behavior.cases.show', [
            'case' => $case,
            'score' => $yearId ? $standing->score($case->student_id, $yearId) : null,
            'thresholds' => app(BehaviorPolicySettings::class)->thresholds(),
            'role' => $request->user()->hasRole('vp_academic') ? 'VP' : ($request->user()->hasRole('principal') ? 'Principal' : null),
        ]);
    }

    public function decide(DecideCaseRequest $request, BehaviorCase $case)
    {
        $case = $this->behavior->decideCase($request->user(), $case, $request->validated('decision'), $request->reason());

        return back()->with('status', $case->isOpen()
            ? "Decision recorded. Round {$case->round} is waiting for the ".($case->awaiting === 'VP' ? 'VP' : 'Principal').'.'
            : "Case decided: {$case->outcome}.".($case->outcome === 'Expel' ? ' A pending exit request was created for the Registrar; the student\'s status has not changed.' : ''));
    }

    public function withdrawExit(ReasonRequest $request, StudentExitRequest $exitRequest, StudentExitRequestService $exits)
    {
        $exits->withdraw($request->user(), $exitRequest, $request->reason());

        return back()->with('status', 'Exit request withdrawn. The VP and the Registrars have been notified.');
    }

    // ---- Behavior policy (thresholds)

    public function policy(Request $request, BehaviorPolicySettings $settings)
    {
        return view('behavior.policy', [
            'thresholds' => $settings->thresholds(),
            'canManage' => $request->user()->can(BehaviorPermissions::MANAGE),
        ]);
    }

    public function previewPolicy(UpdatePolicyRequest $request, BehaviorPolicySettings $settings)
    {
        return response()->json(['stages' => $settings->preview($request->thresholds())]);
    }

    public function updatePolicy(UpdatePolicyRequest $request, BehaviorPolicySettings $settings)
    {
        $settings->update($request->user(), $request->thresholds());

        return back()->with('status', 'Thresholds saved. Existing flags and notices stay as they are; students are checked against the new numbers from their next deduction or restoration.');
    }

    // ---- Incident categories

    public function categories()
    {
        return view('behavior.categories', ['categories' => BehaviorCategory::ordered()->get()]);
    }

    public function storeCategory(CategoryRequest $request, AuditService $audit)
    {
        DB::transaction(function () use ($request, $audit) {
            $category = BehaviorCategory::create([
                'name' => trim($request->validated('name')),
                'description' => $request->validated('description'),
                'sort_order' => (int) ($request->validated('sort_order') ?? (BehaviorCategory::max('sort_order') + 10)),
                'is_active' => true,
            ]);
            $audit->log($request->user(), 'Added behavior category', 'BehaviorCategory', $category->id, ['name' => $category->name]);
        });

        return back()->with('status', 'Category added.');
    }

    public function updateCategory(CategoryRequest $request, BehaviorCategory $category, AuditService $audit)
    {
        DB::transaction(function () use ($request, $category, $audit) {
            $category = BehaviorCategory::whereKey($category->id)->lockForUpdate()->firstOrFail();
            $category->fill([
                'name' => trim($request->validated('name')),
                'description' => $request->validated('description'),
                'sort_order' => (int) ($request->validated('sort_order') ?? $category->sort_order),
                'is_active' => $request->boolean('is_active'),
            ]);
            $changes = collect($category->getDirty())->mapWithKeys(fn ($to, $f) => [$f => ['from' => $category->getOriginal($f), 'to' => $to]])->all();
            $category->save();

            if ($changes !== []) {
                $audit->log($request->user(), 'Edited behavior category', 'BehaviorCategory', $category->id, $changes);
            }
        });

        return back()->with('status', 'Category saved.');
    }
}
