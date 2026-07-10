<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.audit-logs.index', [
            'logs' => $this->filteredQuery($request)->paginate(25)->withQueryString(),
            'filters' => $request->only(['search', 'from', 'to']),
        ]);
    }

    /** §6.6: export the filtered trail as CSV — read-only, same filters as the table. */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'audit-logs-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'User', 'Role', 'Action', 'Entity', 'Entity ID']);

            $this->filteredQuery($request)->with('user')->chunk(500, function ($logs) use ($out) {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->user?->name ?? '',
                        $log->role,
                        $log->action,
                        $log->entity_type,
                        $log->entity_id,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function filteredQuery(Request $request): Builder
    {
        $query = AuditLog::query()->with('user')->latest('created_at')->latest('id');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('entity_type', 'like', "%{$search}%");
            });
        }

        if ($from = $request->date('from')) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        if ($to = $request->date('to')) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        return $query;
    }
}
