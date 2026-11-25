<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RenameGradeLevelRequest;
use App\Http\Requests\Admin\StoreGradeLevelRequest;
use App\Models\GradeLevel;
use App\Services\GradeLevelService;
use Illuminate\Http\Request;

class GradeLevelController extends Controller
{
    public function __construct(private GradeLevelService $levels)
    {
    }

    public function index()
    {
        return view('admin.grade-levels.index', [
            'levels' => GradeLevel::ordered()->withCount('sections')->get(),
        ]);
    }

    public function store(StoreGradeLevelRequest $request)
    {
        $level = $this->levels->create($request->validated(), $request->user());

        return back()->with('status', "{$level->name} added at the end of the list.");
    }

    public function update(RenameGradeLevelRequest $request, GradeLevel $gradeLevel)
    {
        $this->levels->rename($gradeLevel, $request->validated('name'), $request->user());

        return back()->with('status', "Renamed to {$gradeLevel->name}.");
    }

    public function move(Request $request, GradeLevel $gradeLevel, string $direction)
    {
        $this->levels->move($gradeLevel, $direction, $request->user());

        return back()->with('status', "{$gradeLevel->name} moved {$direction}.");
    }

    public function retire(Request $request, GradeLevel $gradeLevel)
    {
        $this->levels->retire($gradeLevel, $request->user());

        return back()->with('status', "{$gradeLevel->name} retired. It can no longer be chosen for a section.");
    }

    public function restore(Request $request, GradeLevel $gradeLevel)
    {
        $this->levels->restore($gradeLevel, $request->user());

        return back()->with('status', "{$gradeLevel->name} restored.");
    }
}
