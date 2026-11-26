<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grading schemes (sdp_doc/YASIS_Academic_and_Fee_Rules.md §4-6). One row per scheme; each
     * grade level points at one. `code` and `result_basis` are fixed (the calculation branches on
     * them); the rest is the Admin's to edit. The rounding rule is stored per scheme: marks are
     * rounded to `mark_decimals` and the result (average, point total or GPA) to
     * `result_decimals` before the band lookup. `pending_question` flags defaults awaiting the
     * school, as on the curriculum.
     */
    public function up(): void
    {
        Schema::create('grading_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 60);
            $table->string('result_basis', 20);
            $table->decimal('result_max', 5, 2);
            $table->unsignedTinyInteger('mark_decimals')->default(0);
            $table->unsignedTinyInteger('result_decimals')->default(0);
            $table->string('rounding_mode', 10)->default('half_up');
            $table->string('subject_fail_rule', 20);
            $table->string('pending_question', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_schemes');
    }
};
