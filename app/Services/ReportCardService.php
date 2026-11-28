<?php

namespace App\Services;

use App\Models\GradeLevel;
use App\Models\PersonalityRating;
use App\Models\PromotionBatchItem;
use App\Models\ReportAcknowledgement;
use App\Models\ReportRule;
use App\Models\Student;
use App\Models\StudentResult;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Services\Results\StudentResultData;

/**
 * The student's progress report (sdp_doc/YASIS_Academic_and_Fee_Rules.md §7.1) for a term, as
 * the school's four cards lay it out. Every figure comes from ResultService: frozen for a locked
 * term, live and provisional for an open one. Columns First, Second and Third are those terms; the
 * Final column is the whole year (the school's answer to Q3): each subject's year mark, the year's
 * result, position and class size, and the days absent of the four terms together. It is filled on
 * the last term's report only, from the year's freeze when every term is locked. Terms after the
 * report's term are blank.
 */
class ReportCardService
{
    public function __construct(private ResultService $results, private AttendanceScoreService $attendance) {}

    /** The card's data for a student and term, or null when the student has no class that school year. */
    public function build(Student $student, Term $term): ?array
    {
        $report = $this->results->studentTerm($student, $term);
        $section = $report->section;
        if (! $section) {
            return null;
        }
        $section->loadMissing(['gradeLevel.gradingScheme', 'homeroomTeacher.user', 'academicYear']);
        $level = $section->gradeLevel;
        $layout = $level?->report_card_layout ?? 'g5_9';
        [$layoutTitle, $accent] = GradeLevel::REPORT_CARD_LAYOUTS[$layout] ?? GradeLevel::REPORT_CARD_LAYOUTS['g5_9'];
        $terms = Term::where('academic_year_id', $term->academic_year_id)->orderBy('sequence')->get();

        // One column per term up to the report's term; the last is the year (Final).
        $columns = [];
        $lastSequence = $terms->max('sequence');
        foreach ($terms as $t) {
            if ($t->sequence > $term->sequence) {
                $columns[$t->sequence] = ['term' => $t, 'shown' => false, 'frozen' => false, 'result' => null, 'total' => null, 'daysAbsent' => null, 'year' => false];

                continue;
            }
            if ($t->sequence === $lastSequence) {
                $year = $this->results->sectionYear($section);
                $days = collect($columns)->pluck('daysAbsent')->push($this->daysAbsent($student, $section, $t,
                    $this->results->sectionTerm($section, $t)->freeze?->id));
                $columns[$t->sequence] = [
                    'term' => $t, 'shown' => true, 'frozen' => $year->frozen, 'result' => $year->for($student),
                    'total' => $year->students->count(), 'year' => true,
                    'daysAbsent' => $days->containsStrict(null) ? null : (int) $days->sum(),
                ];

                continue;
            }
            $sectionResults = $this->results->sectionTerm($section, $t);
            $result = $sectionResults->for($student);
            $columns[$t->sequence] = [
                'term' => $t,
                'shown' => true,
                'frozen' => $sectionResults->frozen,
                'result' => $result,
                'total' => $sectionResults->students->count(),
                'daysAbsent' => $this->daysAbsent($student, $section, $t, $sectionResults->freeze?->id),
                'year' => false,
            ];
        }

        $offerings = $level
            ? SubjectOffering::with('subject')->where('grade_level_id', $level->id)->active()->ordered()->get()
            : collect();
        $hasHours = $layout === 'g11_12';
        $subjects = $offerings->map(fn (SubjectOffering $o) => [
            'label' => $o->report_label,
            'hours' => $hasHours && $o->credit_hours !== null ? rtrim(rtrim((string) $o->credit_hours, '0'), '.') : '',
            'cells' => collect($columns)->map(fn ($c) => $c['shown'] ? $this->cell($c['result'], $o->subject->code) : '')->all(),
        ])->all();

        $basis = $level?->gradingScheme?->result_basis;

        return [
            'student' => $student,
            'section' => $section,
            'term' => $term,
            'layout' => $layout,
            'layoutTitle' => $layoutTitle,
            'accent' => $accent,
            'gradeName' => $level?->name ?? $section->name,
            'schoolYear' => $section->academicYear?->year_label,
            'classTeacher' => $section->homeroomTeacher?->user?->name,
            'columns' => $columns,
            'hasHours' => $hasHours,
            'subjects' => $subjects,
            // Q12: the card's "G.P.A" row holds the average for KG1-10 (Grade 10's average of its counted
            // subjects, though its point total decides the class) and the GPA for 11-12. Grade 10's card
            // keeps its printed label "G.P.A".
            'gpaLabel' => ['average' => 'G.P.A (AVERAGE %)', 'point_total' => 'G.P.A', 'gpa' => 'G.P.A'][$basis] ?? 'G.P.A',
            'gpaRow' => collect($columns)->map(fn ($c) => $c['shown'] && $c['result']?->isComplete()
                ? ($basis === 'point_total' ? (string) $c['result']->average : $c['result']->value) : '')->all(),
            'positionRow' => collect($columns)->map(fn ($c) => $c['shown'] && $c['result']?->position ? (string) $c['result']->position : '')->all(),
            'totalRow' => collect($columns)->map(fn ($c) => $c['shown'] ? (string) $c['total'] : '')->all(),
            'daysAbsentRow' => collect($columns)->map(fn ($c) => $c['shown'] && $c['daysAbsent'] !== null ? (string) $c['daysAbsent'] : '')->all(),
            'notes' => $this->notes($columns),
            'provisional' => collect($columns)->contains(fn ($c) => $c['shown'] && ! $c['frozen']),
            'personality' => $this->personality($student, $term, $terms),
            'acknowledgements' => $this->acknowledgements($student, $terms, $term),
            'placement' => $term->sequence === $terms->max('sequence') ? $this->placement($student, $section->id) : null,
        ];
    }

