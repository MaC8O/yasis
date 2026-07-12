<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_batch_id')->constrained('promotion_batches')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('action', ['Promote', 'Retain', 'Graduate'])->default('Promote');
            $table->foreignId('to_section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->timestamps();
            $table->unique(['promotion_batch_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_batch_items');
    }
};
