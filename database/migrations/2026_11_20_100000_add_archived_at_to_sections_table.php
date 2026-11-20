<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Archiving is the safe alternative to deleting a section that already holds
     * students, attendance or grades: the section is retired, its history kept.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
