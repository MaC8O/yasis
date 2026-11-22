<?php

namespace App\Http\Requests\Behavior;

use Illuminate\Foundation\Http\FormRequest;

/** The incident fields shared by every report form (teacher create/edit, VP create). */
abstract class ReportFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // routes are role-guarded; the service re-checks scope and ownership
    }

    protected function reportRules(): array
    {
        return [
            'incident_date' => ['required', 'date', 'before_or_equal:today', 'after:'.now()->subYear()->toDateString()],
            'behavior_category_id' => ['required', 'integer', 'exists:behavior_categories,id'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'is_office_referral' => ['sometimes', 'boolean'],
        ];
    }

    public function rules(): array
    {
        return $this->reportRules();
    }

    public function messages(): array
    {
        return [
            'incident_date.before_or_equal' => 'The incident date cannot be in the future.',
            'incident_date.after' => 'The incident date must be within the last year.',
            'description.min' => 'Describe what happened in at least 10 characters.',
            'behavior_category_id.required' => 'Choose a category.',
        ];
    }

    public function validatedReport(): array
    {
        return [
            'incident_date' => $this->validated('incident_date'),
            'behavior_category_id' => (int) $this->validated('behavior_category_id'),
            'description' => trim($this->validated('description')),
            'is_office_referral' => $this->boolean('is_office_referral'),
        ];
    }
}
