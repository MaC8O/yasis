<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A new grade level. Its code is fixed once created, so it is validated strictly here. */
class StoreGradeLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already limited to the Admin role
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/', Rule::unique('grade_levels', 'code')],
            'name' => ['required', 'string', 'max:60', Rule::unique('grade_levels', 'name')],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters and digits only, for example KG1 or 7.',
            'code.unique' => 'A grade level with this code already exists.',
            'name.unique' => 'A grade level with this name already exists.',
        ];
    }
}
