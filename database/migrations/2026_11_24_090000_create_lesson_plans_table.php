<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A teacher's lesson plan for one class (section + subject) and term. The file itself lives in
 * lesson_plan_versions; every upload adds a version and none is ever replaced.
 *
 * Submitted ("Awaiting review") → Approved | NeedsChanges by the VP. A new version always
 * returns the plan to Submitted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->id();
            // Restrict: deleting a teacher's account with plans falls back to erasing it (see
            // UserManagementController::destroy), and the school keeps its staff documents.
            $table->foreignId('teacher_id')->constrained('staff_profiles')->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->string('note', 1000)->nullable();
            $table->enum('status', ['Submitted', 'Approved', 'NeedsChanges'])->default('Submitted');
            $table->unsignedSmallInteger('current_version')->default(1);
            $table->dateTime('submitted_at');
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('teacher_id');
            $table->index(['status', 'submitted_at']);
            $table->index(['section_id', 'subject_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_plans');
    }
};
