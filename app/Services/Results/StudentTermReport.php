<?php

namespace App\Services\Results;

use App\Models\Section;
use App\Models\Term;
use Illuminate\Support\Carbon;

/**
 * One student's term as every page shows it: the student and guardian dashboards and grade pages,
 * and the report-card PDF all read this, so families and staff see the same numbers. Frozen when
 * the term is locked; otherwise calculated live and provisional.
 */
final class StudentTermReport
{
    public function __construct(
        public readonly Term $term,
        public readonly ?Section $section,
        public readonly ?StudentResultData $result,
        public readonly bool $frozen,
        public readonly bool $lockedWithoutFreeze,
        public readonly ?Carbon $frozenAt,
    ) {}

    public function provisional(): bool
    {
        return ! $this->frozen;
    }

    /** The subjects to list, in report-card order: the curriculum subjects the student takes, then any taught outside it. */
    public function subjects(): array
    {
        if (! $this->result) {
            return [];
        }

        return array_values(array_filter($this->result->subjects, fn (SubjectResult $s) => $s->taken));
    }

    /** A subject's letter as every page prints it: "B · 3.00" (grade value), "A · 10 points", "PASS", or null. */
    public function bandText(SubjectResult $subject): ?string
    {
        if ($subject->bandLabel === null) {
            return null;
        }
        if ($subject->bandValue === null) {
            return $subject->bandLabel;
        }

        return $this->result?->basis === 'point_total'
            ? $subject->bandLabel.' · '.rtrim(rtrim($subject->bandValue, '0'), '.').' '.((float) $subject->bandValue === 1.0 ? 'point' : 'points')
            : $subject->bandLabel.' · '.$subject->bandValue;
    }

    /** "Average", "Point total" or "GPA" (Q12), or a neutral word when there is no scheme. */
    public function resultLabel(): string
    {
        return $this->result?->resultLabel ?? 'Result';
    }

    /** The result as printed ("77%", "56", "3.04"), or null when there is none. */
    public function resultDisplay(): ?string
    {
        return $this->result?->display();
    }

    /** Why there is no overall result yet, if there isn't one. */
    public function reasons(): array
    {
        if (! $this->section) {
            return ["Not enrolled in a class in {$this->term->name}'s school year."];
        }
        if (! $this->result) {
            return ['No result was stored for this student when the term was locked.'];
        }

        return $this->result->isComplete() ? [] : $this->result->reasons;
    }

    public function statusNote(): string
    {
        if ($this->frozen) {
            return 'Final: '.$this->term->name.' is locked'.($this->frozenAt ? ' (results frozen '.$this->frozenAt->format('j M Y').')' : '').'.';
        }

        return 'Provisional: '.$this->term->name.' is not locked yet, so these figures can still change.';
    }
}
