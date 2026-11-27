<?php

namespace App\Http\Requests\Principal;

use App\Http\Controllers\Principal\GovernanceController;
use App\Models\ResultFreeze;
use App\Services\GradebookTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The Principal's governance controls. Locking the current term again after an unlock may use the
 * rules it was locked with (the default) or the current rules, which needs a written reason.
 */
class UpdateGovernanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route is limited to the Principal role
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'relock_rules' => $this->input('relock_rules') ?: ResultFreeze::RULES_PREVIOUS,
            'relock_reason' => trim((string) $this->input('relock_reason')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'promotion_window_open' => ['nullable', 'boolean'],
            'transcript_issuance_enabled' => ['nullable', 'boolean'],
            'principal_may_assist_registration' => ['nullable', 'boolean'],
            'grade_lock' => ['nullable', 'boolean'],
            'results_released' => ['nullable', 'boolean'],
            'relock_rules' => [Rule::in([ResultFreeze::RULES_PREVIOUS, ResultFreeze::RULES_CURRENT])],
            'relock_reason' => ['nullable', 'required_if:relock_rules,'.ResultFreeze::RULES_CURRENT, 'string', 'max:1000'],
            'acknowledge_empty_categories' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Locking a term with KG1 to Grade 10 gradebooks that still have an empty items category needs
     * the Principal to acknowledge the warning first (phase 5). A subject whose Examinations
     * category is empty gets no final mark.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $term = GovernanceController::currentTermFor();
            if (! $term || $term->is_locked || ! $this->boolean('grade_lock') || $this->boolean('acknowledge_empty_categories')) {
                return;
            }
            $empty = app(GradebookTemplateService::class)->emptyItemCategories($term);
            if ($empty !== []) {
                $validator->errors()->add('acknowledge_empty_categories', count($empty).' '.(count($empty) === 1 ? 'gradebook has' : 'gradebooks have')
                    .' an empty category (listed under the grade lock). Tick "Lock anyway" to lock '.$term->name.'.');
            }
        }];
    }

    public function messages(): array
    {
        return ['relock_reason.required_if' => 'Say why the term is being recalculated with the current rules.'];
    }
}
