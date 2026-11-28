<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A report card or attendance rule (§7.1, §8): the Admin edits the value; the Principal reads it.
 * Defaults awaiting the school carry their question number in `pending_question`.
 */
class ReportRule extends Model
{
    /**
     * key => [label, allowed values (value => description), null for a whole number 1-100, 'count' for
     * a whole number 0-10, or 'text' for a line of text]
     */
    public const RULES = [
        'min_attendance_pct' => ['Minimum attendance to sit examinations (%)', null],
        'excused_absences' => ['An excused absence (for the attendance mark, "Days absent" and the minimum attendance)', [
            'count_as_absent' => 'Counts as an absence',
            'leave_out' => 'Is left out: it neither counts as attended nor as absent',
        ]],
        'lates_per_absence' => ['Late arrivals that count as one absence (0 = late arrivals never count)', 'count'],
        'attendance_check_period' => ['The minimum attendance is checked over', [
            'year' => 'The school year so far',
            'term' => 'Each term on its own',
        ]],
        'max_subject_absence_pct' => ['Absence in a subject in a term above which a student is flagged (%)', null],
        'flag_basis' => ['Attendance flags fire', [
            'whole_period' => 'Once the limit is certainly broken for the whole term or year (school days from the dates and the calendar)',
            'so_far' => 'As soon as the days so far are over the limit',
        ]],
        'school_week' => ['The school meets', [
            'mon_fri' => 'Monday to Friday',
            'mon_sat' => 'Monday to Saturday',
            'sun_thu' => 'Sunday to Thursday',
        ]],
        'subject_attendance_source' => ['Attendance in each subject comes from', [
            'daily' => 'The daily attendance of the section (ISMS has no attendance per subject)',
        ]],
        'personality_shown' => ['The personality record prints', [
            'final_report' => 'On the final report only (traits are rated once a year, on the last term)',
            'latest_term' => 'The latest term with ratings (traits are rated every term)',
            'report_term' => 'The ratings of the report\'s own term',
        ]],
        'examination_centre' => ['Examination centre on the Statement of Marks', 'text'],
        'supw_incomplete_blocks_promotion' => ['SUPW Incomplete at the end of the year', [
            'no' => 'Is shown beside the promotion recommendation, and does not change it',
            'yes' => 'Makes the recommendation Retain',
        ]],
    ];

    /** Q14: traits are rated once a year and printed on the final report only. */
    public static function personalityOncePerYear(): bool
    {
        return static::value('personality_shown', 'final_report') === 'final_report';
    }

    protected $fillable = ['key', 'value', 'pending_question'];

    public static function value(string $key, string $default = ''): string
    {
        return (string) (static::where('key', $key)->value('value') ?? $default);
    }

    public static function minAttendancePct(): int
    {
        return (int) static::value('min_attendance_pct', '80');
    }

    /** Q15: the examination centre printed on the Statement of Marks. */
    public static function examinationCentre(): string
    {
        return static::value('examination_centre', 'YANGON ADVENTIST SEMINARY INTERNATIONAL SCHOOL');
    }

    public function pendingQuestions(): array
    {
        return $this->pending_question ? array_values(array_filter(array_map('trim', explode(',', $this->pending_question)))) : [];
    }
}
