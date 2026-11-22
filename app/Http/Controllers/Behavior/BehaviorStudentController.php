<?php

namespace App\Http\Controllers\Behavior;

use App\Http\Controllers\Controller;
use App\Models\BehaviorCase;
use App\Models\BehaviorCategory;
use App\Models\BehaviorFlag;
use App\Models\BehaviorReport;
use App\Models\Student;
use App\Services\Behavior\BehaviorAccess;
use App\Services\Behavior\BehaviorStanding;
use App\Support\PerPage;
use Illuminate\Http\Request;

/** Staff behavior pages: the landing page (queue + student list) and a student's full record. */
class BehaviorStudentController extends Controller
{
    public function __construct(private BehaviorAccess $access, private BehaviorStanding $standing) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $yearId = $this->standing->activeYearId();

        $students = $this->access->visibleStudents($user)
            ->when($request->string('search')->trim()->value(), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('student_id_number', 'like', "%{$s}%")))
            ->when($request->string('stage')->value(), function ($q, $stage) use ($yearId) {
                $q->whereIn('id', BehaviorFlag::open()->where('academic_year_id', $yearId)->where('stage', $stage)->select('student_id'));
            })
            ->with('department')->orderBy('name')
            ->paginate(PerPage::resolve($request))->withQueryString();

        $vp = $user->hasRole('vp_academic');

        return view('behavior.index', [
            'year' => $this->standing->activeYear(),
            'students' => $students,
            'summaries' => $this->standing->summaries($students->getCollection()->pluck('id')),
            'filters' => $request->only(['search', 'stage']),
            // VP review queue: everything awaiting review, oldest first.
            'queue' => $vp ? BehaviorReport::with(['student', 'category', 'reporter.user'])->where('status', BehaviorReport::SUBMITTED)->oldest('created_at')->get() : collect(),
            'openFlagCounts' => $yearId ? BehaviorFlag::open()->where('academic_year_id', $yearId)->selectRaw('stage, COUNT(*) AS c')->groupBy('stage')->pluck('c', 'stage') : collect(),
            'openCases' => $this->access->seesAllStudents($user) ? BehaviorCase::with('student')->where('status', 'Open')->get() : collect(),
            // Teachers: their own reports, including students no longer in their scope.
            'myReports' => $user->hasRole('teacher')
                ? BehaviorReport::with(['student', 'category'])->where('reported_by', $user->id)->latest()->limit(50)->get() : collect(),
        ]);
    }

    public function show(Request $request, Student $student)
    {
        $user = $request->user();
        $this->access->assertCanViewStudent($user, $student);

        return view('behavior.students.show', $this->record($student) + [
            'student' => $student->load('department'),
            'categories' => BehaviorCategory::active()->ordered()->get(),
            'canReport' => $user->hasRole('teacher') && $this->access->teacherStudentIds($user->id)->contains($student->id),
            'isVp' => $user->hasRole('vp_academic'),
        ]);
    }

    /** Everything about one student's behavior, shared with the student and guardian pages. */
    public static function record(Student $student): array
    {
        $standing = app(BehaviorStanding::class);

        return [
            'summary' => $standing->summary($student),
            'reports' => $student->behaviorReports()
                ->with(['category', 'reporter.user', 'reviewer.user', 'voider.user', 'versions.category', 'deduction.restorations', 'academicYear'])
                ->latest('incident_date')->latest('id')->get(),
            'entries' => $student->behaviorLedgerEntries()->with(['actor.user', 'report.category', 'restorations'])->latest('id')->get(),
            'flags' => $student->behaviorFlags()->latest('opened_at')->latest('id')->get(),
            'cases' => $student->behaviorCases()->with(['decisions.decider.user', 'exitRequest'])->latest('id')->get(),
        ];
    }
}
