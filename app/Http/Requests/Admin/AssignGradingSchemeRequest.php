<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Choose the grading scheme a grade level uses (route has {gradeLevel}). */
class AssignGradingSchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // routes are already limited to the Admin role
    }

    public function rules(): array
    {
        return [
            'grading_scheme_id' => ['required', 'integer', Rule::exists('grading_schemes', 'id')],
        ];
    }

    public function messages(): array
    {
        return ['grading_scheme_id.required' => 'Choose a grading scheme.'];
    }
}
