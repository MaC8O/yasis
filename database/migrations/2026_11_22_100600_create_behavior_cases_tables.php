<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expulsion review case: two keys (VP first, then Principal). Up to three rounds; if they
        // still disagree after the third, the case closes as Monitor (resolution DefaultAfterRounds).
        Schema::create('behavior_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('opened_by_flag_id')->constrained('behavior_flags');
            $table->enum('status', ['Open', 'Decided'])->default('Open');
            $table->unsignedTinyInteger('round')->default(1);
            $table->enum('awaiting', ['VP', 'Principal'])->nullable()->default('VP');
            $table->enum('outcome', ['Expel', 'Monitor', 'Close'])->nullable();
            $table->enum('resolution', ['Agreed', 'DefaultAfterRounds'])->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });

        Schema::create('behavior_case_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('behavior_case_id')->constrained('behavior_cases');
            $table->unsignedTinyInteger('round');
            $table->enum('role', ['VP', 'Principal']);
            $table->enum('decision', ['Expel', 'Monitor', 'Close']);
            $table->text('reason');
            $table->foreignId('decided_by')->constrained('staff_profiles');
            $table->dateTime('decided_at');
            $table->timestamps();

            $table->unique(['behavior_case_id', 'round', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_case_decisions');
        Schema::dropIfExists('behavior_cases');
    }
};
