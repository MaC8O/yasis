<?php

namespace App\Http\Controllers;

use App\Http\Requests\Teacher\SavePersonalityRatingsRequest;
use App\Models\AcademicYear;
use App\Models\PersonalityRating;
use App\Models\ReportAcknowledgement;
use App\Models\ReportRule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use App\Services\AttendanceScoreService;
use App\Services\ReportCardActionsService;
use App\Services\ReportCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Report cards for staff (§7.1): the homeroom teacher (their own sections: ratings, confirmation,
 * eligibility flags) and the Registrar, VP and Principal (every section, read-only). A card for an
 * open term is provisional. The attendance-eligibility list (§8) is for the VP and Principal.
 */
class ReportCardController extends Controller
{
    public function __construct(private ReportCardService $cards, private ReportCardActionsService $actions) {}

    /** Sections this person may see report cards for in the active year. */
    private function sections(Request $request)
    {
        $year = AcademicYear::where('is_active', true)->first();
        $query = Section::where('academic_year_id', $year?->id)->whereNull('archived_at')->orderByGradeLevel()->orderBy('name');
        if ($this->isTeacherOnly($request)) {
            $query->where('homeroom_teacher_id', $request->user()->staffProfile?->id ?? 0);
        }

        return $query->get();
    }

    private function isTeacherOnly(Request $request): bool
    {
        return ! $request->user()->hasAnyRole(['registrar', 'vp_academic', 'principal']);
    }

    /** Only the section's homeroom teacher rates and confirms. */
    private function assertHomeroom(Request $request, Section $section): void
    {
        abort_unless($section->homeroom_teacher_id && $section->homeroom_teacher_id === $request->user()->staffProfile?->id, 403,
            'Only the class teacher of this section can do this.');
    }

    private function assertCanSee(Request $request, Section $section): void
    {
        abort_unless($this->sections($request)->contains('id', $section->id), 403, 'You can only see report cards for your own class.');
    }

    public function index(Request $request, AttendanceScoreService $attendance)
    {
        $sections = $this->sections($request);
        abort_if($sections->isEmpty(), 403, 'You are not the class teacher of any section this year.');
        $section = $sections->firstWhere('id', $request->integer('section')) ?? $sections->first();
        $terms = Term::where('academic_year_id', $section->academic_year_id)->orderBy('sequence')->get();
        $term = $terms->firstWhere('id', $request->integer('term'))
            ?? $terms->first(fn ($t) => $t->start_date->lte(today()) && $t->end_date->gte(today())) ?? $terms->last();

        $roster = Student::whereIn('id', $this->actions->roster($section))->orderBy('name')->get();
        $att = $attendance->forSectionTerm($section, $term)['students'];
        $minimum = ReportRule::minAttendancePct();
        $acks = ReportAcknowledgement::current()->where('term_id', $term->id)->whereIn('student_id', $roster->pluck('id'))->get();

        return view('report-cards.index', [
            'sections' => $sections,
            'section' => $section,
            'terms' => $terms,
            'term' => $term,
            'roster' => $roster,
            'attendance' => $att,
            'minimum' => $minimum,
            'below' => collect($this->actions->belowMinimumAttendance($term, collect([$section])))->keyBy('student_id'),
            'confirmed' => $acks->where('role', ReportAcknowledgement::CLASS_TEACHER)->pluck('student_id')->all(),
            'guardianAcks' => $acks->where('role', ReportAcknowledgement::GUARDIAN)->pluck('student_id')->all(),
            'ratings' => PersonalityRating::where('term_id', $term->id)->whereIn('student_id', $roster->pluck('id'))->get()->groupBy('student_id')
                ->map(fn ($rows) => $rows->pluck('rating', 'trait')),
            'isHomeroom' => $section->homeroom_teacher_id === $request->user()->staffProfile?->id,
        ]);
    }

    public function show(Request $request, Section $section, Term $term, Student $student)
    {
        $this->assertCanSee($request, $section);
        $card = $this->card($student, $term, $section);

        return view('report-cards.show', ['card' => $card, 'pdfUrl' => route('report-cards.pdf', [$section, $term, $student]),
            'backUrl' => route('report-cards.index', ['section' => $section->id, 'term' => $term->id])]);
    }

    public function pdf(Request $request, Section $section, Term $term, Student $student)
    {
        $this->assertCanSee($request, $section);

        return self::stream($this->card($student, $term, $section));
    }

    public function savePersonality(SavePersonalityRatingsRequest $request, Section $section, Term $term)
    {
        $this->assertHomeroom($request, $section);
        abort_unless($term->academic_year_id === $section->academic_year_id, 404);
        $data = $request->validated();
        $changed = $this->actions->savePersonality($section, $term, $data['ratings'] ?? [], $request->user());

        return back()->with('status', $changed ? "Saved {$changed} ".str('rating')->plural($changed).'.' : 'No ratings changed.');
    }

    public function confirm(Request $request, Section $section, Term $term)
    {
        $this->assertHomeroom($request, $section);
        abort_unless($term->academic_year_id === $section->academic_year_id, 404);
        $count = $this->actions->confirm($section, $term, $request->user());

        return back()->with('status', "Confirmed {$count} ".str('report')->plural($count)." for {$term->name}.");
    }

    /** VP and Principal: students below the minimum attendance this term (§8). Flagged only. */
    public function attendance(Request $request)
    {
        $year = AcademicYear::where('is_active', true)->first();
        $terms = $year?->terms()->orderBy('sequence')->get() ?? collect();
        $term = $terms->firstWhere('id', $request->integer('term'))
            ?? $terms->first(fn ($t) => $t->start_date->lte(today()) && $t->end_date->gte(today())) ?? $terms->last();

        return view('report-cards.attendance', [
            'terms' => $terms,
            'term' => $term,
            'minimum' => ReportRule::minAttendancePct(),
            'below' => $term ? $this->actions->belowMinimumAttendance($term) : [],
        ]);
    }

    private function card(Student $student, Term $term, Section $section): array
    {
        $card = $this->cards->build($student, $term);
        abort_if(! $card || $card['section']->id !== $section->id, 404, 'This student has no report card in this section for that term.');

        return $card;
    }

    /** The booklet PDF: A4 landscape, two columns per page, two pages. */
    public static function stream(array $card)
    {
        $name = 'report-card-'.$card['student']->student_id_number.'-'.str($card['term']->name)->slug().'.pdf';

        return Pdf::loadView('report-cards.pdf', ['card' => $card])->setPaper('a4', 'landscape')->stream($name);
    }

    /** Student or guardian access: released terms only. */
    public static function assertReleased(Term $term): void
    {
        abort_unless($term->results_released, 403, "This term's results have not been released yet.");
    }
}
