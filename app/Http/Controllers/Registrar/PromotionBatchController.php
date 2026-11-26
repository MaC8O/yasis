<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrar\PreparePromotionBatchRequest;
use App\Models\PromotionBatch;
use App\Models\Section;
use App\Models\SystemSetting;
use App\Services\AuditService;
use App\Services\ResultService;
use Illuminate\Support\Facades\DB;

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

    public function create(Section $section, ResultService $results)
    {
        abort_unless($this->promotionWindowOpen(), 403, 'The promotion window is currently closed by the Principal.');

        return view('registrar.promotions.create', [
            'section' => $section->load(['enrollments' => fn ($q) => $q->where('status', '!=', 'Withdrawn')->with('student'), 'department']),
            'targetSections' => Section::where('id', '!=', $section->id)->orderBy('name')->get(),
            'recommendations' => $results->promotionRecommendations($section),
        ]);
    }

    public function store(PreparePromotionBatchRequest $request, Section $section, AuditService $audit)
    {
        abort_unless($this->promotionWindowOpen(), 403, 'The promotion window is currently closed by the Principal.');

        $data = $request->validated();
        $recommendations = $request->recommendations();

        DB::transaction(function () use ($request, $section, $audit, $data, $recommendations) {
            $batch = PromotionBatch::create([
                'from_section_id' => $section->id,
                'prepared_by' => $request->user()->id,
                'status' => 'Pending',
            ]);

            $overrides = [];
            foreach ($data['actions'] as $item) {
                $recommendation = $recommendations[(int) $item['student_id']] ?? ['action' => null, 'basis' => null];
                $differs = $recommendation['action'] !== null && $item['action'] !== $recommendation['action'];
                $batch->items()->create([
                    'student_id' => $item['student_id'],
                    'action' => $item['action'],
                    'to_section_id' => $item['action'] === 'Promote' ? ($item['to_section_id'] ?? null) : null,
                    'recommended_action' => $recommendation['action'],
                    'recommendation_basis' => $recommendation['basis'] === null ? null : mb_substr($recommendation['basis'], 0, 255),
                    'override_reason' => $differs ? $item['override_reason'] : null,
                ]);
                if ($differs) {
                    $overrides[] = [
                        'student_id' => (int) $item['student_id'],
                        'recommended' => $recommendation['action'],
                        'decision' => $item['action'],
                        'reason' => $item['override_reason'],
                    ];
                }
            }

            $audit->log($request->user(), 'Prepared promotion batch', 'PromotionBatch', $batch->id, ['overrides' => $overrides]);
        });

        return redirect()->route('registrar.promotions.index')
            ->with('status', "Promotion batch prepared for {$section->name} — awaiting VP + Principal co-approval.");
    }
}
