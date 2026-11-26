<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One subject of a stored result, with a copy of its curriculum row at freeze time (label,
     * order, compulsory or slot, counted, grading mode, credit hours, flag), its mark (two decimals,
     * unrounded), the mark as printed, and the band it fell in (letter, points or grade value, fail).
     */
    public function up(): void
    {
        Schema::create('student_subject_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_result_id')->constrained('student_results')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('subject_offering_id')->nullable()->constrained('subject_offerings')->restrictOnDelete();
            $table->string('report_label', 60);
            $table->unsignedSmallInteger('sort_order');
            $table->boolean('in_curriculum')->default(true);
            $table->boolean('is_compulsory')->default(true);
            $table->string('elective_slot', 10)->nullable();
            $table->boolean('taken')->default(true);
            $table->boolean('counts_in_result')->default(false);
            $table->string('grading_mode', 20)->nullable();
            $table->decimal('credit_hours', 3, 1)->nullable();
            $table->decimal('mark', 5, 2)->nullable();
            $table->decimal('rounded_mark', 5, 2)->nullable();
            $table->string('band_label', 20)->nullable();
            $table->decimal('band_value', 4, 2)->nullable();
            $table->boolean('is_fail')->nullable();
            $table->string('pending_question', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subject_results');
    }
};
