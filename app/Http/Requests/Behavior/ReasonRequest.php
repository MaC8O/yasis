<?php

namespace App\Http\Requests\Behavior;

use Illuminate\Foundation\Http\FormRequest;

/** Any action whose only input is a required written reason: dismiss, void, withdraw an exit request. */
class ReasonRequest extends FormRequest
{
    public const REASON_RULES = ['required', 'string', 'min:5', 'max:1000'];

    public function authorize(): bool
    {
        return true; // routes are role-guarded; the services re-check
    }

    public function rules(): array
    {
        return ['reason' => self::REASON_RULES];
    }

    public function messages(): array
    {
        return self::reasonMessages();
    }

    public static function reasonMessages(): array
    {
        return [
            'reason.required' => 'A written reason is required.',
            'reason.min' => 'Write a reason of at least 5 characters.',
        ];
    }

    public function reason(): string
    {
        return trim($this->validated('reason'));
    }
}
