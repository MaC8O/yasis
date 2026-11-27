<?php

namespace App\Http\Requests\Treasurer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * §10: the Treasurer's fee schedule grid: items[item id][amount] in whole kyats and, for tuition,
 * items[item id][instalments]. Once the schedule is published, a short reason is required.
 */
class SaveFeeScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route is limited to the Treasurer role
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.amount' => ['required', 'integer', 'min:0', 'max:999999999'],
            'items.*.instalments' => ['nullable', 'integer', 'min:1', 'max:12'],
            'confirmed' => ['array'],
            'confirmed.*' => ['string', 'regex:/^Q\d+$/'],
            'reason' => [Rule::requiredIf(fn () => $this->route('feeSchedule')?->isPublished()), 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.amount.*' => 'Each amount is a whole number of kyats, 0 or more.',
            'items.*.instalments.*' => 'Instalments are a whole number from 1 to 12.',
            'reason.required' => 'Families can see this schedule, so give a short reason for the change.',
        ];
    }
}
