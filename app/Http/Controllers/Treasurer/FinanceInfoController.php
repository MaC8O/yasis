<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\SunFamilyStatement;
use App\Models\SunImportBatch;
use App\Models\SunLedgerLine;
use App\Models\SystemSetting;
use App\Services\AuditService;
use App\Support\FeeVisibility;
use Illuminate\Http\Request;

/** The Treasurer's guidance pages: preparing the Sun listing, and who sees what (with the leadership switch). */
class FinanceInfoController extends Controller
{
    public function sourcePrep()
    {
        return view('treasurer.info.source-prep');
    }

    /** Who sees what of the ledger, generated from FeeVisibility::rules(), with the leadership switch. */
    public function visibilityRules()
    {
        $published = SunImportBatch::current()->whereNotNull('published_at')->pluck('id');

        return view('treasurer.info.visibility-rules', [
            'rules' => FeeVisibility::rules(),
            'leadershipSeesRestricted' => FeeVisibility::leadershipSeesRestricted(),
            'counts' => [
                'lines' => SunLedgerLine::current()->count(),
                'hidden' => SunLedgerLine::current()->where('is_restricted', true)->count(),
                'statements' => SunFamilyStatement::whereIn('batch_id', $published)->whereNotNull('student_id')->count(),
            ],
        ]);
    }

    public function updatePolicy(Request $request, AuditService $audit)
    {
        $data = $request->validate(['leadership_sees_restricted' => ['required', 'boolean']]);
        $enabled = (bool) $data['leadership_sees_restricted'];

        SystemSetting::set(FeeVisibility::LEADERSHIP_SEES_RESTRICTED, $enabled ? '1' : '0');
        $audit->log($request->user(), $enabled
            ? 'Allowed leadership to see lines hidden from families'
            : 'Hid lines hidden from families from leadership too', 'SystemSetting', null);

        return back()->with('status', $enabled
            ? 'The Principal, VP and Registrar now see lines you hid from families, marked. Families still see them only as "Adjustments".'
            : 'Lines you hid from families now show to leadership only as "Adjustments", as they do to families.');
    }
}
