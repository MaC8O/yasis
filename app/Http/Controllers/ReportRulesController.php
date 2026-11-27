<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\UpdateReportRulesRequest;
use App\Models\ReportRule;
use App\Services\ReportRuleService;
use Illuminate\Http\Request;

/**
 * Report card and attendance rules (§7.1, §8): edited by the Admin (admin.report-rules.*), read-only
 * for the Principal (principal.report-rules.index).
 */
class ReportRulesController extends Controller
{
    public function index(Request $request)
    {
        $portal = strtok($request->route()->getName(), '.');

        return view('report-rules.index', [
            'portal' => $portal,
            'readOnly' => $portal !== 'admin',
            'rules' => ReportRule::orderBy('id')->get(),
        ]);
    }

    public function update(UpdateReportRulesRequest $request, ReportRuleService $service)
    {
        $data = $request->validated();

        $service->update(array_map(fn ($v) => trim((string) $v), $data['values']), $data['confirmed'] ?? [], $request->user());

        return back()->with('status', 'Report card rules saved.');
    }
}
