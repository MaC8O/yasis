<?php

namespace App\Http\Requests\Admin;

use App\Models\ReportRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The Admin's report card and attendance rules. */
class UpdateReportRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route is limited to the Admin role
    }

    public function rules(): array
    {
        $rules = ['confirmed' => ['array'], 'confirmed.*' => [Rule::in(array_keys(ReportRule::RULES))]];
        foreach (ReportRule::RULES as $key => [, $options]) {
            $rules["values.{$key}"] = match (true) {
                $options === null => ['required', 'integer', 'min:1', 'max:100'],
                $options === 'text' => ['required', 'string', 'max:120'],
                $options === 'count' => ['required', 'integer', 'min:0', 'max:10'],
                default => ['required', Rule::in(array_keys($options))],
            };
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'values.min_attendance_pct.*' => 'The minimum attendance must be a whole number from 1 to 100.',
            'values.max_subject_absence_pct.*' => 'The subject absence limit must be a whole number from 1 to 100.',
            'values.lates_per_absence.*' => 'Late arrivals per absence must be a whole number from 0 to 10.',
            'values.examination_centre.*' => 'Enter the examination centre, up to 120 characters.',
        ];
    }
}
