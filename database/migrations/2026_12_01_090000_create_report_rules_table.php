<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Report card and attendance rules (sdp_doc/YASIS_Academic_and_Fee_Rules.md §7.1 and §8) as
     * data, edited by the Admin and read-only for the Principal. Each default awaiting the school is
     * flagged with its question number.
     */
    public function up(): void
    {
        Schema::create('report_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('value', 40);
            $table->string('pending_question', 20)->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('report_rules')->insert([
            // §8: the minimum attendance to sit examinations; students below it are flagged, never blocked.
            ['key' => 'min_attendance_pct', 'value' => '80', 'pending_question' => 'Q16', 'created_at' => $now, 'updated_at' => $now],
            // §7.1: "Days absent" counts Absent and Excused days (absent_and_excused) or Absent only (absent_only).
            ['key' => 'days_absent_counts', 'value' => 'absent_and_excused', 'pending_question' => 'Q8', 'created_at' => $now, 'updated_at' => $now],
            // §7.1: traits are rated every term; the card prints the latest rated term (latest_term) or the report's own term (report_term).
            ['key' => 'personality_shown', 'value' => 'latest_term', 'pending_question' => 'Q14', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('report_rules');
    }
};
