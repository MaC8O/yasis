<?php

namespace App\Http\Requests\Registrar;

use App\Models\Student;
use App\Services\ResultService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Prepare a promotion batch for a section (route has {section}). Section 9: a decision that differs
 * from the calculated recommendation needs a reason.
 */
class PreparePromotionBatchRequest extends FormRequest
{
    /** @var array<int, array{action: ?string, basis: string}>|null */
    private ?array $recommendations = null;

    public function authorize(): bool
    {
        return true; // the route is limited to the Registrar role
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'actions' => collect((array) $this->input('actions', []))->map(fn ($row) => is_array($row) ? array_merge($row, [
                'override_reason' => trim((string) ($row['override_reason'] ?? '')) ?: null,
            ]) : $row)->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'actions' => ['required', 'array'],
            'actions.*.student_id' => ['required', 'exists:students,id'],
            'actions.*.action' => ['required', 'in:Promote,Retain,Graduate'],
            'actions.*.to_section_id' => ['nullable', 'exists:sections,id'],
            'actions.*.override_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $names = Student::whereIn('id', collect($this->input('actions'))->pluck('student_id'))->pluck('name', 'id');
            foreach ($this->input('actions') as $i => $row) {
                $recommended = $this->recommendations()[(int) $row['student_id']]['action'] ?? null;
                if ($recommended !== null && $row['action'] !== $recommended && $row['override_reason'] === null) {
                    $validator->errors()->add("actions.{$i}.override_reason",
                        "{$names[$row['student_id']]}: the recommendation is {$recommended}. Say why you chose {$row['action']}.");
                }
            }
        }];
    }

    /** @return array<int, array{action: ?string, basis: string}> student id => recommendation */
    public function recommendations(): array
    {
        return $this->recommendations ??= app(ResultService::class)->promotionRecommendations($this->route('section'));
    }
}
