<?php

namespace App\Http\Requests\LessonPlans;

use App\Models\LessonPlan;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Input for every lesson-plan write. One request class with per-action rules keeps the limits in
 * one place. Routes are permission-guarded and LessonPlanService re-checks access; the real file
 * type is checked there by LessonPlanFileInspector (the extension rule is only an early answer).
 */
class LessonPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $file = ['required', 'file', 'max:'.LessonPlan::MAX_FILE_KB, 'extensions:pdf,docx,pptx'];
        $text = [
            'title' => ['required', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];

        return match ($this->route()?->getName()) {
            'lesson-plans.store' => $text + [
                'teaching_assignment_id' => ['required', 'integer'],
                'term_id' => ['required', 'integer'],
                'file' => $file,
            ],
            'lesson-plans.versions.store' => ['file' => $file],
            'lesson-plans.update' => $text,
            'lesson-plans.reviews.store' => [
                'decision' => ['required', 'in:'.LessonPlan::APPROVED.','.LessonPlan::NEEDS_CHANGES],
                'comment' => ['nullable', 'string', 'max:1000', 'required_if:decision,'.LessonPlan::NEEDS_CHANGES],
                'version_id' => ['required', 'integer'],
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'file.max' => 'The file is larger than 10 MB.',
            'file.extensions' => 'Upload a PDF, Word (.docx) or PowerPoint (.pptx) file.',
            'file.uploaded' => 'The file could not be uploaded. It may be larger than 10 MB.',
            'teaching_assignment_id.required' => 'Choose the class this plan is for.',
            'term_id.required' => 'Choose the term.',
            'comment.required_if' => 'Say what needs to change. The teacher sees this comment.',
        ];
    }

    public function attributes(): array
    {
        return ['teaching_assignment_id' => 'class', 'term_id' => 'term'];
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return $note !== null && trim($note) !== '' ? trim($note) : null;
    }
}
