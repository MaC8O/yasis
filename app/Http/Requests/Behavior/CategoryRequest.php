<?php

namespace App\Http\Requests\Behavior;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** VP → incident categories: add or edit. Categories are deactivated, never deleted. */
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is VP-only
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('behavior_categories', 'name')->ignore($this->route('category'))],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'A category with that name already exists.'];
    }
}
