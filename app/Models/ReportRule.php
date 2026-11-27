<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A report card or attendance rule (§7.1, §8): the Admin edits the value; the Principal reads it.
 * Defaults awaiting the school carry their question number in `pending_question`.
 */
class ReportRule extends Model
{
    /** key => [label, allowed values (value => description), null for a whole number 1-100, or 'text' for a line of text] */
    public const RULES = [
        'min_attendance_pct' => ['Minimum attendance to sit examinations (%)', null],
        'days_absent_counts' => ['"Days absent" on the report card counts', [
            'absent_and_excused' => 'Absent and Excused days',
            'absent_only' => 'Absent days only',
        ]],
        'personality_shown' => ['The personality record prints', [
            'latest_term' => 'The latest term with ratings (traits are rated every term)',
            'report_term' => 'The ratings of the report\'s own term',
        ]],
        'examination_centre' => ['Examination centre on the Statement of Marks', 'text'],
    ];

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
