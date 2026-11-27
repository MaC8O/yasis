<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Term;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * The gradebook's Attendance category (sdp_doc/YASIS_Academic_and_Fee_Rules.md §4.1), worked out
 * from the section's attendance records for a term. Defaults awaiting the school (Q8):
 * - Present and Tardy count as attended; Absent counts against.
 * - Excused days are left out of both the attended days and the counted days.
 * - Only the days on which the student has their own record count (owner's decision): a section
 *   school day with no record for the student is left out, and reported so the teacher can fill it.
 *
 * Section school days are the dates with any attendance record for the section in the term.
 */
class AttendanceScoreService
{
    private const SCALE = 10;

    /**
     * @return array{school_days: int, students: array<int, array{present: int, tardy: int, absent: int, excused: int, counted: int, attended: int, pct: ?string, missing: int}>}
     */
    public function forSectionTerm(Section $section, Term $term): array
    {
        $records = AttendanceRecord::where('section_id', $section->id)->where('term_id', $term->id)
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
            $attended = $counts['present'] + $counts['tardy'];
            $counted = $attended + $counts['absent'];
            $students[$studentId] = $counts + [
                'counted' => $counted,
                'attended' => $attended,
                'pct' => $counted > 0 ? (string) BigDecimal::of($attended)->multipliedBy(100)->dividedBy($counted, self::SCALE, RoundingMode::HALF_UP) : null,
                'missing' => max(0, $schoolDays - $own->map($date)->unique()->count()),
            ];
        }

        return ['school_days' => $schoolDays, 'students' => $students];
    }

    /**
     * "Days absent" on the report card (§7.1): Absent plus Excused days by default, or Absent only,
     * by the report rule (Q8).
     */
    public static function daysAbsent(?array $row): int
    {
        if (! $row) {
            return 0;
        }

        return $row['absent'] + (\App\Models\ReportRule::value('days_absent_counts', 'absent_and_excused') === 'absent_only' ? 0 : $row['excused']);
    }

    /** "18 of 20 days (2 excused not counted)" for a student, or a note that nothing is recorded. */
    public static function describe(?array $row): string
    {
        if (! $row || $row['counted'] === 0) {
            return $row && $row['excused'] > 0 ? "No counted days ({$row['excused']} excused)" : 'No attendance recorded';
        }

        return "{$row['attended']} of {$row['counted']} days".($row['excused'] ? " ({$row['excused']} excused not counted)" : '');
    }
}
