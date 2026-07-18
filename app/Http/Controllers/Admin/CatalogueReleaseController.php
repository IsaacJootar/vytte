<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentCatalogueRelease;
use App\Services\CataloguePublishingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CatalogueReleaseController extends Controller
{
    public function index(Request $request): View
    {
        $query = AssessmentCatalogueRelease::with(['facilityProfile', 'healthDomain'])
            ->withCount('departmentFrameworkVersions')
            ->latest();

        if ($request->filled('creation_path')) {
            $query->where('creation_path', $request->string('creation_path'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('admin.catalogue-releases.index', [
            'releases' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function show(AssessmentCatalogueRelease $release): View
    {
        $release->load(['facilityProfile.departments', 'healthDomain', 'departmentFrameworkVersions.module']);

        return view('admin.catalogue-releases.show', compact('release'));
    }

    public function publish(AssessmentCatalogueRelease $release, CataloguePublishingService $publisher): RedirectResponse
    {
        try {
            $publisher->publish($release, auth()->id());
        } catch (\Throwable $exception) {
            return back()->withErrors(['publication' => $exception->getMessage()]);
        }

        return back()->with('success', 'Catalogue release published and frozen.');
    }
}
