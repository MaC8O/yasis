<?php

namespace App\Http\Requests\Admin;

use App\Models\GradebookTemplateCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** The Admin renames and reweights the school's gradebook template; the weights total 100. */
class UpdateGradebookTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route is limited to the Admin role
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'categories' => collect((array) $this->input('categories', []))->map(fn ($row) => [
                'name' => trim((string) ($row['name'] ?? '')),
                'weight' => trim((string) ($row['weight'] ?? '')),
            ])->all(),
            'confirmed' => $this->boolean('confirmed'),
        ]);
    }

    public function rules(): array
    {
        $rules = ['categories' => ['required', 'array'], 'confirmed' => ['boolean']];
        foreach (GradebookTemplateCategory::pluck('key') as $key) {
            $rules["categories.{$key}.name"] = ['required', 'string', 'max:80'];
            $rules["categories.{$key}.weight"] = ['required', 'regex:/^\d{1,3}(\.\d{1,2})?$/'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $names = collect($this->input('categories'))->pluck('name')->map(fn ($n) => mb_strtolower($n));
            if ($names->duplicates()->isNotEmpty()) {
                $validator->errors()->add('categories', 'Each category needs a different name.');
            }
            foreach ((array) $this->input('categories') as $key => $row) {
                if (is_numeric($row['weight'] ?? null) && (float) $row['weight'] <= 0) {
                    $validator->errors()->add("categories.{$key}.weight", 'A weight must be above 0.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return ['categories.*.weight.regex' => 'Use a weight such as 10 or 12.5.'];
    }
}
