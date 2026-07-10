<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class FinanceInfoController extends Controller
{
    public function sourcePrep()
    {
        return view('treasurer.info.source-prep');
    }

    /**
     * §9.2: downloadable import template — headers exactly as FeeRecordsImport
     * consumes them, with the ISMS student-ID matching key first.
     */
    public function importTemplate(): Response
    {
        $csv = "student_id,date,amount,balance,status,restricted\n"
            ."YAS-2026-0001,2026-07-01,150000,0,Paid,\n"
            ."YAS-2026-0002,2026-07-01,150000,50000,Partial,\n"
            ."YAS-2026-0003,2026-07-01,150000,150000,Outstanding,yes\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="fee_import_template.csv"',
        ]);
    }

    public function visibilityRules()
    {
        return view('treasurer.info.visibility-rules');
    }
}
