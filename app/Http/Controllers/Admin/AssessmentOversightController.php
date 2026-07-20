<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Assessments customers are actually running.
 *
 * The sibling screen, Assessments, holds the blank templates Vytte authors and
 * publishes. This one holds the filled-in ones: real facilities, real answers. It is
 * read-only — Platform Admin can see that an assessment exists and how far along it is,
 * never the answers inside it.
 */
class AssessmentOversightController extends Controller
{
    public function index(Request $request): View
    {
        $query = Assessment::withoutGlobalScopes()
            ->with(['project.workspace', 'target', 'catalogueRelease', 'score'])
            ->latest();

        if ($request->filled('search')) {
            $search = '%'.strtolower($request->string('search')->value()).'%';
            $query->where(function ($inner) use ($search): void {
                $inner->whereHas('target', fn ($t) => $t->whereRaw('LOWER(name) LIKE ?', [$search]))
                    ->orWhereHas('project', fn ($p) => $p->withoutGlobalScopes()->whereRaw('LOWER(name) LIKE ?', [$search]))
                    ->orWhereHas('project.workspace', fn ($w) => $w->whereRaw('LOWER(name) LIKE ?', [$search]));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('creation_path')) {
            $query->where('creation_path', $request->string('creation_path'));
        }

        $all = fn () => Assessment::withoutGlobalScopes();

        return view('admin.assessments.index', [
            'assessments' => $query->paginate(30)->withQueryString(),
            'counts' => [
                'total' => $all()->count(),
                'in_progress' => $all()->whereNull('completed_at')->count(),
                'completed' => $all()->whereNotNull('completed_at')->count(),
                'this_month' => $all()->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
        ]);
    }
}
