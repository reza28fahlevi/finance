<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->query('action').'%'))
            ->when($request->filled('subject'), fn ($q) => $q->where('auditable_type', 'like', '%'.$request->query('subject')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('settings.audit', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => AuditLog::select('action')->distinct()->orderBy('action')->pluck('action'),
            'subjects' => AuditLog::whereNotNull('auditable_type')->select('auditable_type')->distinct()->pluck('auditable_type')->map(fn ($t) => class_basename($t)),
        ]);
    }
}
