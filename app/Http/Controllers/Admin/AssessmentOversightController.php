<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AssessmentOversightController extends Controller
{
    public function index(Request $request): View
    {
        $query = Assessment::withoutGlobalScopes()
            ->with(['project.workspace', 'target', 'catalogueRelease', 'snapshot', 'reportSnapshot', 'score'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('creation_path')) {
            $query->where('creation_path', $request->string('creation_path'));
        }

        return view('admin.assessments.index', [
            'assessments' => $query->paginate(30)->withQueryString(),
        ]);
    }
}
