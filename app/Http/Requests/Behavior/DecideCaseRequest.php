<?php

namespace App\Http\Requests\Behavior;

use App\Models\BehaviorCase;
use Illuminate\Validation\Rule;

/** VP or Principal decision on an expulsion review case. */
class DecideCaseRequest extends ReasonRequest
{
    public function rules(): array
    {
        return parent::rules() + ['decision' => ['required', Rule::in(BehaviorCase::DECISIONS)]];
    }

    public function messages(): array
    {
        return parent::messages() + ['decision.in' => 'Choose Expel, Monitor or Close.'];
    }
}
