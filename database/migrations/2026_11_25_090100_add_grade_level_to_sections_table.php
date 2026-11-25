<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every section gets an explicit grade level. Nullable because some existing sections can't
     * be classified from their name (e.g. "Nursery": the school's levels start at KG1, see Q2);
     * the Registrar sets those on the section's edit form. A level in use can't be deleted.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('grade_level_id')->nullable()->after('department_id')
                ->constrained('grade_levels')->restrictOnDelete();
        });

        $this->backfill();
    }

    /**
     * One-time fill from the section name. Only sections without a level are touched, so running
     * it again is harmless. Returns the names it could not classify.
     *
     * @return list<string>
     */
    public function backfill(): array
    {
        $levels = DB::table('grade_levels')->pluck('id', 'code');
        $unclassified = [];

        foreach (DB::table('sections')->whereNull('grade_level_id')->orderBy('id')->get(['id', 'name']) as $section) {
            $code = $this->classify($section->name);

            if ($code === null || ! $levels->has($code)) {
                $unclassified[] = $section->name;

                continue;
            }

            DB::table('sections')->where('id', $section->id)->update(['grade_level_id' => $levels[$code]]);
        }

        return $unclassified;
    }

    /** "Grade 9-A" → "9", "Kindergarten 2" / "KG-2" → "KG2"; anything else (Nursery, a bare "KG") → null. */
    public function classify(string $name): ?string
    {
        if (preg_match('/\bgrade\s*(\d{1,2})\b/i', $name, $m)) {
            $grade = (int) $m[1];

            return $grade >= 1 && $grade <= 12 ? (string) $grade : null;
        }

        if (preg_match('/\b(?:kindergarten|kg)\s*-?\s*([12])\b/i', $name, $m)) {
            return 'KG'.$m[1];
        }

        return null;
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_level_id');
        });
    }
};
