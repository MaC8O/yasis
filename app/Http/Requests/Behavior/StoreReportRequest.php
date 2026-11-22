<?php

namespace App\Http\Requests\Behavior;

/** Teacher → Report incident, and Teacher → Edit own report (same fields). */
class StoreReportRequest extends ReportFieldsRequest {}
