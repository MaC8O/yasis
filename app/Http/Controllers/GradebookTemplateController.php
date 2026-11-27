<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\UpdateGradebookTemplateRequest;
use App\Models\GradingScheme;
use App\Services\GradebookTemplateService;
use Illuminate\Http\Request;

/**
 * The school's gradebook template. The Admin edits it (admin.gradebook-template.*); the Principal
 * sees the same page read-only (principal.gradebook-template.index).
 */
class GradebookTemplateController extends Controller
{
    public function __construct(private GradebookTemplateService $templates) {}

    public function index(Request $request)
    {
        $portal = strtok($request->route()->getName(), '.');
        abort_unless(in_array($portal, ['admin', 'principal'], true), 404);

        return view('gradebook-template.index', [
            'portal' => $portal,
            'readOnly' => $portal !== 'admin',
            'template' => $this->templates->template(),
            'schemes' => GradingScheme::with(['gradeLevels' => fn ($q) => $q->ordered()])->orderBy('id')->get(),
        ]);
    }

    public function update(UpdateGradebookTemplateRequest $request)
    {
        $data = $request->validated();
        $this->templates->update($data['categories'], (bool) $data['confirmed'], $request->user());

        return back()->with('status', 'Gradebook template saved. The open terms\' gradebooks use it now; locked terms keep theirs.');
    }
}
