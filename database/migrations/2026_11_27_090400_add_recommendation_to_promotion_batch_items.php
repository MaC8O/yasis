<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section 9: the calculated recommendation shown to the Registrar when the batch was prepared,
     * what it was based on, and the reason for a decision that differs from it. Existing items stay
     * null: they were prepared before recommendations existed.
     */
    public function up(): void
    {
        Schema::table('promotion_batch_items', function (Blueprint $table) {
            $table->string('recommended_action', 20)->nullable()->after('to_section_id');
            $table->string('recommendation_basis')->nullable()->after('recommended_action');
            $table->text('override_reason')->nullable()->after('recommendation_basis');
        });
    }

    public function down(): void
    {
        Schema::table('promotion_batch_items', function (Blueprint $table) {
            $table->dropColumn(['recommended_action', 'recommendation_basis', 'override_reason']);
        });
    }
};
