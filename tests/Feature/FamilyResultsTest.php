<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\ResultFreezeService;
use Barryvdh\DomPDF\Facade\Pdf;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 4b: every page that shows grades reads ResultService. The student and guardian pages and
 * the report-card PDF show the same numbers for the same student and term; a locked term shows its
 * frozen figures and an unlocked one is marked provisional; Grade 10 shows each subject's mark and
 * letter with the reason there is no point total yet; the teacher gradebook uses the section's scheme.
 */
class FamilyResultsTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    private Section $section;

    private Student $student;

    private User $studentUser;

    private User $guardianUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->section = $this->levelSection('Grade 12-A', '12');
        $this->student = $this->grade12Example($this->section, $this->terms[0]);
        [$this->studentUser, $this->guardianUser] = $this->familyAccounts($this->student);
        $this->terms[0]->update(['results_released' => true]);
    }

    /** @return array{0: User, 1: User} the student's own login and a linked guardian's */
    private function familyAccounts(Student $student): array
    {
        $studentUser = User::create(['name' => $student->name, 'email' => 's'.$student->id.'@test.local', 'password' => Hash::make('password'), 'status' => 'Active']);
        $studentUser->assignRole('student');
        $student->update(['user_id' => $studentUser->id]);

        $guardianUser = User::create(['name' => 'Parent '.$student->id, 'email' => 'g'.$student->id.'@test.local', 'password' => Hash::make('password'), 'status' => 'Active']);
        $guardianUser->assignRole('guardian');
        $guardian = Guardian::create(['user_id' => $guardianUser->id, 'relationship' => 'Mother', 'phone' => '0912']);
        $guardian->students()->attach($student->id, ['is_primary' => true]);

        return [$studentUser, $guardianUser];
    }

    /** Each subject on a grade page as [label, mark, letter]. */
    private function pageSubjects(string $html): array
    {
        $xpath = $this->xpath($html);
        $rows = [];
        foreach ($xpath->query('//div[@data-subject]') as $node) {
            $band = $xpath->query('.//*[@data-band]', $node)->item(0);
            $rows[] = [
                trim($xpath->query('.//p', $node)->item(0)->textContent),
                trim($xpath->query('.//*[@data-mark]', $node)->item(0)->textContent),
                $band ? trim(preg_replace('/\s+/', ' ', $band->textContent)) : '—',
            ];
        }

        return $rows;
    }

    /** Each subject row of the report-card PDF as [label, mark, letter]. */
    private function pdfSubjects(string $html): array
    {
        $xpath = $this->xpath($html);
        $rows = [];
        foreach ($xpath->query('//tbody/tr[not(contains(@class, "summary"))]') as $tr) {
            $cells = $xpath->query('./td', $tr);
            $rows[] = [
                trim(preg_replace('/\s*\(.*\)\s*$/', '', trim($cells->item(0)->firstChild->textContent))),
                trim($cells->item(1)->textContent),
                trim($cells->item(2)->textContent),
            ];
        }

        return $rows;
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="utf-8"?>'.$html);

        return new DOMXPath($doc);
    }

    /** The HTML the report-card PDF is rendered from (the PDF itself is binary). */
    private function reportCardHtml(User $as, string $url): string
    {
        $html = null;
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($view, $data) use (&$html) {
            $html = view($view, $data)->render();

            $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
            $pdf->shouldReceive('stream')->andReturn(response('%PDF', 200, ['Content-Type' => 'application/pdf']));

            return $pdf;
        });
        $this->actingAs($as)->get($url)->assertOk();

        return $html;
    }

    public function test_the_student_page_the_guardian_page_and_the_report_card_show_the_same_numbers(): void
    {
        $term = $this->terms[0];
        $studentPage = $this->actingAs($this->studentUser)->get("/student/grades?term={$term->id}")->assertOk()->getContent();
        $guardianPage = $this->actingAs($this->guardianUser)->get("/guardian/grades?term={$term->id}&child={$this->student->id}")->assertOk()->getContent();
        $pdf = $this->reportCardHtml($this->guardianUser, "/guardian/grades/report-card?term={$term->id}&child={$this->student->id}");
        $studentPdf = $this->reportCardHtml($this->studentUser, "/student/grades/report-card?term={$term->id}");

        $expected = $this->pageSubjects($studentPage);
        $this->assertContains(['MYANMAR', '66', 'C+ · 2.33'], $expected);
        $this->assertContains(['SUPW', '80', 'PASS'], $expected);
        $this->assertNotContains('WORLD HISTORY', array_column($expected, 0)); // an option not taken is not listed
        $this->assertCount(11, $expected);

        $this->assertSame($expected, $this->pageSubjects($guardianPage));
        $this->assertSame($expected, $this->pdfSubjects($pdf));
        $this->assertSame($expected, $this->pdfSubjects($studentPdf));

        foreach ([$studentPage, $guardianPage] as $page) {
            $this->assertStringContainsString('Term GPA', $page);
            $this->assertMatchesRegularExpression('/Term GPA.*?3\.04/s', $page);
            $this->assertMatchesRegularExpression('/Class of pass.*?Second/s', $page);
        }
        $this->assertMatchesRegularExpression('/G\.P\.A<\/td>\s*<td>3\.04<\/td>\s*<td>Class: Second/', $pdf);

        // The dashboards show the same figure under the same label.
        $this->actingAs($this->studentUser)->get('/student/dashboard')->assertOk()->assertSeeInOrder(['Term GPA', '3.04']);
        $this->actingAs($this->guardianUser)->get('/guardian/dashboard')->assertOk()->assertSeeInOrder(['Latest GPA', '3.04']);
    }

    public function test_an_unlocked_term_is_provisional_and_a_locked_term_shows_its_frozen_figures(): void
    {
        $term = $this->terms[0];
        $this->actingAs($this->studentUser)->get("/student/grades?term={$term->id}")->assertOk()
            ->assertSee('Provisional')->assertSee('is not locked yet');
        $this->assertStringContainsString('Provisional', $this->reportCardHtml($this->studentUser, "/student/grades/report-card?term={$term->id}"));

        $principal = $this->makeStaff('principal', 'Principal');
        app(ResultFreezeService::class)->lock($term, $principal->user);
        // A changed mark can't reach a locked term's pages (only an approved grade change re-freezes it).
        $myanmar = Subject::where('code', 'MYAN')->whereNull('department_id')->value('id');
        Grade::whereHas('assessment.category', fn ($q) => $q->where('subject_id', $myanmar)->where('term_id', $term->id))
            ->where('student_id', $this->student->id)->update(['score' => 95]);

        $page = $this->actingAs($this->guardianUser)->get("/guardian/grades?term={$term->id}&child={$this->student->id}")->assertOk()
            ->assertSee('Final')->assertSee('is locked')->assertDontSee('is not locked yet');
        $this->assertContains(['MYANMAR', '66', 'C+ · 2.33'], $this->pageSubjects($page->getContent()));
        $pdf = $this->reportCardHtml($this->guardianUser, "/guardian/grades/report-card?term={$term->id}&child={$this->student->id}");
        $this->assertStringContainsString('Final', $pdf);
        $this->assertContains(['MYANMAR', '66', 'C+ · 2.33'], $this->pdfSubjects($pdf));
    }

    public function test_grade_10_shows_each_subjects_mark_and_letter_with_the_reason_there_is_no_point_total(): void
    {
        $ten = $this->levelSection('Grade 10-A', '10');
        $pupil = $this->pupil('Grade Ten Pupil', $ten);
        $this->setMarks($pupil, $ten, $this->terms[0], ['MORAL' => 90, 'MYAN' => 77, 'ENG' => 50, 'MATH' => 30, 'COMP' => 88, 'BIO' => 60, 'CHEM' => 70,
            'PHYS' => 80, 'WHIST' => 65, 'WGEO' => 45, 'MUSIC' => 85, 'SUPW' => 92, 'PE' => 68]);
        [$user] = $this->familyAccounts($pupil);

        $page = $this->actingAs($user)->get("/student/grades?term={$this->terms[0]->id}")->assertOk()
            ->assertSee('Term point total')
            ->assertSee('No point total or class of pass yet')
            ->assertSee('Q6')->assertSee('Q19');
        $subjects = $this->pageSubjects($page->getContent());
        $this->assertCount(13, $subjects);
        $this->assertContains(['MATHEMATICS', '30', 'Fail · 0 points'], $subjects);
        $this->assertContains(['MYANMAR', '77', 'B · 9 points'], $subjects);
        $this->assertMatchesRegularExpression('/Term point total.*?—/s', $page->getContent());
    }

    public function test_a_kg_to_9_average_is_shown_as_a_percentage(): void
    {
        $two = $this->levelSection('Grade 2-A', '2');
        $pupil = $this->pupil('Grade Two Pupil', $two);
        $this->setMarks($pupil, $two, $this->terms[0], self::KG_EXAMPLE);
        [$user, $guardian] = $this->familyAccounts($pupil);

        $this->actingAs($user)->get("/student/grades?term={$this->terms[0]->id}")->assertOk()
            ->assertSeeInOrder(['Term average', '90%'])->assertSeeInOrder(['Class of pass', 'First']);
        $this->actingAs($guardian)->get("/guardian/dashboard?child={$pupil->id}")->assertOk()->assertSeeInOrder(['Latest average', '90%']);
    }

    public function test_the_teacher_gradebook_previews_with_the_scheme_and_shows_frozen_figures_for_a_locked_term(): void
    {
        $teacher = $this->makeStaff('teacher', 'Teacher', 'gradebook-teacher@test.local');
        $myanmar = Subject::where('code', 'MYAN')->whereNull('department_id')->value('id');
        $assignment = TeachingAssignment::create(['section_id' => $this->section->id, 'subject_id' => $myanmar, 'teacher_id' => $teacher->id]);

        $response = $this->actingAs($teacher->user)->get("/teacher/gradebook?assignment={$assignment->id}")->assertOk()
            ->assertSee('Provisional');
        // The live preview gets the Grade 11-12 mark bands, decimals and rounding mode.
        $preview = $response->viewData('preview');
        $this->assertSame(['label' => 'A', 'min' => 90.0, 'max' => 100.0, 'fail' => false], $preview['bands'][0]);
        $this->assertSame([0, 'half_up', false], [$preview['decimals'], $preview['mode'], $preview['passFail']]);
        $this->assertNull($response->viewData('frozenRows'));

        $principal = $this->makeStaff('principal', 'Principal');
        app(ResultFreezeService::class)->lock($this->terms[0], $principal->user);

        $page = $this->actingAs($teacher->user)->get("/teacher/gradebook?assignment={$assignment->id}")->assertOk()->getContent();
        $this->assertStringContainsString('Final (frozen', $page);
        $this->assertMatchesRegularExpression('/data-result="'.$this->student->id.'">\s*<span>66<\/span>\s*<span[^>]*>\(C\+\)<\/span>/', $page);
    }
}
