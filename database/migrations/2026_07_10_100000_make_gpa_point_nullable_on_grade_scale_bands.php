<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_scale_bands', function (Blueprint $table) {
            // §8.4: lower-school (Pre-School/Elementary) scales are descriptive —
            // letter only, no GPA point.
            $table->decimal('gpa_point', 3, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('grade_scale_bands', function (Blueprint $table) {
            $table->decimal('gpa_point', 3, 2)->nullable(false)->change();
        });
    }
};
