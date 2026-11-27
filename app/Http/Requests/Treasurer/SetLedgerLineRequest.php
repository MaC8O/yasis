<?php

namespace App\Http\Requests\Treasurer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The Treasurer's override on one ledger line: its category, or whether families see it. */
class SetLedgerLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route is limited to the Treasurer role
    }

    public function rules(): array
    {
        return [
            'category' => ['required_without:restricted', 'nullable', 'string', Rule::exists('fee_categories', 'key')],
            'restricted' => ['required_without:category', 'nullable', 'boolean'],
        ];
    }
}
