<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which grading schemes use the fixed gradebook template. KG_9 does (school policy); G10 does
     * by default (Q9); G11_12 keeps free category weighting (Q9). Q9 is added to the G10 and G11_12
     * flags. Set here, shown read-only until the school answers Q9.
     */
    public function up(): void
    {
        Schema::table('grading_schemes', function (Blueprint $table) {
            $table->boolean('uses_gradebook_template')->default(false)->after('failed_ranking');
        });

        DB::table('grading_schemes')->whereIn('code', ['KG_9', 'G10'])->update(['uses_gradebook_template' => true]);
        foreach (DB::table('grading_schemes')->whereIn('code', ['G10', 'G11_12'])->get(['id', 'pending_question']) as $scheme) {
            DB::table('grading_schemes')->where('id', $scheme->id)->update(['pending_question' => $this->withFlag($scheme->pending_question, 'Q9')]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('grading_schemes')->get(['id', 'pending_question']) as $scheme) {
            $flags = array_values(array_filter(array_map('trim', explode(',', (string) $scheme->pending_question)), fn ($q) => $q !== '' && $q !== 'Q9'));
            DB::table('grading_schemes')->where('id', $scheme->id)->update(['pending_question' => $flags ? implode(',', $flags) : null]);
        }

        Schema::table('grading_schemes', function (Blueprint $table) {
            $table->dropColumn('uses_gradebook_template');
        });
    }

    private function withFlag(?string $flags, string $flag): string
    {
        $list = array_filter(array_map('trim', explode(',', (string) $flags)));
        $list[] = $flag;
        $list = array_unique($list);
        sort($list, SORT_NATURAL);

        return implode(',', $list);
    }
};
