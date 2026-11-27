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
 * term, live and provisional for an open one. Columns First, Second, Third and Final are the four
 * terms (Final is the fourth term's own result, §2/Q3); terms after the report's term are blank.
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

        // One column per term up to the report's term.
        $columns = [];
        foreach ($terms as $t) {
            if ($t->sequence > $term->sequence) {
                $columns[$t->sequence] = ['term' => $t, 'shown' => false, 'frozen' => false, 'result' => null, 'total' => null, 'daysAbsent' => null];

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
            // Q12: what the card's "G.P.A" row holds at this grade.
            'gpaLabel' => ['average' => 'G.P.A (AVERAGE %)', 'point_total' => 'G.P.A (POINT TOTAL)', 'gpa' => 'G.P.A'][$basis] ?? 'G.P.A',
            'gpaRow' => collect($columns)->map(fn ($c) => $c['shown'] && $c['result']?->isComplete() ? $c['result']->value : '')->all(),
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

    /** A subject's cell: the mark as printed, PASS/FAIL for a pass/fail subject, "-" for an option not taken. */
    private function cell(?StudentResultData $result, string $code): string
    {
        $subject = $result?->subject($code);
        if (! $subject) {
            return '';
        }
        if (! $subject->taken) {
            return '-';
        }
        if ($subject->gradingMode === 'pass_fail') {
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
            if ($c['shown'] && $c['result'] && ! $c['result']->isComplete()) {
                $notes[] = $c['term']->name.': '.implode(' ', $c['result']->reasons);
            }
        }

        return array_values(array_unique($notes));
    }

    /** The ratings printed: the latest rated term up to the report's (Q14 default) or the report's own term. */
    private function personality(Student $student, Term $term, $terms): array
    {
        $candidates = ReportRule::value('personality_shown', 'latest_term') === 'report_term'
            ? collect([$term])
            : $terms->filter(fn ($t) => $t->sequence <= $term->sequence)->sortByDesc('sequence');

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
