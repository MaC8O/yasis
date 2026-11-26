<?php

namespace App\Services\Results;

/**
 * One subject in a student's result: its curriculum row (or a subject taught outside the
 * curriculum), its mark with two decimals, the mark as printed, and the band it falls in.
 */
final class SubjectResult
{
    public function __construct(
        public readonly int $subjectId,
        public readonly string $code,
        public readonly string $name,
        public readonly string $label,
        public readonly int $sortOrder,
        public readonly ?int $offeringId,
        public readonly bool $inCurriculum,
        public readonly bool $compulsory,
        public readonly ?string $slot,
        public readonly bool $taken,
        public readonly bool $counted,
        public readonly ?string $gradingMode,
        public readonly ?string $creditHours,
        public readonly ?string $mark,
        public readonly ?string $roundedMark,
        public readonly ?string $bandLabel,
        public readonly ?string $bandValue,
        public readonly ?bool $isFail,
        public readonly ?string $pendingQuestion,
    ) {}
}
