<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('type', ['Transcript', 'Report Card', 'Transfer/Leaving Certificate', 'Completion Certificate', 'Enrollment Certificate']);
            $table->enum('status', ['Draft', 'Pending Approval', 'Approved', 'Returned', 'Ready', 'Printed'])->default('Draft');
            $table->foreignId('prepared_by')->constrained('staff_profiles');
            $table->foreignId('approved_by')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('generated_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
