<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $action = trim((string) $request->query('action'));
        $logs = AuditLog::query()->with(['user', 'web', 'article'])
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->latest('created_at')->paginate(100)->withQueryString();
        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');

        return view('admin.audit.index', compact('logs', 'actions', 'action'));
    }

    public function show(AuditLog $auditLog): View
    {
        return view('admin.audit.show', ['log' => $auditLog->load(['user', 'web', 'article'])]);
    }
}
