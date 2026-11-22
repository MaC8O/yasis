<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A student is "at" a stage while that stage's flag is open. Closed flags stay as history.
        Schema::create('behavior_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->enum('stage', ['Warning', 'FinalWarning', 'ExpulsionReview']);
            $table->unsignedTinyInteger('threshold_at_open');
            $table->unsignedTinyInteger('score_at_open');
            $table->foreignId('opened_by_entry_id')->constrained('behavior_ledger_entries');
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by_entry_id')->nullable()->constrained('behavior_ledger_entries');
            $table->string('close_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['student_id', 'academic_year_id', 'stage', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_flags');
    }
};
