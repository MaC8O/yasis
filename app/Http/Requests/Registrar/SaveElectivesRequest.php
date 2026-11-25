<?php

namespace App\Http\Requests\Registrar;

use App\Models\StudentElective;
use App\Services\ElectiveService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The Registrar's elective choices for one section: choices[studentId][slot] = subject_offering_id.
 * Every student must be actively enrolled in the section, every slot must exist for the section's
 * grade level, every choice must be one of that slot's active subjects, and a choice already
 * made can be changed but not cleared.
 */
class SaveElectivesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already limited to the Registrar role
    }

    public function rules(): array
    {
        return [
            'choices' => ['required', 'array'],
            'choices.*' => ['array'],
            'choices.*.*' => ['nullable', 'integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $section = $this->route('section');
            $service = app(ElectiveService::class);
            $slots = $service->slotsFor($section);
            $roster = $service->roster($section)->keyBy('id');
            $existing = StudentElective::where('academic_year_id', $section->academic_year_id)
                ->whereIn('student_id', $roster->keys())->get()
                ->keyBy(fn ($e) => $e->student_id.'|'.$e->elective_slot);

            foreach ($this->input('choices', []) as $studentId => $choices) {
                $student = $roster->get((int) $studentId);
                if (! $student) {
                    $validator->errors()->add('choices', 'One of the students is not actively enrolled in this section. Reload the page and try again.');

                    return;
                }

                foreach ($choices as $slot => $offeringId) {
                    $options = $slots->get((string) $slot);
                    if (! $options) {
                        $validator->errors()->add('choices', "There is no elective slot {$slot} for this grade level. Reload the page and try again.");

                        return;
                    }
                    if ($offeringId === null || $offeringId === '') {
                        if ($existing->has($student->id.'|'.$slot)) {
                            $validator->errors()->add("choices.{$student->id}.{$slot}", "{$student->name}: a slot {$slot} choice can be changed but not cleared.");
                        }

                        continue;
                    }
                    if (! $options->contains('id', (int) $offeringId)) {
                        $validator->errors()->add("choices.{$student->id}.{$slot}", "{$student->name}: choose one of slot {$slot}'s subjects.");
                    }
                }
            }
        }];
    }
}
