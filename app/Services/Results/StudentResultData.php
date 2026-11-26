<?php

namespace App\Services\Results;

/**
 * A student's result for a term or a year, calculated live or read from a freeze.
 *
 * status: `complete`, `incomplete` (a counted subject has no mark, or an elective is missing) or
 * `blocked` (no grade level or scheme, or the result waits on an unanswered blocking question).
 * Only a complete result has a value, class of pass, pass/fail and position.
 */
final class StudentResultData
{
    /**
     * @param  list<string>  $reasons
     * @param  list<SubjectResult>  $subjects
     */
    public function __construct(
        public readonly int $studentId,
        public readonly int $sectionId,
        public readonly ?int $gradeLevelId,
        public readonly ?int $schemeId,
        public readonly ?string $basis,
        public readonly ?string $resultLabel,
        public readonly string $status,
        public readonly array $reasons,
        public readonly ?string $value,
        public readonly ?string $unrounded,
        public readonly ?string $classLabel,
        public readonly ?bool $passed,
        public ?int $position,
        public ?int $rankedCount,
        public readonly ?string $recommendation,
        public readonly array $subjects,
        public readonly bool $frozen = false,
    ) {}

    public function subject(string $code): ?SubjectResult
    {
        foreach ($this->subjects as $subject) {
            if ($subject->code === $code) {
                return $subject;
            }
        }

        return null;
    }

    public function isComplete(): bool
    {
        return $this->status === 'complete';
    }

    /** The result as printed: "90%" for an average, "56" for a point total, "3.04" for a GPA; null without one. */
    public function display(): ?string
    {
        if (! $this->isComplete()) {
            return null;
        }

        return $this->basis === 'average' ? "{$this->value}%" : $this->value;
    }

    /** "Average 90 · First", "GPA 3.04 · Second", or the reasons there is no result. */
    public function summary(): string
    {
        return $this->isComplete()
            ? "{$this->resultLabel} {$this->value} · {$this->classLabel}"
            : implode(' ', $this->reasons);
    }
}
