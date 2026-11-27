<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `template_key`: the gradebook template category a gradebook category implements (null for a
     * teacher's own category, as in Grades 11-12). `archived_at`: a category taken out of use when a
     * gradebook moved onto the template; it keeps its items and grades but no longer counts. Both
     * nullable; existing rows stay null (the gradebook:adopt-template command moves open-term
     * gradebooks onto the template).
     */
    public function up(): void
    {
        Schema::table('assessment_categories', function (Blueprint $table) {
            $table->string('template_key', 20)->nullable()->after('weight_pct');
            $table->timestamp('archived_at')->nullable()->after('template_key');
            // Not led by section_id: MySQL would then use it for that foreign key and refuse to drop it on rollback.
            $table->index(['template_key', 'term_id'], 'assessment_categories_template_key_idx');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_categories', function (Blueprint $table) {
            $table->dropIndex('assessment_categories_template_key_idx');
            $table->dropColumn(['template_key', 'archived_at']);
        });
    }
};
