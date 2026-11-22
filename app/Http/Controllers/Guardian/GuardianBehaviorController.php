<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Behavior\BehaviorStudentController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Guardian\Concerns\ResolvesChild;
use Illuminate\Http\Request;

/** Guardian → Behavior, for their own children only. Read only. */
class GuardianBehaviorController extends Controller
{
    use ResolvesChild;

    public function index(Request $request)
    {
        $children = $this->guardianChildren($request);
        abort_if($children->isEmpty(), 403, 'No linked children.');

        // Unlike other guardian pages, a child id that isn't theirs is refused, not swapped for their own child.
        if ($request->filled('child')) {
            $child = $children->firstWhere('id', $request->integer('child'));
            abort_unless($child, 403, 'You can only view your own children.');
        } else {
            $child = $children->first();
        }

        return view('family.behavior', BehaviorStudentController::record($child) + [
            'student' => $child, 'role' => 'guardian', 'children' => $children,
        ]);
    }
}
