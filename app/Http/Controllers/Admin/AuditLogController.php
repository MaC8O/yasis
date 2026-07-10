<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')->latest('created_at');

        if ($search = $request->string('search')->trim()->value()) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orWhere('action', 'like', "%{$search}%")
                ->orWhere('entity_type', 'like', "%{$search}%");
        }

        return view('admin.audit-logs.index', [
            'logs' => $query->paginate(25)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }
}
