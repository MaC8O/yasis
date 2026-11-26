<?php

namespace App\Http\Requests\Admin;

use App\Services\GradingSchemeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Replace one of a grading scheme's band sets (route has {gradingScheme} and {kind}), together
 * with the decimal places values are rounded to before the lookup and, for the class-of-pass set
 * of the point total and GPA, the top of the range. The set must cover the range with no gaps
 * or overlaps (App\Support\GradingBands).
 */
class SaveGradingBandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // routes are already limited to the Admin role
    }

    protected function prepareForValidation(): void
    {
        $bands = collect((array) $this->input('bands', []))
            ->filter(fn ($band) => is_array($band))
            ->map(fn ($band) => [
                'label' => trim((string) ($band['label'] ?? '')),
                'min' => trim((string) ($band['min'] ?? '')),
                'max' => trim((string) ($band['max'] ?? '')),
                'value' => trim((string) ($band['value'] ?? '')),
                'is_fail' => filter_var($band['is_fail'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            // A row left completely empty in the form is not a band.
            ->reject(fn ($band) => $band['label'] === '' && $band['min'] === '' && $band['max'] === '' && $band['value'] === '' && ! $band['is_fail'])
            ->values()->all();

        $max = trim((string) $this->input('result_max'));
        $this->merge([
            'bands' => $bands,
            'result_max' => $max === '' ? null : $max,
            'confirmed' => $this->boolean('confirmed'),
        ]);
    }

    public function rules(): array
    {
        return [
            'decimals' => ['required', 'integer', 'in:0,1,2'],
            'result_max' => ['nullable', 'string', 'max:6'],
            'bands' => ['array'],
            'bands.*.label' => ['string', 'max:20'],
            // The numbers are checked as a set in after(), as exact decimals.
            'bands.*.min' => ['nullable', 'string', 'max:6'],
            'bands.*.max' => ['nullable', 'string', 'max:6'],
            'bands.*.value' => ['nullable', 'string', 'max:5'],
            'bands.*.is_fail' => ['boolean'],
            'confirmed' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $problems = app(GradingSchemeService::class)->problems(
                $this->route('gradingScheme'),
                $this->route('kind'),
                $this->input('bands'),
                (int) $this->input('decimals'),
                $this->input('result_max'),
            );
            foreach ($problems as $problem) {
                $validator->errors()->add('bands', $problem);
            }
        }];
    }

    public function messages(): array
    {
        return [
            'decimals.in' => 'Round to 0, 1 or 2 decimal places.',
            'bands.*.label.max' => 'A band label can be at most 20 characters.',
        ];
    }
}
