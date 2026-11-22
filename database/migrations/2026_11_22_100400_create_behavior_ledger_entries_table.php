<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only. The score for a school year is 100 − deductions applied + restorations applied.
        Schema::create('behavior_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->enum('type', ['Deduction', 'Restoration']);
            $table->unsignedSmallInteger('points_requested');
            $table->unsignedSmallInteger('points_applied');
            $table->text('reason');
            $table->foreignId('behavior_report_id')->nullable()->constrained('behavior_reports');
            $table->foreignId('restores_entry_id')->nullable()->constrained('behavior_ledger_entries');
            $table->unsignedTinyInteger('score_before');
            $table->unsignedTinyInteger('score_after');
            $table->foreignId('actor_id')->constrained('staff_profiles');
            $table->timestamps();

            $table->index(['student_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_ledger_entries');
    }
};
