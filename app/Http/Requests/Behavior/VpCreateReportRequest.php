<?php

namespace App\Http\Requests\Behavior;

/** VP → New report with deduction: the incident plus the points and the required reason. */
class VpCreateReportRequest extends ReportFieldsRequest
{
    public function rules(): array
    {
        return $this->reportRules() + PointsAndReason::rules();
    }

    public function messages(): array
    {
        return parent::messages() + PointsAndReason::messages();
    }
}
