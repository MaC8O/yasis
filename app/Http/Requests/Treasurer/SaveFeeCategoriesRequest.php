<?php

namespace App\Http\Requests\Treasurer;

use Illuminate\Foundation\Http\FormRequest;

/** §5 / T4: category visibility defaults, keyword rules, and one new keyword. */
class SaveFeeCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route is limited to the Treasurer role
    }

    public function rules(): array
    {
        return [
            'restricted' => ['array'],
            'restricted.*' => ['boolean'],
            'rules' => ['array'],
            'rules.*.keyword' => ['nullable', 'string', 'max:60'],
            'rules.*.active' => ['nullable', 'boolean'],
            'new.category_id' => ['nullable', 'integer', 'exists:fee_categories,id'],
            'new.keyword' => ['nullable', 'string', 'max:60', 'required_with:new.category_id'],
        ];
    }
}
