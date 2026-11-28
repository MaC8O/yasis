<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KG4 = ['KG1', 'KG2', '1', '2', '3', '4'];

    private const G5_9 = ['5', '6', '7', '8', '9'];

    private const TRACKS = [
        '10' => ['BIO' => 'science', 'CHEM' => 'science', 'PHYS' => 'science', 'WHIST' => 'arts', 'WGEO' => 'arts', 'MUSIC' => 'arts'],
        '11' => ['BIO' => 'science', 'CHEM' => 'science', 'PHYS' => 'science', 'WHIST' => 'arts', 'WGEO' => 'arts', 'ACCT' => 'arts'],
        '12' => ['BIO' => 'science', 'CHEM' => 'science', 'PHYS' => 'science', 'WHIST' => 'arts', 'WGEO' => 'arts', 'ACCT' => 'arts'],
    ];

    /** The 11-12 slots as seeded (restored on rollback). */
    private const SLOTS = ['BIO' => 'A', 'CHEM' => 'B', 'PHYS' => 'C', 'WHIST' => 'A', 'WGEO' => 'B', 'ACCT' => 'C'];

    /**
     * The school's answers to Q4-Q7 (sdp_doc/YASIS_School_Answers.md):
     * - KG-4: Character Building counts, Social Study doesn't (Q4).
     * - SUPW, Grades 5-12: Pass, Incomplete or Complete, in no average or GPA (Q5).
     * - Grade 10: Moral, Myanmar, English and Mathematics plus one track of three count (Q6).
     *   Computer isn't counted by default, flagged FQ4 (follow-up question 4). PE and SUPW are extras.
     * - Grades 11-12: one track per student instead of a choice per slot (Q7).
     * Elective choices that are all from one track become that track; mixed choices get none and
     * are left for the Registrar. `student_electives` keeps its rows.
     */
    public function up(): void
    {
        DB::transaction(fn () => $this->apply());
    }

    private function apply(): void
    {
        $now = now();
        $this->set(self::KG4, ['CHAR'], ['counts_in_result' => true, 'pending_question' => null]);
        $this->set(self::KG4, ['SOCST'], ['counts_in_result' => false, 'pending_question' => null]);
        $this->set(array_merge(self::G5_9, ['10', '11', '12']), ['SUPW'], ['grading_mode' => 'completion', 'counts_in_result' => false, 'pending_question' => null]);

        $this->set(['10'], ['MORAL', 'MYAN', 'ENG', 'MATH'], ['is_compulsory' => true, 'counts_in_result' => true, 'pending_question' => null]);
        $this->set(['10'], ['COMP'], ['is_compulsory' => true, 'counts_in_result' => false, 'pending_question' => 'FQ4']);
        $this->set(['10'], ['PE'], ['is_compulsory' => true, 'counts_in_result' => false, 'pending_question' => null]);
        foreach (self::TRACKS as $level => $subjects) {
            foreach ($subjects as $code => $track) {
                $this->set([$level], [$code], ['is_compulsory' => false, 'track' => $track, 'counts_in_result' => true, 'pending_question' => null]);
            }
        }

        // Elective choices to tracks, before the slots are cleared.
        $trackOf = DB::table('subject_offerings')->whereNotNull('track')->pluck('track', 'id');
        $choices = DB::table('student_electives')->get()->groupBy(fn ($e) => $e->student_id.'|'.$e->academic_year_id);
        foreach ($choices as $rows) {
            $tracks = $rows->map(fn ($e) => $trackOf[$e->subject_offering_id] ?? null)->unique();
            if ($tracks->count() === 1 && $tracks->first() !== null
                && ! DB::table('student_tracks')->where('student_id', $rows[0]->student_id)->where('academic_year_id', $rows[0]->academic_year_id)->exists()) {
                DB::table('student_tracks')->insert([
                    'student_id' => $rows[0]->student_id, 'academic_year_id' => $rows[0]->academic_year_id,
                    'track' => $tracks->first(), 'set_by' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $this->set(['11', '12'], array_keys(self::SLOTS), ['elective_slot' => null]);
    }

    public function down(): void
    {
        DB::transaction(fn () => $this->revert());
    }

    private function revert(): void
    {
        if (DB::table('student_tracks')->whereNotNull('set_by')->exists()) {
            throw new RuntimeException('Not rolled back: the Registrar has set students\' tracks, and rolling back would lose them.');
        }
        DB::table('student_tracks')->whereNull('set_by')->delete();

        $this->set(self::KG4, ['CHAR', 'SOCST'], ['counts_in_result' => false, 'pending_question' => 'Q4']);
        $this->set(self::G5_9, ['SUPW'], ['grading_mode' => 'score', 'counts_in_result' => true, 'pending_question' => 'Q5']);
        $this->set(['10'], ['MORAL', 'MYAN', 'ENG', 'MATH', 'COMP', 'BIO', 'CHEM', 'PHYS', 'WHIST', 'WGEO', 'MUSIC', 'PE'],
            ['is_compulsory' => true, 'track' => null, 'counts_in_result' => false, 'pending_question' => 'Q6']);
        $this->set(['10'], ['SUPW'], ['grading_mode' => 'score', 'counts_in_result' => false, 'pending_question' => 'Q5,Q6']);
        foreach (self::SLOTS as $code => $slot) {
            $this->set(['11', '12'], [$code], ['track' => null, 'elective_slot' => $slot, 'pending_question' => 'Q7']);
        }
        $this->set(['11', '12'], ['SUPW'], ['grading_mode' => 'pass_fail', 'counts_in_result' => false, 'pending_question' => 'Q5']);
    }

    /** @param  list<string>  $levels  grade level codes  @param  list<string>  $subjects  subject codes */
    private function set(array $levels, array $subjects, array $values): void
    {
        DB::table('subject_offerings')
            ->whereIn('grade_level_id', DB::table('grade_levels')->whereIn('code', array_map('strval', $levels))->select('id'))
            ->whereIn('subject_id', DB::table('subjects')->whereIn('code', $subjects)->select('id'))
            ->update($values + ['updated_at' => now()]);
    }
};
