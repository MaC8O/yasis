<?php

namespace App\Services;

use App\Exceptions\ActionRefused;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\GradebookTemplateCategory;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Notifications\GradebookNotice;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The fixed gradebook template (sdp_doc/YASIS_Academic_and_Fee_Rules.md §4.1) for the grade levels
 * whose grading scheme uses it (KG1 to Grade 10; Q9).
 *
 * - adopt(): puts one gradebook (section, subject, term) on the template. Categories whose names
 *   match a template category become it; a second match is merged into the first; items in
 *   categories that match nothing move by their own name and type; an Attendance category with
 *   hand-entered items is archived (Attendance is now calculated); missing categories are created.
 *   No grade is deleted: grades belong to items, and items only move. Locked terms are never
 *   touched. Every change is audited, and the teachers get a one-time notice listing moved items.
 * - update(): the Admin's rename/reweight, applied to the open terms' gradebooks too.
 * - emptyItemCategories(): for the Principal's lock warning.
 */
class GradebookTemplateService
{
    public function __construct(private AuditService $audit) {}

    /** @return Collection<int, GradebookTemplateCategory> */
    public function template(): Collection
    {
        return GradebookTemplateCategory::ordered()->get();
    }

    public function appliesTo(Section $section): bool
    {
        $section->loadMissing('gradeLevel.gradingScheme');

        return (bool) $section->gradeLevel?->gradingScheme?->uses_gradebook_template;
    }

    /** Template key a category name matches, or null. */
    public static function keyForCategoryName(string $name): ?string
    {
        $n = mb_strtolower($name);

        return match (true) {
            str_contains($n, 'attendance') => 'attendance',
            (bool) preg_match('/exam|mid-?term|final/', $n) => 'examinations',
            (bool) preg_match('/quiz|test/', $n) => 'quizzes',
            (bool) preg_match('/participation|conduct|behaviou?r|obedience/', $n) => 'participation',
            (bool) preg_match('/assignment|homework|project|class ?work|group/', $n) => 'assignments',
            default => null,
        };
    }

    /** Where an item from a category that matches nothing goes: by its name, then its type. */
    public static function keyForItem(Assessment $item): string
    {
        return match (true) {
            (bool) preg_match('/exam|mid-?term|final/', mb_strtolower($item->name)) => 'examinations',
            in_array($item->type, ['quiz', 'test'], true) => 'quizzes',
            default => 'assignments',
        };
    }

    /**
     * Put a gradebook on the template. Returns what changed (empty when it already was).
     *
     * @return array{renamed: list<string>, merged: list<string>, moved: list<array{item: string, from: string, to: string}>, archived: list<string>, created: list<string>}
     */
    public function adopt(Section $section, int $subjectId, Term $term, User $actor): array
    {
        $report = ['renamed' => [], 'merged' => [], 'moved' => [], 'archived' => [], 'created' => []];
        if ($term->is_locked || ! $this->appliesTo($section)) {
            return $report;
        }

        DB::transaction(function () use ($section, $subjectId, $term, $actor, &$report) {
            $template = $this->template()->keyBy('key');
            $categories = AssessmentCategory::where('section_id', $section->id)->where('subject_id', $subjectId)
                ->where('term_id', $term->id)->active()->with('assessments')->lockForUpdate()->orderBy('id')->get();
            $targets = $categories->whereNotNull('template_key')->keyBy('template_key');
            $now = now();

            $target = function (string $key) use (&$targets, $template, $section, $subjectId, $term, &$report) {
                if (! $targets->has($key)) {
                    $targets[$key] = AssessmentCategory::create([
                        'section_id' => $section->id, 'subject_id' => $subjectId, 'term_id' => $term->id,
                        'name' => $template[$key]->name, 'weight_pct' => $template[$key]->weight_pct, 'template_key' => $key,
                    ]);
                    $report['created'][] = $template[$key]->name;
                }

                return $targets[$key];
            };
            $move = function (Assessment $item, AssessmentCategory $from, string $key) use ($target, &$report) {
                $to = $target($key);
                $item->update(['category_id' => $to->id]);
                $report['moved'][] = ['item' => $item->name, 'from' => $from->name, 'to' => $to->name];
            };

            foreach ($categories->whereNull('template_key') as $category) {
                $key = self::keyForCategoryName($category->name);

                if ($key === 'attendance') {
                    if ($category->assessments->isNotEmpty() || $targets->has('attendance')) {
                        $category->update(['archived_at' => $now]);
                        $report['archived'][] = $category->name.($category->assessments->isNotEmpty()
                            ? ' (attendance is now calculated from attendance records; its '.$category->assessments->count().' items and their marks are kept but not counted)' : '');
                    } else {
                        $old = $category->name;
                        $category->update(['template_key' => 'attendance', 'name' => $template['attendance']->name, 'weight_pct' => $template['attendance']->weight_pct]);
                        $targets['attendance'] = $category;
                        $report['renamed'][] = $old.' → '.$template['attendance']->name;
                    }
                } elseif ($key !== null && ! $targets->has($key)) {
                    $old = $category->name;
                    $category->update(['template_key' => $key, 'name' => $template[$key]->name, 'weight_pct' => $template[$key]->weight_pct]);
                    $targets[$key] = $category;
                    $report['renamed'][] = $old.' → '.$template[$key]->name;
                } elseif ($key !== null) {
                    foreach ($category->assessments as $item) {
                        $move($item, $category, $key);
                    }
                    $category->update(['archived_at' => $now]);
                    $report['merged'][] = $category->name.' → '.$targets[$key]->name;
                } else {
                    foreach ($category->assessments as $item) {
                        $move($item, $category, self::keyForItem($item));
                    }
                    $category->update(['archived_at' => $now]);
                    $report['archived'][] = $category->name.' (empty after its items were moved)';
                }
            }

            foreach ($template as $key => $row) {
                $target($key);
            }

            if (array_filter($report)) {
                $subject = Subject::find($subjectId);
                $this->audit->log($actor, 'Moved gradebook onto the template', 'AssessmentCategory', null, [
                    'section' => $section->name, 'subject' => $subject?->code, 'term' => $term->name,
                ] + $report);

                if ($report['moved'] || array_filter($report['archived'], fn ($a) => str_contains($a, 'not counted'))) {
                    $this->notifyTeachers($section, $subject, $term, $report);
                }
            }
        });

        return $report;
    }

