<?php

namespace App\Http\Requests\Registrar;

use App\Exceptions\ActionRefused;
use App\Models\Student;
use App\Models\SystemSetting;
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

    /**
     * The route is limited to the Registrar role. A closed promotion window is a 403, as before.
     * A year whose results aren't frozen yet is refused with the reason, before any validation:
     * the decisions would be made against provisional results.
     */
    public function authorize(): bool
    {
        if (SystemSetting::get('promotion_window_open', '1') !== '1') {
            return false;
        }
        if ($blocker = app(ResultService::class)->promotionBlocker($this->route('section'))) {
            throw new ActionRefused($blocker);
        }

        return true;
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
