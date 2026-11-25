<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The school's curriculum subjects (Mathematics, English, …) run from KG1 to Grade 12 and so
     * span every department; they are school-wide, with no department. Existing subjects keep
     * theirs. Rolling back fails while any school-wide subject remains (roll back the curriculum
     * seed first).
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable(false)->change();
        });
    }
};
