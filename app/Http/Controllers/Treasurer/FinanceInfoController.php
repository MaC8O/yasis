<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;

class FinanceInfoController extends Controller
{
    public function sourcePrep()
    {
        return view('treasurer.info.source-prep');
    }

    public function visibilityRules()
    {
        return view('treasurer.info.visibility-rules');
    }
}
