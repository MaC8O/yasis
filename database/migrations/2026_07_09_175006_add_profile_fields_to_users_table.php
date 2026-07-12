<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('status');
            $table->date('date_of_birth')->nullable()->after('photo_path');
            $table->string('gender', 20)->nullable()->after('date_of_birth');
            $table->string('phone', 30)->nullable()->after('gender');
            $table->string('address')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'date_of_birth', 'gender', 'phone', 'address']);
        });
    }
};
