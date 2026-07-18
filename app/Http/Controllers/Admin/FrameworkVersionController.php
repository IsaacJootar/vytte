<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepartmentFrameworkVersion;
use App\Services\DepartmentFrameworkPublishingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FrameworkVersionController extends Controller
{
    public function index(Request $request): View
    {
        $query = DepartmentFrameworkVersion::with('module')
            ->withCount(['sections', 'indicators', 'questionPlacements'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('framework_type')) {
            $query->where('framework_type', $request->string('framework_type'));
        }

        return view('admin.framework-versions.index', [
            'frameworks' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function show(DepartmentFrameworkVersion $framework): View
    {
        $framework->load([
            'module',
            'sections.indicators.placements.questionVersion.questionType',
            'questionPlacements.questionVersion.questionType',
            'questionPlacements.section',
            'questionPlacements.indicator',
        ]);

        return view('admin.framework-versions.show', compact('framework'));
    }

    public function publish(DepartmentFrameworkVersion $framework, DepartmentFrameworkPublishingService $publisher): RedirectResponse
    {
        try {
            $publisher->publish($framework, auth()->id());
        } catch (\Throwable $exception) {
            return back()->withErrors(['publication' => $exception->getMessage()]);
        }

        return back()->with('success', 'Framework version published and frozen.');
    }
}
