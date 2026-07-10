<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->foreignId('id')->constrained('users')->cascadeOnDelete();
            $table->primary('id');
            $table->string('staff_id_number', 30)->unique();
            $table->enum('role_type', [
                'Admin', 'Principal', 'VP_Academic', 'Registrar', 'Teacher', 'Treasurer', 'HR_Office',
            ]);
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->enum('status', ['Active', 'On Leave', 'Probation', 'Inactive'])->default('Active');
            $table->date('joined_date');
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
