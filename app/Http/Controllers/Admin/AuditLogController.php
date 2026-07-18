<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::orderByDesc('created_at');

        if ($request->filled('event')) {
            $query->whereRaw('LOWER(event) LIKE LOWER(?)', ['%'.$request->event.'%']);
        }

        if ($request->filled('workspace_id')) {
            $query->where('workspace_id', $request->string('workspace_id'));
        }

        return view('admin.audit-logs.index', [
            'logs' => $query->paginate(40)->withQueryString(),
        ]);
    }
}
