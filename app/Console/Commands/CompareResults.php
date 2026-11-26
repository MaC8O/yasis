<?php

namespace App\Console\Commands;

use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Services\GradeService;
use App\Services\ResultService;
use Illuminate\Console\Command;

/**
 * Phase 4a, read-only: for a section and term, prints what families see today (the department
 * grade scale, via GradeService::computeStudentResult) beside what phase 4b will show (the grading
 * schemes, via ResultService), so the switch can be checked on staging first. Stores nothing.
 */
class CompareResults extends Command
{
    protected $signature = 'results:compare
        {section : Section id, or its exact name in the active year}
        {term : Term id, or its name in that section\'s year (for example "First")}';

    protected $description = 'Show the old (department scale) and new (grading scheme) figures side by side for a section and term';

    public function handle(GradeService $grades, ResultService $results): int
    {
        $section = $this->section((string) $this->argument('section'));
        if (! $section) {
            $this->error("No section matches \"{$this->argument('section')}\".");

            return self::FAILURE;
        }
        $term = Term::where('academic_year_id', $section->academic_year_id)
            ->where(fn ($q) => $q->where('id', (int) $this->argument('term'))->orWhere('name', (string) $this->argument('term')))->first();
        if (! $term) {
            $this->error("No term \"{$this->argument('term')}\" in {$section->name}'s school year.");

            return self::FAILURE;
        }

        $new = $results->sectionTerm($section, $term);
        $this->line("<options=bold>{$section->name} · {$term->name}</> · grade level ".($section->gradeLevel?->name ?? 'none')
            .' · new figures '.($new->frozen ? 'from the freeze of '.$new->freeze->frozen_at->format('j M Y H:i') : 'calculated live')
            .($new->lockedWithoutFreeze ? ' (locked but not frozen yet)' : ''));

        $students = Student::whereIn('id', $new->students->keys())->get()->keyBy('id');
        foreach ($new->students as $studentId => $result) {
            $student = $students[$studentId];
            $rows = [];
            $oldGpas = [];
            foreach ($result->subjects as $subject) {
                if ($subject->mark === null) {
                    continue;
                }
                $old = $grades->computeStudentResult($student, $section, Subject::find($subject->subjectId), $term);
                if ($old['gpa'] !== null) {
                    $oldGpas[] = (float) $old['gpa'];
                }
                $rows[] = [
                    $subject->label,
                    $old['pct'] === null ? '—' : rtrim(rtrim(number_format($old['pct'], 2, '.', ''), '0'), '.'),
                    $old['letter'] ?? '—',
                    $subject->roundedMark ?? '—',
                    $subject->bandLabel === null ? '—' : $subject->bandLabel.($subject->bandValue !== null ? " ({$subject->bandValue})" : ''),
                ];
            }

            $this->newLine();
            $this->line("<options=bold>{$student->name}</>");
            $rows === []
                ? $this->line('No marks yet this term.')
                : $this->table(['Subject', 'Old %', 'Old letter', 'New mark', 'New letter (value)'], $rows);

            // The old pages averaged the non-zero GPA points of the subjects, unweighted.
            $oldGpa = array_filter($oldGpas);
            $this->line('Old "Term GPA": '.($oldGpa ? round(array_sum($oldGpa) / count($oldGpa), 2) : '—')
                .' | New: '.($result->isComplete()
                    ? "{$result->resultLabel} {$result->value}, ".($result->passed ? "{$result->classLabel}, Pass" : 'Fail').", position {$result->position} of {$result->rankedCount}"
                    : 'no result. '.implode(' ', $result->reasons)));
        }

        if ($new->students->isEmpty()) {
            $this->warn('No students in this section.');
        }

        return self::SUCCESS;
    }

    private function section(string $arg): ?Section
    {
        if (ctype_digit($arg)) {
            return Section::with('gradeLevel')->find((int) $arg);
        }

        return Section::with('gradeLevel')->where('name', $arg)
            ->whereHas('academicYear', fn ($q) => $q->where('is_active', true))->first();
    }
}
