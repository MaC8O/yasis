<?php

use App\Support\FeeSheet2026;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * §10: the school's 2026-2027 fee sheet, unpublished, when that school year exists (on staging).
 * The Treasurer checks the amounts and publishes. On a fresh seed the year doesn't exist yet, so
 * the seeder loads it instead. Rolling back removes only that schedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        FeeSheet2026::load();
    }

    public function down(): void
    {
        $yearId = DB::table('academic_years')->where('year_label', FeeSheet2026::YEAR_LABEL)->value('id');
        $scheduleId = $yearId ? DB::table('fee_schedules')->where('academic_year_id', $yearId)->value('id') : null;
        if ($scheduleId) {
            DB::table('fee_schedule_items')->where('fee_schedule_id', $scheduleId)->delete();
            DB::table('fee_schedules')->where('id', $scheduleId)->delete();
        }
    }
};
