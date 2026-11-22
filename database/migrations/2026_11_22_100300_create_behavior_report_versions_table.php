<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A snapshot of every earlier version of a report, taken each time it is edited.
        Schema::create('behavior_report_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('behavior_report_id')->constrained('behavior_reports');
            $table->unsignedSmallInteger('version');
            $table->date('incident_date');
            $table->foreignId('behavior_category_id')->constrained('behavior_categories');
            $table->text('description');
            $table->boolean('is_office_referral');
            $table->foreignId('recorded_by')->constrained('staff_profiles');
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->unique(['behavior_report_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_report_versions');
    }
};
