<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The school's answer to Q9: the 10/10/10/10/60 make-up of a subject's marks applies to every
     * grade, so Grades 11-12 use the fixed gradebook template too, and Q9 is cleared from the
     * Grade 10 and Grades 11-12 schemes. Open-term gradebooks move onto the template when they are
     * opened (or by gradebook:adopt-template); locked terms keep their own weights.
     */
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('grading_schemes')->where('code', 'G11_12')->update(['uses_gradebook_template' => true, 'updated_at' => now()]);
            foreach (['G10', 'G11_12'] as $code) {
                $this->setFlags($code, fn (array $flags) => array_values(array_diff($flags, ['Q9'])));
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::table('grading_schemes')->where('code', 'G11_12')->update(['uses_gradebook_template' => false, 'updated_at' => now()]);
            foreach (['G10', 'G11_12'] as $code) {
                $this->setFlags($code, fn (array $flags) => array_values(array_unique(array_merge(['Q9'], $flags))));
            }
        });
    }

    private function setFlags(string $code, callable $change): void
    {
        $current = (string) DB::table('grading_schemes')->where('code', $code)->value('pending_question');
        $flags = $change(array_values(array_filter(array_map('trim', explode(',', $current)))));
        natsort($flags);
        DB::table('grading_schemes')->where('code', $code)->update(['pending_question' => $flags ? implode(',', $flags) : null]);
    }
};
