<?php

namespace App\Http\Requests\Registrar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add one subject-teacher assignment (or, on update, change only the teacher). */
class TeachingAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already limited by role
    }

    public function rules(): array
    {
        $teacher = ['required', Rule::exists('staff_profiles', 'id')->where('role_type', 'Teacher')];

        if ($this->route('teachingAssignment')) {
            return ['teacher_id' => $teacher];
        }

        return [
            'section_id' => ['required', Rule::exists('sections', 'id')->whereNull('archived_at')],
            'subject_id' => ['required', Rule::exists('subjects', 'id')],
            'teacher_id' => $teacher,
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_id.exists' => 'Only staff with the Teacher role can be assigned to teach a subject.',
            'section_id.exists' => 'That section does not exist or is archived.',
        ];
    }
}
