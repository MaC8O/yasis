<?php

namespace App\Http\Requests\Admin;

use App\Models\GradingScheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A grading scheme's own settings: name, rounding mode and whether one failed subject fails the year. */
class UpdateGradingSchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // routes are already limited to the Admin role
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'confirmed' => $this->boolean('confirmed'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('grading_schemes', 'name')->ignore($this->route('gradingScheme'))],
            'rounding_mode' => ['required', Rule::in(array_keys(GradingScheme::ROUNDING_MODES))],
            'subject_fail_rule' => ['required', Rule::in(array_keys(GradingScheme::FAIL_RULES))],
            'confirmed' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Another grading scheme already has this name.'];
    }
}
