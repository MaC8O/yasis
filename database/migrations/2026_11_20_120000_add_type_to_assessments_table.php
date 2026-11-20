<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gradable item type (assignment / classwork / group work / test / quiz). Purely descriptive:
     * weighting stays on the category, so grade computation is unchanged.
     */
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('type', 20)->default('assignment')->after('name');
        });

        // Give existing items a sensible type from their name.
        foreach (['quiz' => '%quiz%', 'test' => '%test%', 'group_work' => '%group%', 'classwork' => '%classwork%'] as $type => $pattern) {
            DB::table('assessments')->where('type', 'assignment')->whereRaw('LOWER(name) LIKE ?', [$pattern])->update(['type' => $type]);
        }
        DB::table('assessments')->where('type', 'assignment')->whereRaw('LOWER(name) LIKE ?', ['%exam%'])->update(['type' => 'test']);
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
