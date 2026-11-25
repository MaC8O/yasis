<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The curriculum as data (sdp_doc/YASIS_Academic_and_Fee_Rules.md §3): one row per subject per
     * grade level, in report-card order. `pending_question` marks a row whose values are a default
     * awaiting the school's answer (e.g. "Q6"); the Admin clears it once confirmed. Rows are
     * retired (`archived_at`), never deleted.
     */
    public function up(): void
    {
        Schema::create('subject_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->string('report_label', 60);
            $table->boolean('is_compulsory')->default(true);
            $table->string('elective_slot', 10)->nullable();
            $table->decimal('credit_hours', 3, 1)->nullable();
            $table->enum('grading_mode', ['score', 'pass_fail'])->default('score');
            $table->boolean('counts_in_result')->default(true);
            $table->smallInteger('sort_order');
            $table->string('pending_question', 10)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['grade_level_id', 'subject_id']);
            $table->unique(['grade_level_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_offerings');
    }
};
