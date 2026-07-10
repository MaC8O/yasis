<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->smallInteger('year');
            $table->smallInteger('allocated');
            $table->smallInteger('pending')->default(0);
            $table->smallInteger('used')->default(0);
            $table->timestamps();
            $table->unique(['staff_id', 'leave_type_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
