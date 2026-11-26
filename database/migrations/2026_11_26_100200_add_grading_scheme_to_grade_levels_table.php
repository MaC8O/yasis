<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The grading scheme each grade level uses. Nullable: existing rows stay empty until the next
     * migration assigns the school's schemes, and a level the Admin adds later has none until the
     * Admin picks one. A scheme in use can't be deleted.
     */
    public function up(): void
    {
        Schema::table('grade_levels', function (Blueprint $table) {
            $table->foreignId('grading_scheme_id')->nullable()->after('sort_order')
                ->constrained('grading_schemes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grade_levels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grading_scheme_id');
        });
    }
};
