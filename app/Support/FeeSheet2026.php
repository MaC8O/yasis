<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The school's 2026-2027 fee sheet (sdp_doc/YASIS_Academic_and_Fee_Rules.md §10), loaded into
 * fee_schedules / fee_schedule_items by the data migration (when the school year exists) and by
 * the seeder (on a fresh seed, where the year is created after the migrations). Plain DB queries,
 * so the migration doesn't depend on the models.
 */
final class FeeSheet2026
{
    public const YEAR_LABEL = '2026-2027';

    /** Level code => [yearly fee, tuition for the year]. */
    private const AMOUNTS = [
        'KG1' => [250000, 2400000], 'KG2' => [250000, 2400000],
        '1' => [250000, 2400000], '2' => [250000, 2400000], '3' => [250000, 2400000], '4' => [250000, 2400000],
        '5' => [250000, 2550000], '6' => [250000, 2550000],
        '7' => [300000, 2550000], '8' => [300000, 2550000],
        '9' => [300000, 2600000], // Q17: grouped with Grades 11-12 on the sheet
        '10' => [300000, 2500000],
        '11' => [300000, 2600000], '12' => [300000, 2600000],
    ];

    private const DEPOSIT = 20000;

    private const INSTALMENTS = 4;

    /**
     * Create the 2026-2027 schedule, unpublished, for the school year with that label. Returns the
     * schedule's id, or null when the year doesn't exist or already has a schedule.
     */
    public static function load(): ?int
    {
        $yearId = DB::table('academic_years')->where('year_label', self::YEAR_LABEL)->value('id');
        if (! $yearId || DB::table('fee_schedules')->where('academic_year_id', $yearId)->exists()) {
            return null;
        }

        $now = now();
        $scheduleId = DB::table('fee_schedules')->insertGetId([
            'academic_year_id' => $yearId, 'currency' => 'MMK', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $levels = DB::table('grade_levels')->whereIn('code', array_keys(self::AMOUNTS))->pluck('id', 'code');
        $rows = [];
        foreach ($levels as $code => $levelId) {
            [$yearly, $tuition] = self::AMOUNTS[$code];
            $base = ['fee_schedule_id' => $scheduleId, 'grade_level_id' => $levelId, 'created_at' => $now, 'updated_at' => $now];
            // Q18: the yearly fee and the deposit are separate one-time lines; tuition is four equal instalments.
            $rows[] = $base + ['fee_type' => 'yearly', 'amount' => $yearly, 'instalments' => 1, 'new_students_only' => false, 'pending_question' => 'Q18'];
            $rows[] = $base + ['fee_type' => 'tuition', 'amount' => $tuition, 'instalments' => self::INSTALMENTS, 'new_students_only' => false,
                'pending_question' => (string) $code === '9' ? 'Q17, Q18' : 'Q18'];
            $rows[] = $base + ['fee_type' => 'deposit', 'amount' => self::DEPOSIT, 'instalments' => 1, 'new_students_only' => true, 'pending_question' => 'Q18'];
        }
        DB::table('fee_schedule_items')->insert($rows);

        return $scheduleId;
    }
}
