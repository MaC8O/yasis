<?php

namespace App\Http\Requests\Behavior;

use App\Services\Behavior\BehaviorPolicySettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Behavior policy thresholds: whole numbers 1–99, and Warning > Final warning > Expulsion review.
 * Also validates the preview request, so a preview can never show numbers that could not be saved.
 */
class UpdatePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route requires the behavior.policy.manage permission
    }

    public function rules(): array
    {
        $rule = ['required', 'integer', 'between:1,99'];

        return ['warning' => $rule, 'final_warning' => $rule, 'expulsion_review' => $rule];
    }

    public function attributes(): array
    {
        return ['warning' => 'Warning', 'final_warning' => 'Final warning', 'expulsion_review' => 'Expulsion review'];
    }

    public function messages(): array
    {
        return [
            '*.integer' => 'The :attribute threshold must be a whole number.',
            '*.between' => 'The :attribute threshold must be between 1 and 99.',
            '*.required' => 'Enter a :attribute threshold.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $w = (int) $this->input('warning');
            $f = (int) $this->input('final_warning');
            $e = (int) $this->input('expulsion_review');

            if ($w <= $f) {
                $validator->errors()->add('final_warning', "The Final warning threshold ({$f}) must be lower than the Warning threshold ({$w}).");
            }
            if ($f <= $e) {
                $validator->errors()->add('expulsion_review', "The Expulsion review threshold ({$e}) must be lower than the Final warning threshold ({$f}).");
            }
        }];
    }

    /** @return array<string, int> keyed by stage */
    public function thresholds(): array
    {
        return collect(BehaviorPolicySettings::FIELDS)
            ->mapWithKeys(fn ($stage, $field) => [$stage => (int) $this->validated($field)])->all();
    }
}
