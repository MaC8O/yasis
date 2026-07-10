<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imported_fee_records', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });

        Schema::table('imported_fee_records', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->change();
            $table->string('raw_student_key', 60)->nullable()->after('import_batch_id');
        });

        Schema::table('imported_fee_records', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('imported_fee_records', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropColumn('raw_student_key');
            $table->foreignId('student_id')->nullable(false)->change();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
    }
};