    /**
     * The Admin renames and reweights the template (weights total 100). The open terms' gradebooks
     * follow in the same transaction; locked terms keep theirs. `confirmed` clears the flags.
     *
     * @param  array<string, array{name: string, weight: string}>  $rows  keyed by template key
     */
    public function update(array $rows, bool $confirmed, User $actor): void
    {
        DB::transaction(function () use ($rows, $confirmed, $actor) {
            $template = GradebookTemplateCategory::lockForUpdate()->ordered()->get()->keyBy('key');
            $total = array_reduce($rows, fn (BigDecimal $sum, $r) => $sum->plus($r['weight']), BigDecimal::zero());
            if (! $total->isEqualTo(100)) {
                throw new ActionRefused('The weights must total 100%. They total '.$total->stripTrailingZeros().'%.');
            }

            $changes = [];
            foreach ($template as $key => $category) {
                $category->fill(['name' => $rows[$key]['name'], 'weight_pct' => $rows[$key]['weight']]);
                if ($confirmed) {
                    $category->pending_question = null;
                }
                if ($category->isDirty()) {
                    $changes[$key] = collect($category->getDirty())->map(fn ($to, $field) => ['from' => $category->getOriginal($field), 'to' => $to])->all();
                    $category->save();
                }
            }
            if ($changes === []) {
                throw new ActionRefused('Nothing was changed.');
            }

            // Every open-term gradebook category on the template follows it (counted, not MySQL's "changed rows").
            $synced = 0;
            foreach ($template as $key => $category) {
                $open = AssessmentCategory::where('template_key', $key)->active()->whereHas('term', fn ($q) => $q->where('is_locked', false));
                $synced += (clone $open)->count();
                $open->update(['name' => $category->name, 'weight_pct' => $category->weight_pct]);
            }

            $this->audit->log($actor, 'Updated gradebook template', 'GradebookTemplateCategory', null, [
                'changes' => $changes,
                'open_term_categories_updated' => $synced,
            ]);
        });
    }

    /**
     * Sections and subjects (in the term's school year, on the template) with an items category
     * that has no items yet, for the Principal's warning before locking the term.
     *
     * @return list<array{section: string, subject: string, categories: list<string>}>
     */
    public function emptyItemCategories(Term $term): array
    {
        $itemKeys = $this->template()->reject->isAttendance()->pluck('name', 'key');
        $sections = Section::where('academic_year_id', $term->academic_year_id)->whereNotNull('grade_level_id')
            ->with('gradeLevel.gradingScheme')->orderByGradeLevel()->orderBy('name')->get()
            ->filter(fn (Section $s) => $this->appliesTo($s));

        $list = [];
        foreach ($sections as $section) {
            $subjects = SubjectOffering::with('subject')->where('grade_level_id', $section->grade_level_id)->active()->ordered()->get();
            $counts = AssessmentCategory::where('section_id', $section->id)->where('term_id', $term->id)->active()
                ->whereNotNull('template_key')->withCount('activeAssessments')->get()
                ->groupBy('subject_id')->map(fn ($cats) => $cats->pluck('active_assessments_count', 'template_key'));

            foreach ($subjects as $offering) {
                $have = $counts->get($offering->subject_id, collect());
                $empty = $itemKeys->filter(fn ($name, $key) => (int) $have->get($key, 0) === 0)->values()->all();
                if ($empty) {
                    $list[] = ['section' => $section->name, 'subject' => $offering->report_label, 'categories' => $empty];
                }
            }
        }

        return $list;
    }

    private function notifyTeachers(Section $section, ?Subject $subject, Term $term, array $report): void
    {
        $lines = array_map(fn ($m) => "“{$m['item']}” moved from {$m['from']} to {$m['to']}", $report['moved']);
        foreach (array_filter($report['archived'], fn ($a) => str_contains($a, 'not counted')) as $archived) {
            $lines[] = "Archived: {$archived}";
        }

        $teacherIds = TeachingAssignment::where('section_id', $section->id)->where('subject_id', $subject?->id)->pluck('teacher_id');
        foreach (User::whereIn('id', $teacherIds)->get() as $teacher) {
            $teacher->notify(new GradebookNotice(
                $section->id, (int) $subject?->id, $term->id,
                "{$section->name} · ".($subject?->name ?? 'subject')." · {$term->name}: your gradebook now uses the school's fixed categories",
                $lines,
                route('teacher.gradebook.index', ['section' => $section->id, 'subject' => $subject?->id, 'term' => $term->id]),
            ));
        }
    }
}
