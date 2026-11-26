<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A student's stored result for a freeze (a term or the year): the average, point total or GPA
     * as printed and unrounded, the class of pass, pass or fail, and the position in the section.
     * `status` is complete, incomplete (a counted subject has no mark) or blocked (for example Grade
     * 10 while its counted subjects are unconfirmed). A grade change applied to a locked term
     * supersedes the section's rows (`superseded_at`, kept) and inserts new ones.
     */
    public function up(): void
    {
        Schema::create('student_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_freeze_id')->constrained('result_freezes')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('grade_level_id')->nullable()->constrained('grade_levels')->restrictOnDelete();
            $table->foreignId('grading_scheme_id')->nullable()->constrained('grading_schemes')->restrictOnDelete();
            $table->string('result_basis', 20)->nullable();
            $table->string('status', 20);
            $table->text('status_reason')->nullable();
            $table->decimal('result_value', 5, 2)->nullable();
            $table->decimal('result_unrounded', 9, 4)->nullable();
            $table->string('class_label', 20)->nullable();
            $table->boolean('passed')->nullable();
            $table->unsignedSmallInteger('position')->nullable();
            $table->unsignedSmallInteger('ranked_count')->nullable();
            $table->string('recommendation', 20)->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->string('superseded_reason')->nullable();
            $table->timestamps();

            $table->index(['result_freeze_id', 'section_id', 'superseded_at']);
            $table->index(['student_id', 'superseded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_results');
    }
};
