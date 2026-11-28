<?php

namespace App\Http\Requests\Admin;

use App\Models\SubjectOffering;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add a subject to a grade level's curriculum (route has {gradeLevel}) or edit a row (route has
 * {subjectOffering}). A compulsory row has no slot; an optional row must name its slot.
 */
class CurriculumRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // routes are already limited to the Admin role
    }

    protected function prepareForValidation(): void
    {
        $track = strtolower(trim((string) $this->input('track')));
        $hours = trim((string) $this->input('credit_hours'));

        $this->merge([
            'report_label' => trim((string) $this->input('report_label')),
            'is_compulsory' => $this->boolean('is_compulsory'),
            'counts_in_result' => $this->boolean('counts_in_result'),
            'confirmed' => $this->boolean('confirmed'),
            'track' => $track === '' ? null : $track,
            'credit_hours' => $hours === '' ? null : $hours,
        ]);
    }

    public function rules(): array
    {
        $levelId = $this->route('gradeLevel')?->id ?? $this->route('subjectOffering')?->grade_level_id;

        return [
            'subject_id' => $this->route('gradeLevel')
                ? ['required', Rule::exists('subjects', 'id'), Rule::unique('subject_offerings', 'subject_id')->where('grade_level_id', $levelId)]
                : ['prohibited'],
            'report_label' => ['required', 'string', 'max:60'],
            'is_compulsory' => ['boolean'],
            'track' => ['nullable', 'string', 'max:20', 'regex:/^[a-z][a-z_]*$/'],
            'credit_hours' => ['nullable', 'regex:/^\d{1,2}(\.\d)?$/'],
            'grading_mode' => ['required', Rule::in(array_keys(SubjectOffering::GRADING_MODES))],
            'counts_in_result' => ['boolean'],
            'confirmed' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('is_compulsory') && $this->input('track') !== null) {
                $validator->errors()->add('track', 'A compulsory subject belongs to no track. Clear the track, or make the subject optional.');
            }
            if (! $this->input('is_compulsory') && $this->input('track') === null) {
                $validator->errors()->add('track', 'An optional subject needs the track it belongs to (for example science or arts).');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'subject_id.unique' => 'This subject is already in this grade level\'s curriculum. If it was retired, restore it instead.',
            'track.regex' => 'Use a single lower-case word for the track, for example science or arts.',
            'credit_hours.regex' => 'Credit hours must be a number such as 2 or 1.5.',
        ];
    }
}
