<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_section_id')->constrained('sections');
            $table->foreignId('prepared_by')->constrained('staff_profiles');
            $table->enum('status', ['Pending', 'VP_Approved', 'Rejected', 'Applied'])->default('Pending');
            $table->foreignId('vp_approved_by')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->dateTime('vp_approved_at')->nullable();
            $table->foreignId('principal_approved_by')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->dateTime('principal_approved_at')->nullable();
            $table->dateTime('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_batches');
    }
};
