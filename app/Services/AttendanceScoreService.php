<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\ReportRule;
use App\Models\Section;
use App\Models\Term;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Attendance worked out from the section's daily records (sdp_doc/YASIS_Academic_and_Fee_Rules.md
 * §4.1 and §8), with the school's rules (Q8, Q16) as report rules the Admin edits:
 * - Present and Tardy count as attended; Absent counts against.
 * - `excused_absences`: an Excused day counts as an absence (the default, FQ1) or is left out of
 *   both the attended and the counted days. The one rule covers the 10% mark, Days absent and the
 *   80% check, so they can't disagree.
 * - `lates_per_absence`: every N late arrivals (default 3) count as one absent day: only whole
 *   multiples, so 5 lates are one absence. 0 turns it off. Lates are converted per term for the
 *   term's figures, and on the year's total for the year's 80% check.
 * - Only the days on which the student has their own record count (owner's decision, phase 5); a
 *   section school day with no record for the student is reported so the teacher can fill it.
 *
 * Per-subject attendance comes from the section's daily attendance (`subject_attendance_source` =
 * daily, FQ2), so a student's subject absence is the same in every subject.
 *
 * A freeze stores the rules it used (`result_freezes.attendance_rules`); pass them as $rules to
 * work a frozen term out the same way.
 */
class AttendanceScoreService
{
    private const SCALE = 10;

    /**
     * The rules in force now.
     *
     * @return array{excused: string, lates_per_absence: int, excused_in_days_absent: bool}
     */
    public static function rules(): array
    {
        $excused = ReportRule::value('excused_absences', 'count_as_absent');

        return [
            'excused' => $excused,
            'lates_per_absence' => max(0, (int) ReportRule::value('lates_per_absence', '3')),
            'excused_in_days_absent' => $excused === 'count_as_absent',
        ];
    }

    /**
     * @return array{school_days: int, rules: array, students: array<int, array{present: int, tardy: int, absent: int, excused: int, late_absences: int, counted: int, attended: int, days_absent: int, pct: ?string, missing: int}>}
     */
    public function forSectionTerm(Section $section, Term $term, ?array $rules = null): array
    {
        return $this->forSectionTerms($section, [$term->id], $rules);
    }

    /**
     * The school year to date up to and including $term, with lates converted on the year's total:
     * for the year's 80% check (Q16).
     */
    public function forSectionYearTo(Section $section, Term $term, ?array $rules = null): array
    {
        $termIds = Term::where('academic_year_id', $term->academic_year_id)->where('sequence', '<=', $term->sequence)->pluck('id')->all();

        return $this->forSectionTerms($section, $termIds, $rules);
    }

    /** @param  list<int>  $termIds */
    private function forSectionTerms(Section $section, array $termIds, ?array $rules): array
    {
        $rules = ($rules ?? []) + self::rules();
        $countExcused = $rules['excused'] === 'count_as_absent';
        $per = (int) $rules['lates_per_absence'];

        $records = AttendanceRecord::where('section_id', $section->id)->whereIn('term_id', $termIds)
            ->get(['student_id', 'attendance_date', 'status']);
        $date = fn ($r) => substr((string) $r->getRawOriginal('attendance_date'), 0, 10);
        $schoolDays = $records->map($date)->unique()->count();

        $roster = Enrollment::where('section_id', $section->id)->where('status', '!=', 'Withdrawn')->pluck('student_id')
            ->merge($records->pluck('student_id'))->unique();

        $students = [];
        foreach ($roster as $studentId) {
            $own = $records->where('student_id', $studentId);
            $counts = ['present' => 0, 'tardy' => 0, 'absent' => 0, 'excused' => 0];
            foreach ($own as $record) {
                $counts[strtolower($record->status)]++;
            }
            $lateAbsences = $per > 0 ? intdiv($counts['tardy'], $per) : 0;
            $excusedAbsent = $countExcused ? $counts['excused'] : 0;
            $attended = $counts['present'] + $counts['tardy'] - $lateAbsences;
            $counted = $counts['present'] + $counts['tardy'] + $counts['absent'] + $excusedAbsent;
            $students[$studentId] = $counts + [
                'late_absences' => $lateAbsences,
                'counted' => $counted,
                'attended' => $attended,
                'days_absent' => $counts['absent'] + $lateAbsences + (($rules['excused_in_days_absent'] ?? $countExcused) ? $counts['excused'] : 0),
                'pct' => $counted > 0 ? (string) BigDecimal::of($attended)->multipliedBy(100)->dividedBy($counted, self::SCALE, RoundingMode::HALF_UP) : null,
                'missing' => max(0, $schoolDays - $own->map($date)->unique()->count()),
            ];
        }

        return ['school_days' => $schoolDays, 'rules' => $rules, 'students' => $students];
    }

    /** "Days absent" on the report card (§7.1): absent days, Excused days by the rule, and absences from late arrivals. */
    public static function daysAbsent(?array $row): int
    {
        return $row ? (int) $row['days_absent'] : 0;
    }

    /** "17 of 20 days · 4 late = 1 absence · 2 excused counted as absent", or a note that nothing is recorded. */
    public static function describe(?array $row, ?array $rules = null): string
    {
        $rules ??= self::rules();
        if (! $row || $row['counted'] === 0) {
            return $row && $row['excused'] > 0 ? "No counted days ({$row['excused']} excused)" : 'No attendance recorded';
        }

        $parts = ["{$row['attended']} of {$row['counted']} days"];
        if ($row['late_absences'] > 0) {
            $parts[] = "{$row['tardy']} late = {$row['late_absences']} ".($row['late_absences'] === 1 ? 'absence' : 'absences');
        }
        if ($row['excused'] > 0) {
            $parts[] = $row['excused'].' excused '.($rules['excused'] === 'count_as_absent' ? 'counted as absent' : 'not counted');
        }

        return implode(' · ', $parts);
    }
}
