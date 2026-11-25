<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Rename a grade level. Only the display name changes; the code stays fixed. */
class RenameGradeLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already limited to the Admin role
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('grade_levels', 'name')->ignore($this->route('gradeLevel'))],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'A grade level with this name already exists.'];
    }
}
