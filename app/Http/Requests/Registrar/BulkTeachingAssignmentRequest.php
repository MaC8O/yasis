<?php

namespace App\Http\Requests\Registrar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Section detail page: set the teacher for several subjects in one save (blank = unassigned). */
class BulkTeachingAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already limited to the Registrar role
    }

    public function rules(): array
    {
        return [
            'teachers' => ['required', 'array'],
            'teachers.*' => ['nullable', Rule::exists('staff_profiles', 'id')->where('role_type', 'Teacher')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $ids = array_keys((array) $this->input('teachers', []));
            $valid = \App\Models\Subject::whereIn('id', $ids)->count();
            if ($valid !== count($ids)) {
                $validator->errors()->add('teachers', 'One or more subjects no longer exist. Refresh the page and try again.');
            }
            if ($this->route('section')?->isArchived()) {
                $validator->errors()->add('teachers', 'This section is archived. Restore it before changing its teachers.');
            }
        });
    }

    public function messages(): array
    {
        return ['teachers.*.exists' => 'Only staff with the Teacher role can be assigned to teach a subject.'];
    }
}
