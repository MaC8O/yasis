<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TO_EXCUSED = ['absent_and_excused' => 'count_as_absent', 'absent_only' => 'leave_out'];

    /**
     * The school's attendance answers (Q8, Q16), as report rules the Admin edits:
     * - `excused_absences` replaces `days_absent_counts`: one rule for the 10% mark, Days absent and
     *   the 80% check. Default: an excused absence counts as an absence (FQ1).
     * - `lates_per_absence`: every 3 late arrivals count as one absent day (whole multiples only).
     * - `attendance_check_period`: the 80% rule over the school year to date (Q16), not per term.
     * - `max_subject_absence_pct`: more than 10% absence in a subject in a term.
     * - `subject_attendance_source`: per-subject attendance comes from the section's daily
     *   attendance, since ISMS has none per subject (FQ2).
     * Q16 is cleared from the minimum, and the template's Attendance row waits on FQ1 and FQ2 now.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $now = now();
            $old = DB::table('report_rules')->where('key', 'days_absent_counts')->first();
            DB::table('report_rules')->where('key', 'days_absent_counts')->update([
                'key' => 'excused_absences', 'value' => self::TO_EXCUSED[$old->value ?? 'absent_and_excused'] ?? 'count_as_absent',
                'pending_question' => 'FQ1', 'updated_at' => $now,
            ]);
            DB::table('report_rules')->insert([
                ['key' => 'lates_per_absence', 'value' => '3', 'pending_question' => null, 'created_at' => $now, 'updated_at' => $now],
                ['key' => 'attendance_check_period', 'value' => 'year', 'pending_question' => null, 'created_at' => $now, 'updated_at' => $now],
                ['key' => 'max_subject_absence_pct', 'value' => '10', 'pending_question' => null, 'created_at' => $now, 'updated_at' => $now],
                ['key' => 'subject_attendance_source', 'value' => 'daily', 'pending_question' => 'FQ2', 'created_at' => $now, 'updated_at' => $now],
            ]);
            DB::table('report_rules')->where('key', 'min_attendance_pct')->update(['pending_question' => null, 'updated_at' => $now]);
            DB::table('gradebook_template_categories')->where('key', 'attendance')->update(['pending_question' => 'FQ1,FQ2', 'updated_at' => $now]);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $now = now();
            $value = DB::table('report_rules')->where('key', 'excused_absences')->value('value');
            DB::table('report_rules')->where('key', 'excused_absences')->update([
                'key' => 'days_absent_counts', 'value' => array_flip(self::TO_EXCUSED)[$value] ?? 'absent_and_excused',
                'pending_question' => 'Q8', 'updated_at' => $now,
            ]);
            DB::table('report_rules')->whereIn('key', ['lates_per_absence', 'attendance_check_period', 'max_subject_absence_pct', 'subject_attendance_source'])->delete();
            DB::table('report_rules')->where('key', 'min_attendance_pct')->update(['pending_question' => 'Q16', 'updated_at' => $now]);
            DB::table('gradebook_template_categories')->where('key', 'attendance')->update(['pending_question' => 'Q8', 'updated_at' => $now]);
        });
    }
};
