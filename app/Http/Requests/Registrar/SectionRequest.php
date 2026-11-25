<?php

namespace App\Http\Requests\Registrar;

use App\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * §7.5 create/edit a section. On edit, every field is optional so the older
 * homeroom/capacity-only form keeps working; whatever is sent is validated.
 */
class SectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already limited to the Registrar role
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('post') && ! $this->filled('academic_year_id')) {
            $this->merge(['academic_year_id' => AcademicYear::where('is_active', true)->value('id')]);
        }
    }

    public function rules(): array
    {
        $section = $this->route('section');
        $editing = $section !== null;
        $required = $editing ? 'sometimes' : 'required';

        $yearId = $editing ? $section->academic_year_id : $this->input('academic_year_id');
        $departmentId = $this->input('department_id', $section?->department_id);

        return [
            'name' => [$required, 'string', 'max:255',
                Rule::unique('sections', 'name')
                    ->where('academic_year_id', $yearId)
                    ->where('department_id', $departmentId)
                    ->ignore($section?->id),
            ],
            'department_id' => [$required, Rule::exists('departments', 'id')],
            // Required for a new section. On edit, a level once set can't be cleared; a section the
            // backfill couldn't classify (e.g. Nursery) can still be saved without one.
            'grade_level_id' => [
                $editing ? 'sometimes' : 'required',
                $editing && $section->grade_level_id === null ? 'nullable' : 'required',
                Rule::exists('grade_levels', 'id')->whereNull('archived_at'),
            ],
            'academic_year_id' => $editing ? ['prohibited'] : ['required', Rule::exists('academic_years', 'id')],
            'homeroom_teacher_id' => ['nullable', Rule::exists('staff_profiles', 'id')->where('role_type', 'Teacher')],
            'capacity' => [$editing ? 'sometimes' : 'required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A section with this name already exists for that department in that academic year.',
            'grade_level_id.required' => 'Choose the grade level for this section.',
            'grade_level_id.exists' => 'Choose a grade level from the list.',
            'homeroom_teacher_id.exists' => 'The homeroom teacher must be a staff member with the Teacher role.',
            'academic_year_id.required' => 'There is no active academic year — choose one.',
            'academic_year_id.prohibited' => 'A section cannot be moved to another academic year.',
        ];
    }
}
