<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imported_fee_records', function (Blueprint $table) {
            // §9.4: "Hold parks a row" — held rows are excluded from the publish
            // gate and never surface in family-facing views.
            $table->boolean('is_held')->default(false)->after('is_restricted');
        });
    }

    public function down(): void
    {
        Schema::table('imported_fee_records', function (Blueprint $table) {
            $table->dropColumn('is_held');
        });
    }
};