    /** A subject's cell: the mark as printed, PASS/FAIL or SUPW's word, "-" for another track's subject. */
    private function cell(?StudentResultData $result, string $code): string
    {
        $subject = $result?->subject($code);
        if (! $subject) {
            return '';
        }
        if (! $subject->taken) {
            return '-';
        }
        if (in_array($subject->gradingMode, ['pass_fail', 'completion'], true)) {
            return $subject->bandLabel ?? '';
        }

        return $subject->roundedMark ?? '';
    }

    private function daysAbsent(Student $student, $section, Term $term, ?int $freezeId): ?int
    {
        if ($freezeId) {
            $stored = StudentResult::where('result_freeze_id', $freezeId)->where('student_id', $student->id)
                ->where('section_id', $section->id)->current()->value('days_absent');
            if ($stored !== null) {
                return (int) $stored;
            }
        }
        $row = $this->attendance->forSectionTerm($section, $term)['students'][$student->id] ?? null;

        return $row ? AttendanceScoreService::daysAbsent($row) : null;
    }

    /** Why a shown column has no G.P.A, for the screen version (e.g. Grade 10 waiting on Q6). */
    private function notes(array $columns): array
    {
        $notes = [];
        foreach ($columns as $c) {
            $name = $c['year'] ? 'Final (the year)' : $c['term']->name;
            if ($c['shown'] && $c['result'] && ! $c['result']->isComplete()) {
                $notes[] = $name.': '.implode(' ', $c['result']->reasons);
            } elseif ($c['shown'] && $c['result']?->classPending) {
                $notes[] = $name.': '.$c['result']->resultLabel.' '.$c['result']->value.', '.$c['result']->classText().'.';
            }
        }

        return array_values(array_unique($notes));
    }

    /**
     * The ratings printed. Traits are rated once a year, on the last term, and printed on the final
     * report only (the school's answer to Q14); earlier cards leave the block blank. The rule can
     * still be set to the latest rated term or the report's own term.
     */
    private function personality(Student $student, Term $term, $terms): array
    {
        $candidates = match (ReportRule::value('personality_shown', 'final_report')) {
            'final_report' => $term->sequence === $terms->max('sequence') ? collect([$term]) : collect(),
            'report_term' => collect([$term]),
            default => $terms->filter(fn ($t) => $t->sequence <= $term->sequence)->sortByDesc('sequence'),
        };

        foreach ($candidates as $t) {
            $ratings = PersonalityRating::where('student_id', $student->id)->where('term_id', $t->id)->pluck('rating', 'trait');
            if ($ratings->isNotEmpty()) {
                return ['term' => $t, 'ratings' => $ratings->all()];
            }
        }

        return ['term' => null, 'ratings' => []];
    }

    /** For each term up to the report's: the guardian, class teacher and Principal signatures (name and date). */
    private function acknowledgements(Student $student, $terms, Term $term): array
    {
        $rows = ReportAcknowledgement::with('user')->current()
            ->whereIn('term_id', $terms->pluck('id'))
            ->where(fn ($q) => $q->where('student_id', $student->id)->orWhere(fn ($q2) => $q2->whereNull('student_id')->where('role', ReportAcknowledgement::PRINCIPAL)))
            ->orderBy('acknowledged_at')->get();

        $signatures = [];
        foreach ($terms as $t) {
            foreach ([ReportAcknowledgement::GUARDIAN, ReportAcknowledgement::CLASS_TEACHER, ReportAcknowledgement::PRINCIPAL] as $role) {
                $row = $t->sequence <= $term->sequence ? $rows->where('term_id', $t->id)->where('role', $role)->last() : null;
                $signatures[$t->sequence][$role] = $row ? ['name' => $row->user?->name, 'date' => $row->acknowledged_at->format('j M Y')] : null;
            }
        }

        return $signatures;
    }

    /** The Final report's class placement, from the applied promotion batch for the student's section. */
    private function placement(Student $student, int $sectionId): ?array
    {
        $item = PromotionBatchItem::with(['toSection.gradeLevel', 'toSection.academicYear'])
            ->where('student_id', $student->id)->where('action', 'Promote')->whereNotNull('to_section_id')
            ->whereHas('batch', fn ($q) => $q->where('from_section_id', $sectionId)->where('status', 'Applied'))
            ->latest('id')->first();
        if (! $item?->toSection) {
            return null;
        }
        $first = Term::where('academic_year_id', $item->toSection->academic_year_id)->orderBy('sequence')->first();

        return [
            'grade' => $item->toSection->gradeLevel?->name ?? $item->toSection->name,
            'year' => $item->toSection->academicYear?->year_label,
            'beginning' => $first?->start_date?->format('j M Y'),
        ];
    }
}
