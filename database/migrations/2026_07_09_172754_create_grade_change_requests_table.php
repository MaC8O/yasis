<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * §3.6 governance: grade changes after a term is locked require two-key
     * co-approval (VP reviews, Principal co-approves) and are audit-logged, so a
     * mark cannot be quietly altered after finalisation. Same shape as promotion_batches.
     */
    public function up(): void
    {
        Schema::create('grade_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments');
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('term_id')->constrained('terms');
            $table->decimal('old_score', 8, 2)->nullable();
            $table->decimal('new_score', 8, 2);
            $table->string('reason');
            $table->enum('status', ['Pending', 'VP_Approved', 'Applied', 'Rejected', 'Cancelled'])->default('Pending');
            $table->foreignId('requested_by')->constrained('staff_profiles');
            $table->foreignId('vp_approved_by')->nullable()->constrained('staff_profiles');
            $table->timestamp('vp_approved_at')->nullable();
            $table->foreignId('principal_approved_by')->nullable()->constrained('staff_profiles');
            $table->timestamp('principal_approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_change_requests');
    }
};
