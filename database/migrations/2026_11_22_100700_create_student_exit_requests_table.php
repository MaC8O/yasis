<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A request for the Registrar to process a student's exit. It never changes the student by
        // itself: the Registrar links it when confirming a Transfer/Drop, or the Principal withdraws it.
        Schema::create('student_exit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id');
            $table->text('reason');
            $table->enum('status', ['Pending', 'Processed', 'Withdrawn'])->default('Pending');
            $table->foreignId('processed_by')->nullable()->constrained('staff_profiles');
            $table->dateTime('processed_at')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('staff_profiles');
            $table->dateTime('withdrawn_at')->nullable();
            $table->text('withdraw_reason')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_exit_requests');
    }
};
