<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gradebook items are archived, never deleted: an archived item keeps its grades but no longer
     * counts in any mark, and a teacher can restore it while the term is open. Nullable; existing
     * rows stay null (current).
     */
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('max_score');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
