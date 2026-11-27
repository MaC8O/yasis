<?php

namespace App\Console\Commands;

use App\Models\AssessmentCategory;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use App\Services\GradebookTemplateService;
use Illuminate\Console\Command;

/**
 * Phase 5 deploy step: put every open-term gradebook in KG1 to Grade 10 on the school's fixed
 * template (GradebookTemplateService::adopt). Locked terms are never touched; no grade is deleted.
 * Safe to run again. Audited under the given Admin or Principal; affected teachers get a one-time
 * notice listing moved items.
 */
class AdoptGradebookTemplate extends Command
{
    protected $signature = 'gradebook:adopt-template
        {--actor= : Email of the Admin or Principal the changes are recorded under}';

    protected $description = 'Move open-term KG1 to Grade 10 gradebooks onto the fixed gradebook template';

    public function handle(GradebookTemplateService $templates): int
    {
        $actor = User::where('email', (string) $this->option('actor'))->first();
        if (! $actor || ! $actor->hasAnyRole(['admin', 'principal'])) {
            $this->error('Give --actor the email of an Admin or Principal account; the changes are audited under it.');

            return self::FAILURE;
        }

        $gradebooks = AssessmentCategory::query()
            ->whereHas('term', fn ($q) => $q->where('is_locked', false))
            ->select('section_id', 'subject_id', 'term_id')->distinct()->get();

        $changed = 0;
        $moved = 0;
        foreach ($gradebooks as $g) {
            $section = Section::find($g->section_id);
            if (! $section || ! $templates->appliesTo($section)) {
                continue;
            }
            $report = $templates->adopt($section, $g->subject_id, Term::findOrFail($g->term_id), $actor);
            if (array_filter($report)) {
                $changed++;
                $moved += count($report['moved']);
            }
        }

        $this->info($changed === 0
            ? 'Nothing to change: every open-term KG1 to Grade 10 gradebook already uses the template.'
            : "Moved {$changed} ".($changed === 1 ? 'gradebook' : 'gradebooks')." onto the template ({$moved} ".($moved === 1 ? 'item' : 'items').' moved).');

        return self::SUCCESS;
    }
}
