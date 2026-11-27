<?php

namespace App\Http\Controllers;

use App\Http\Requests\Treasurer\SaveFeeScheduleRequest;
use App\Models\AcademicYear;
use App\Models\FeeSchedule;
use App\Services\FeeScheduleService;
use Illuminate\Http\Request;

/**
 * §10 fee schedule: managed by the Treasurer (treasurer.fee-schedule.*); read-only for the
 * Principal, VP and Registrar, who already see fees (<role>.fee-schedule.index).
 */
class FeeScheduleController extends Controller
{
    public function index(Request $request, FeeScheduleService $schedules)
    {
        $portal = strtok($request->route()->getName(), '.');
        $years = AcademicYear::orderByDesc('year_label')->get();
        $year = $years->firstWhere('id', $request->integer('year')) ?? $years->firstWhere('is_active', true) ?? $years->first();
        $schedule = $year ? $schedules->forYear($year) : null;

        return view('fee-schedule.index', [
            'portal' => $portal,
            'readOnly' => $portal !== 'treasurer',
            'years' => $years,
            'year' => $year,
            'schedule' => $schedule,
            'levels' => $schedule ? $schedules->levels($schedule) : collect(),
            'items' => $schedule ? $schedule->items->groupBy('grade_level_id')->map->keyBy('fee_type') : collect(),
            'summary' => $schedule ? $schedules->summary($schedule) : [],
            'pending' => $schedule ? $schedules->pendingQuestions($schedule) : [],
            'service' => $schedules,
        ]);
    }

    public function update(SaveFeeScheduleRequest $request, FeeSchedule $feeSchedule, FeeScheduleService $schedules)
    {
        $data = $request->validated();
        $count = $schedules->save($feeSchedule, $data['items'], $data['confirmed'] ?? [], $data['reason'] ?? null, $request->user());

        return back()->with('status', "Fee schedule saved ({$count} ".str('change')->plural($count).').');
    }

    public function start(Request $request, AcademicYear $academicYear, FeeScheduleService $schedules)
    {
        $schedules->start($academicYear, $request->user());

        return redirect()->route('treasurer.fee-schedule.index', ['year' => $academicYear->id])
            ->with('status', "Fee schedule for {$academicYear->year_label} started from the previous year. It isn't published: check the amounts, then publish it.");
    }

    public function publish(Request $request, FeeSchedule $feeSchedule, FeeScheduleService $schedules)
    {
        $schedules->publish($feeSchedule, true, $request->user());

        return back()->with('status', 'Fee schedule published. Families now see it on their Fees page.');
    }

    public function unpublish(Request $request, FeeSchedule $feeSchedule, FeeScheduleService $schedules)
    {
        $schedules->publish($feeSchedule, false, $request->user());

        return back()->with('status', 'Fee schedule unpublished. Families no longer see it.');
    }
}
