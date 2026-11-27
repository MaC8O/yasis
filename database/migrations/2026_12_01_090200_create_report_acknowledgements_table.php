<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Report card acknowledgements (§7.1), printed where each signature goes:
     * - class_teacher: the homeroom teacher confirms a student's term report (once the term is locked);
     * - principal: the Principal's release of the term's results, one row per term (student null);
     * - guardian: "I acknowledge this report" in the guardian portal.
     * Withdrawn, never deleted (for example when the Principal turns the release off).
     */
    public function up(): void
    {
        Schema::create('report_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->constrained('terms')->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->string('role', 20);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['term_id', 'role', 'withdrawn_at']);
            $table->index(['student_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_acknowledgements');
    }
};
