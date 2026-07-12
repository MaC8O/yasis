<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\PromotionBatch;
use App\Models\Section;
use App\Models\SystemSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;

class PromotionBatchController extends Controller
{
    /**
     * Governance control (§3.6): the Principal opens/closes the promotion window;
     * batches can only be prepared while it is open.
     */
    protected function promotionWindowOpen(): bool
    {
        return SystemSetting::get('promotion_window_open', '1') === '1';
    }

    public function index()
    {
        return view('registrar.promotions.index', [
            'batches' => PromotionBatch::with(['fromSection.department', 'preparedBy.user', 'items'])->latest()->get(),
            'sections' => Section::with('department')->whereHas('academicYear', fn ($q) => $q->where('is_active', true))->orderBy('name')->get(),
            'promotionWindowOpen' => $this->promotionWindowOpen(),
        ]);
    }

    public function create(Section $section)
    {
        abort_unless($this->promotionWindowOpen(), 403, 'The promotion window is currently closed by the Principal.');

        return view('registrar.promotions.create', [
            'section' => $section->load(['enrollments.student', 'department']),
            'targetSections' => Section::where('id', '!=', $section->id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Section $section, AuditService $audit)
    {
        abort_unless($this->promotionWindowOpen(), 403, 'The promotion window is currently closed by the Principal.');

        $data = $request->validate([
            'actions' => ['required', 'array'],
            'actions.*.student_id' => ['required', 'exists:students,id'],
            'actions.*.action' => ['required', 'in:Promote,Retain,Graduate'],
            'actions.*.to_section_id' => ['nullable', 'exists:sections,id'],
        ]);

        $batch = PromotionBatch::create([
            'from_section_id' => $section->id,
            'prepared_by' => $request->user()->id,
            'status' => 'Pending',
        ]);

        foreach ($data['actions'] as $item) {
            $batch->items()->create([
                'student_id' => $item['student_id'],
                'action' => $item['action'],
                'to_section_id' => $item['action'] === 'Promote' ? ($item['to_section_id'] ?? null) : null,
            ]);
        }

        $audit->log($request->user(), 'Prepared promotion batch', 'PromotionBatch', $batch->id);

        return redirect()->route('registrar.promotions.index')
            ->with('status', "Promotion batch prepared for {$section->name} — awaiting VP + Principal co-approval.");
    }
}
