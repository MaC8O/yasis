<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frozen results keep the attendance rules they were frozen with: a freeze stores them, so a
     * re-freeze after a grade change, or a relock with the previous rules, works the Attendance
     * category out the same way. Existing freezes get the rules that applied before the school's
     * answers: Excused days left out of the mark, no late arrivals converted, and "Days absent"
     * counting Excused days as the old setting said.
     */
    public function up(): void
    {
        Schema::table('result_freezes', function (Blueprint $table) {
            $table->json('attendance_rules')->nullable()->after('curriculum');
        });

        $daysAbsent = DB::table('report_rules')->where('key', 'days_absent_counts')->value('value') ?? 'absent_and_excused';
        DB::table('result_freezes')->update(['attendance_rules' => json_encode([
            'excused' => 'leave_out',
            'lates_per_absence' => 0,
            'excused_in_days_absent' => $daysAbsent === 'absent_and_excused',
        ])]);
    }

    public function down(): void
    {
        Schema::table('result_freezes', function (Blueprint $table) {
            $table->dropColumn('attendance_rules');
        });
    }
};
