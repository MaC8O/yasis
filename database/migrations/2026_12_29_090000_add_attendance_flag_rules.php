<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The attendance flags (§8) measured against the whole period, as the school's rules are:
     * - `flag_basis` = whole_period: a flag fires only once the breach is certain (absences above
     *   the limit's share of the term's or year's school days); `so_far` keeps the percentage of
     *   the days recorded so far.
     * - `school_week` = mon_fri: the days of the week the school meets, for counting school days.
     */
    public function up(): void
    {
        $now = now();
        DB::table('report_rules')->insert([
            ['key' => 'flag_basis', 'value' => 'whole_period', 'pending_question' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'school_week', 'value' => 'mon_fri', 'pending_question' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('report_rules')->whereIn('key', ['flag_basis', 'school_week'])->delete();
    }
};
