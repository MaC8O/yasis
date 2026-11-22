<?php

namespace App\Http\Requests\Behavior;

/** Shared rules for a points movement: whole points from 1 to 100 and a written reason. */
final class PointsAndReason
{
    public static function rules(): array
    {
        return [
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'reason' => ReasonRequest::REASON_RULES,
        ];
    }

    public static function messages(): array
    {
        return [
            'points.integer' => 'Points must be a whole number.',
            'points.min' => 'Points must be at least 1.',
            'points.max' => 'Points cannot be more than 100.',
        ] + ReasonRequest::reasonMessages();
    }
}
