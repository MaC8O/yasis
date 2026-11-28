<?php

namespace Tests\Feature;

use App\Models\AssessmentCategory;
use App\Models\AuditLog;
use App\Models\ReportRule;
use App\Models\ResultFreeze;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\StudentElective;
use App\Models\StudentResult;
use App\Models\StudentSubjectResult;
use App\Models\StudentTrack;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\SystemSetting;
use App\Models\TeachingAssignment;
use App\Services\ReportCardService;
use App\Services\ResultFreezeService;
use App\Services\ResultService;
use App\Services\StatementOfMarksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 9a, the school's answers on curriculum and results: SUPW's words (with Complete after the
 * term is locked), frozen results keeping the curriculum they were frozen with, the move from
 * elective slots to tracks, the promotion note and the Grade 10 card. Invented names only.
 */
class SchoolAnswersResultsTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    private StaffProfile $principal;

    private StaffProfile $supwTeacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->principal = $this->makeStaff('principal', 'Principal', 'principal@test.local');
        $this->supwTeacher = $this->makeStaff('teacher', 'Teacher', 'supw.teacher@test.local');
    }

    private function gradeFive(): array
    {
        $section = $this->levelSection('Grade 5-A', '5');
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => Subject::where('code', 'SUPW')->value('id'), 'teacher_id' => $this->supwTeacher->id]);
        $student = $this->pupil('Invented Five', $section);
        $student->update(['date_of_birth' => '2015-03-04']);
        $nine = ['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'SOCST', 'COMP', 'PE', 'MUSIC'];
        foreach ($this->terms as $term) {
            $this->setMarks($student, $section, $term, array_fill_keys($nine, 75));
        }

        return [$section, $student];
    }

    private function words(Section $section, int $term, array $words)
    {
        return $this->actingAs($this->supwTeacher->user)->put('/teacher/gradebook/words', [
            'section_id' => $section->id, 'subject_id' => Subject::where('code', 'SUPW')->value('id'),
            'term_id' => $this->terms[$term - 1]->id, 'words' => $words,
        ]);
    }

    private function lockAll(): void
    {
        foreach ($this->terms as $term) {
            app(ResultFreezeService::class)->lock($term, $this->principal->user);
        }
    }

    private function frozenWord(Student $student, ?int $term): ?string
    {
        $freeze = ResultFreeze::active()->where('academic_year_id', $this->year->id)
            ->where('term_id', $term ? $this->terms[$term - 1]->id : null)->latest('id')->first();
        $result = StudentResult::where('result_freeze_id', $freeze->id)->where('student_id', $student->id)->current()->first();

        return StudentSubjectResult::where('student_result_id', $result->id)->where('subject_id', Subject::where('code', 'SUPW')->value('id'))->value('band_label');
    }

    public function test_the_supw_teacher_enters_words_and_the_gradebook_has_no_categories_for_it(): void
    {
        [$section, $student] = $this->gradeFive();

        $this->actingAs($this->supwTeacher->user)->get("/teacher/gradebook?section={$section->id}&subject=".Subject::where('code', 'SUPW')->value('id'))
            ->assertOk()->assertSee('Pass, Incomplete or Complete')->assertSee('data-supw-form', false);
        $this->assertSame(0, AssessmentCategory::where('section_id', $section->id)->where('subject_id', Subject::where('code', 'SUPW')->value('id'))->count()); // no template for SUPW

        $this->words($section, 1, [$student->id => 'Incomplete'])->assertSessionHas('status', 'Saved 1 word.');
        $this->words($section, 1, [$student->id => 'Fail'])->assertSessionHasErrors('words.'.$student->id);
        $log = AuditLog::where('action', 'Recorded SUPW words')->sole();
        $this->assertSame(['teacher', [['student_id' => $student->id, 'from' => null, 'to' => 'Incomplete']]], [$log->role, $log->details['changes']]);

        // Only the SUPW teacher of the class.
        $other = $this->makeStaff('teacher', 'Teacher', 'other@test.local');
        $this->actingAs($other->user)->put('/teacher/gradebook/words', [
            'section_id' => $section->id, 'subject_id' => Subject::where('code', 'SUPW')->value('id'), 'term_id' => $this->terms[0]->id,
            'words' => [$student->id => 'Pass'],
        ])->assertForbidden();
    }

    public function test_incomplete_becomes_complete_after_the_year_is_locked_and_the_frozen_word_and_statement_follow(): void
    {
        [$section, $student] = $this->gradeFive();
        foreach ([1, 2, 3] as $term) {
            $this->words($section, $term, [$student->id => 'Pass']);
        }
        $this->words($section, 4, [$student->id => 'Incomplete']);
        $this->lockAll();

        // The year's word is the last term's.
        $this->assertSame(['INCOMPLETE', 'INCOMPLETE', 'PASS'], [$this->frozenWord($student, 4), $this->frozenWord($student, null), $this->frozenWord($student, 1)]);
        $year = app(ResultService::class)->sectionYear($section)->for($student);
        $this->assertSame(['Promote', 'INCOMPLETE'], [$year->recommendation, $year->completionWord()]);
        $this->assertStringContainsString('SUPW Incomplete', app(ResultService::class)->promotionRecommendations($section)[$student->id]['basis']);

        // An issued Statement of Marks shows SUPW's word after the counted subjects.
        SystemSetting::set('transcript_issuance_enabled', '1');
        $statements = app(StatementOfMarksService::class);
        $statement = $statements->prepare($student, $this->year, 'MARCH 2-5, 2027', '2027-03-26', $this->principal->user);
        $content = $statements->issue($statement, $this->principal->user);
        $this->assertSame(['label' => 'SUPW', 'score' => 'INCOMPLETE', 'completion' => true], last($content['right']));
        $this->assertSame('75', $content['accumulative']); // nine subjects; SUPW counts in nothing

        // Locked: Pass can't change, and Incomplete can only become Complete.
        $this->words($section, 1, [$student->id => 'Incomplete'])->assertSessionHas('warning', 'First is locked: only Incomplete can still change, to Complete.');
        $this->words($section, 4, [$student->id => 'Pass'])->assertSessionHas('warning');
        $this->words($section, 4, [$student->id => 'Complete'])->assertSessionHas('status', 'Saved 1 word.');

        $this->assertSame(['COMPLETE', 'COMPLETE', 'PASS'], [$this->frozenWord($student, 4), $this->frozenWord($student, null), $this->frozenWord($student, 1)]);
        $this->assertSame('COMPLETE', last($statement->fresh()->content['right'])['score']);
        $this->assertFalse($statements->isOutdated($statement->fresh())); // nothing else was recalculated
        $log = AuditLog::where('action', 'Completed SUPW after the term was locked')->sole();
        $this->assertSame(['term' => 1, 'year' => 1, 'statements' => 1], $log->details['frozen_rows_updated'][$student->id]);
        $card = app(ReportCardService::class)->build($student, $this->terms[3]);
        $this->assertSame('COMPLETE', collect($card['subjects'])->firstWhere('label', 'SUPW')['cells'][4]);
    }

    public function test_with_the_fq3_rule_on_supw_incomplete_makes_the_recommendation_retain_until_completed(): void
    {
        ReportRule::where('key', 'supw_incomplete_blocks_promotion')->update(['value' => 'yes']);
        [$section, $student] = $this->gradeFive();
        $this->words($section, 4, [$student->id => 'Incomplete']);
        $this->lockAll();

        $this->assertSame('Retain', app(ResultService::class)->sectionYear($section)->for($student)->recommendation);

        $this->words($section, 4, [$student->id => 'Complete']);
        $this->assertSame('Promote', app(ResultService::class)->sectionYear($section)->for($student)->recommendation);
    }

    public function test_a_refreeze_uses_the_curriculum_the_term_was_frozen_with_until_the_principal_recalculates(): void
    {
        $section = $this->levelSection('Grade 2-A', '2');
        $student = $this->pupil('Invented Two', $section);
        $this->setMarks($student, $section, $this->terms[0], array_merge(self::KG_EXAMPLE, ['CHAR' => 55]));
        $freezes = app(ResultFreezeService::class);
        $freeze = $freezes->lock($this->terms[0], $this->principal->user);
        $this->assertArrayHasKey((string) $section->grade_level_id, $freeze->fresh()->curriculum);
        // (540 + 55) / 7 = 85.
        $this->assertSame('85', app(ResultService::class)->sectionTerm($section, $this->terms[0])->for($student)->value);

        // The curriculum changes later: Character Building stops counting.
        SubjectOffering::where('grade_level_id', $section->grade_level_id)->whereHas('subject', fn ($q) => $q->where('code', 'CHAR'))->update(['counts_in_result' => false]);

        // An applied grade change re-freezes with the freeze's own curriculum: still seven subjects.
        $freezes->refreezeSection($section, $this->terms[0], $this->principal->user, 'Grade change #1 applied');
        $this->assertSame('85', app(ResultService::class)->sectionTerm($section, $this->terms[0])->for($student)->value);

        // Unlocking and relocking with the previous rules keeps it too; recalculating with the current rules doesn't.
        $freezes->unlock($this->terms[0], $this->principal->user);
        $freezes->lock($this->terms[0], $this->principal->user);
        $this->assertSame('85', app(ResultService::class)->sectionTerm($section, $this->terms[0])->for($student)->value);
        $freezes->unlock($this->terms[0], $this->principal->user);
        $freezes->lock($this->terms[0], $this->principal->user, ResultFreeze::RULES_CURRENT, 'The school changed the curriculum');
        $this->assertSame('90', app(ResultService::class)->sectionTerm($section, $this->terms[0])->for($student)->value);
    }

    public function test_the_migration_moves_one_track_choices_into_tracks_and_leaves_mixed_ones_for_the_registrar(): void
    {
        $migration = require database_path('migrations/2026_12_19_090200_apply_school_answers_to_curriculum.php');
        $migration->down();
        $this->assertSame('A', SubjectOffering::whereHas('gradeLevel', fn ($q) => $q->where('code', '11'))->whereHas('subject', fn ($q) => $q->where('code', 'BIO'))->value('elective_slot'));
        $this->assertSame('Q4', SubjectOffering::whereHas('subject', fn ($q) => $q->where('code', 'CHAR'))->value('pending_question'));

        $section = $this->levelSection('Grade 11-A', '11');
        $science = $this->pupil('Invented Science', $section);
        $mixed = $this->pupil('Invented Mixed', $section);
        $choose = function (Student $student, array $codes) {
            foreach ($codes as $slot => $code) {
                StudentElective::create(['student_id' => $student->id, 'academic_year_id' => $this->year->id, 'elective_slot' => $slot,
                    'subject_offering_id' => SubjectOffering::whereHas('gradeLevel', fn ($q) => $q->where('code', '11'))->whereHas('subject', fn ($q) => $q->where('code', $code))->value('id')]);
            }
        };
        $choose($science, ['A' => 'BIO', 'B' => 'CHEM', 'C' => 'PHYS']);
        $choose($mixed, ['A' => 'BIO', 'B' => 'WGEO', 'C' => 'ACCT']);

        $migration->up();

        $this->assertSame(['science', null], [
            StudentTrack::where('student_id', $science->id)->value('track'), StudentTrack::where('student_id', $mixed->id)->value('track'),
        ]);
        $this->assertNull(StudentTrack::where('student_id', $science->id)->value('set_by'));
        $this->assertSame(6, StudentElective::count()); // kept
        $this->assertSame(0, SubjectOffering::whereNotNull('elective_slot')->count());

        // Once the Registrar has set a track, rolling back is refused.
        StudentTrack::create(['student_id' => $mixed->id, 'academic_year_id' => $this->year->id, 'track' => 'arts', 'set_by' => $this->principal->id]);
        $this->expectExceptionMessage('the Registrar has set students\' tracks');
        $migration->down();
    }

    public function test_the_grade_10_card_prints_the_average_in_its_gpa_row_and_ranks_by_it(): void
    {
        $section = $this->levelSection('Grade 10-A', '10');
        $student = $this->pupil('Invented Ten', $section);
        $this->setTrack($student, 'arts');
        $this->setMarks($student, $section, $this->terms[0], ['MORAL' => 90, 'MYAN' => 80, 'ENG' => 70, 'MATH' => 60, 'WHIST' => 90, 'WGEO' => 80, 'MUSIC' => 70]);

        $card = app(ReportCardService::class)->build($student, $this->terms[0]);

        // Points 10 + 9 + 8 + 7 + 10 + 9 + 8 = 61 (First); the G.P.A row holds the average, (90+80+70+60+90+80+70) / 7 = 77.
        $this->assertSame(['G.P.A', '77', '1'], [$card['gpaLabel'], $card['gpaRow'][1], $card['positionRow'][1]]);
        $rows = collect($card['subjects'])->keyBy('label');
        $this->assertSame(['-', '90', ''], [$rows['BIOLOGY']['cells'][1], $rows['WORLD HISTORY']['cells'][1], $rows['SUPW']['cells'][1]]);
        $r = app(ResultService::class)->sectionTerm($section, $this->terms[0])->for($student);
        $this->assertSame(['61', 'First', '77'], [$r->value, $r->classLabel, $r->average]);
    }
}
