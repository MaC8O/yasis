<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance frozen with each term result, so a locked term's report card doesn't change if
     * attendance records are edited later: days absent (by the Q8 rule) and the attendance
     * percentage (for the Q16 eligibility check). Nullable: results frozen before this show live
     * attendance.
     */
    public function up(): void
    {
        Schema::table('student_results', function (Blueprint $table) {
            $table->unsignedSmallInteger('days_absent')->nullable()->after('ranked_count');
            $table->decimal('attendance_pct', 6, 2)->nullable()->after('days_absent');
        });
    }

    public function down(): void
    {
        Schema::table('student_results', function (Blueprint $table) {
            $table->dropColumn(['days_absent', 'attendance_pct']);
        });
    }
};
