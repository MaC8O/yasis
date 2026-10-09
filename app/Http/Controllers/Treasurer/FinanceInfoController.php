<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\ImportedFeeRecord;
use App\Models\SystemSetting;
use App\Services\AuditService;
use App\Support\FeeVisibility;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FinanceInfoController extends Controller
{
    public function sourcePrep()
    {
        return view('treasurer.info.source-prep');
    }

    /**
     * §9.2: downloadable import template — headers exactly as FeeRecordsImport
     * consumes them, with the ISMS student-ID matching key first and the student's
     * full name beside it so the finance office can read the file at a glance.
     */
    public function importTemplate(): Response
    {
        $csv = "student_id,student_name,date,description,amount,balance,status,restricted\n"
            ."YAS-2026-0001,Saw Htoo Aung,2026-07-01,Term 1 tuition,150000,0,Paid,\n"
            ."YAS-2026-0002,Su Su Aung,2026-07-01,Term 1 tuition,150000,50000,Partial,\n"
            ."YAS-2026-0003,Naw Eh Ler,2026-07-01,SDA employee allowance,150000,150000,Outstanding,yes\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="fee_import_template.csv"',
        ]);
    }

    /** §9.8: who sees what (generated from the enforced rules), plus the per-import confirmations. */
    public function visibilityRules()
    {
        $batches = ImportBatch::with('restrictedConfirmedBy')
            ->withCount([
                'importedFeeRecords',
                'importedFeeRecords as restricted_count' => fn ($q) => $q->where('is_restricted', true),
                'importedFeeRecords as held_count' => fn ($q) => $q->where('is_held', true),
            ])
            ->orderByDesc('uploaded_at')->get();

        // What each audience can see right now — counted with the same scope the portals use.
        $exposure = collect([FeeVisibility::TREASURER, FeeVisibility::LEADERSHIP, FeeVisibility::FAMILY])
            ->mapWithKeys(fn ($audience) => [$audience => ImportedFeeRecord::query()->visibleTo($audience)->count()]);

        return view('treasurer.info.visibility-rules', [
            'rules' => FeeVisibility::rules(),
            'exposure' => $exposure,
            'leadershipSeesRestricted' => FeeVisibility::leadershipSeesRestricted(),
            'batches' => $batches,
        ]);
    }

    /** Policy switch: may leadership (read-only) see restricted SDA rows? Off by default (§2.2). */
    public function updatePolicy(Request $request, AuditService $audit)
    {
        $data = $request->validate(['leadership_sees_restricted' => ['required', 'boolean']]);
        $enabled = (bool) $data['leadership_sees_restricted'];

        SystemSetting::set(FeeVisibility::LEADERSHIP_SEES_RESTRICTED, $enabled ? '1' : '0');
        $audit->log($request->user(), $enabled
            ? 'Allowed leadership to see restricted fee rows'
            : 'Hid restricted fee rows from leadership', 'SystemSetting', null);

        return back()->with('status', $enabled
            ? 'Leadership can now see restricted (SDA) rows, flagged. Guardians still never see them.'
            : 'Restricted (SDA) rows are now hidden from leadership as well as guardians.');
    }

    /** §9.8: (re)confirm that a batch's restricted rows are correctly classified. */
    public function confirmRestricted(Request $request, ImportBatch $importBatch, AuditService $audit)
    {
        $importBatch->update(['restricted_confirmed_at' => now(), 'restricted_confirmed_by' => $request->user()->id]);
        $audit->log($request->user(), 'Confirmed restricted classification for fee batch', 'ImportBatch', $importBatch->id);

        return back()->with('status', "Restricted classification confirmed for {$importBatch->period}.");
    }
}
