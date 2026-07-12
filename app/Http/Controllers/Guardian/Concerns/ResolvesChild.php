<?php

namespace App\Http\Controllers\Guardian\Concerns;

use App\Models\Student;
use Illuminate\Http\Request;

trait ResolvesChild
{
    protected function guardianChildren(Request $request)
    {
        return $request->user()->guardian->students()->with('department')->get();
    }

    protected function selectedChild(Request $request): Student
    {
        $children = $this->guardianChildren($request);
        $selected = $children->firstWhere('id', $request->integer('child')) ?? $children->first();

        abort_if(! $selected, 403, 'No linked children.');

        return $selected;
    }
}
