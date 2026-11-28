<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The school's answers (sdp_doc/YASIS_School_Answers.md): Grade 10 and Grades 11-12 take one
     * track of subjects (science or arts) instead of a choice per slot, and SUPW has no mark, only
     * Pass, Incomplete or Complete (the `completion` grading mode). `track` is copied into frozen
     * subject rows like the slot is.
     */
    public function up(): void
    {
        Schema::table('subject_offerings', function (Blueprint $table) {
            $table->string('track', 20)->nullable()->after('elective_slot');
        });
        DB::statement("ALTER TABLE subject_offerings MODIFY grading_mode ENUM('score','pass_fail','completion') NOT NULL DEFAULT 'score'");

        Schema::table('student_subject_results', function (Blueprint $table) {
            $table->string('track', 20)->nullable()->after('elective_slot');
        });
    }

    public function down(): void
    {
        Schema::table('student_subject_results', function (Blueprint $table) {
            $table->dropColumn('track');
        });
        DB::statement("ALTER TABLE subject_offerings MODIFY grading_mode ENUM('score','pass_fail') NOT NULL DEFAULT 'score'");
        Schema::table('subject_offerings', function (Blueprint $table) {
            $table->dropColumn('track');
        });
    }
};
