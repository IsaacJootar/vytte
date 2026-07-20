<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Workspace;
use App\Support\AuditEventLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::with(['user'])->orderByDesc('created_at');

        // Search covers what a person actually remembers: roughly what happened, or who
        // did it. The stored event key is matched too, so a technical search still works.
        if ($request->filled('search')) {
            $search = '%'.strtolower($request->string('search')->value()).'%';
            $query->where(function ($inner) use ($search): void {
                $inner->whereRaw('LOWER(event) LIKE ?', [$search])
                    ->orWhereHas('user', function ($user) use ($search): void {
                        $user->whereRaw('LOWER(name) LIKE ?', [$search])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$search]);
                    });
            });
        }

        if ($request->filled('category')) {
            $prefixes = AuditEventLabel::prefixesForCategory($request->string('category')->value());

            if ($prefixes !== []) {
                $query->where(function ($inner) use ($prefixes): void {
                    foreach ($prefixes as $prefix) {
                        $inner->orWhere('event', 'like', $prefix.'%');
                    }
                });
            }
        }

        if ($request->filled('workspace_id')) {
            $query->where('workspace_id', $request->string('workspace_id'));
        }

        if ($request->filled('since')) {
            $days = match ($request->string('since')->value()) {
                'today' => 0,
                'week' => 7,
                'month' => 30,
                default => null,
            };

            if ($days !== null) {
                $query->where('created_at', '>=', $days === 0 ? now()->startOfDay() : now()->subDays($days));
            }
        }

        return view('admin.audit-logs.index', [
            'logs' => $query->paginate(40)->withQueryString(),
            'categories' => AuditEventLabel::CATEGORIES,
            'workspaces' => Workspace::orderBy('name')->get(['workspace_id', 'name']),
            'counts' => [
                'today' => AuditLog::where('created_at', '>=', now()->startOfDay())->count(),
                'week' => AuditLog::where('created_at', '>=', now()->subDays(7))->count(),
                'total' => AuditLog::count(),
            ],
        ]);
    }
}
