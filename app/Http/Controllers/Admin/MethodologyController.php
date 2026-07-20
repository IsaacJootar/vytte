<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalysisLens;
use App\Models\AssessmentObjective;
use App\Models\AssessmentTemplate;
use App\Models\HealthArea;
use App\Models\HealthDomain;
use App\Models\InsightCategory;
use App\Models\MethodologyVersion;
use App\Models\ObjectivePreset;
use App\Services\AuditService;
use App\Services\MethodologyPublishingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Administration of the official health knowledge model.
 *
 * Read, search and publish. The catalogue is curated and seeded rather than typed in one
 * entry at a time, so browsing and publication are the operations that matter; per-entry
 * authoring is recorded as remaining work rather than half-built.
 */
class MethodologyController extends Controller
{
    public function index(): View
    {
        $version = MethodologyPublishingService::currentVersion();

        return view('admin.methodology.index', [
            'version' => $version,
            'versions' => MethodologyVersion::orderByDesc('version_number')->get(),
            'counts' => $version ? [
                'objectives' => $version->objectives()->count(),
                'areas' => $version->healthAreas()->count(),
                'lenses' => $version->analysisLenses()->count(),
                'categories' => $version->insightCategories()->count(),
                'templates' => $version->templates()->count(),
                'presets' => $version->presets()->count(),
            ] : array_fill_keys(['objectives', 'areas', 'lenses', 'categories', 'templates', 'presets'], 0),
        ]);
    }

    public function objectives(Request $request): View
    {
        $version = MethodologyPublishingService::currentVersion();

        $query = AssessmentObjective::query()
            ->where('methodology_version_id', $version?->methodology_version_id)
            ->with('recommendations')
            ->orderBy('display_order');

        if ($request->filled('search')) {
            $search = '%'.strtolower($request->string('search')->value()).'%';
            $query->where(function ($inner) use ($search): void {
                $inner->whereRaw('LOWER(objective_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(question_it_answers) LIKE ?', [$search]);
            });
        }

        if ($request->filled('objective_group')) {
            $query->where('objective_group', $request->string('objective_group'));
        }

        return view('admin.methodology.objectives', [
            'version' => $version,
            'objectives' => $query->paginate(25)->withQueryString(),
            'groups' => AssessmentObjective::GROUPS,
        ]);
    }

    public function healthAreas(Request $request): View
    {
        $version = MethodologyPublishingService::currentVersion();

        $query = HealthArea::query()
            ->where('methodology_version_id', $version?->methodology_version_id)
            ->with('healthDomain')
            ->orderBy('display_order');

        if ($request->filled('search')) {
            $search = '%'.strtolower($request->string('search')->value()).'%';
            $query->whereRaw('LOWER(area_name) LIKE ?', [$search]);
        }

        if ($request->filled('health_domain_id')) {
            $query->where('health_domain_id', $request->integer('health_domain_id'));
        }

        return view('admin.methodology.health-areas', [
            'version' => $version,
            'areas' => $query->paginate(30)->withQueryString(),
            'domains' => HealthDomain::orderBy('display_order')->get(),
        ]);
    }

    public function lenses(Request $request): View
    {
        $version = MethodologyPublishingService::currentVersion();

        $query = AnalysisLens::query()
            ->where('methodology_version_id', $version?->methodology_version_id)
            ->orderBy('display_order');

        if ($request->filled('search')) {
            $search = '%'.strtolower($request->string('search')->value()).'%';
            $query->where(function ($inner) use ($search): void {
                $inner->whereRaw('LOWER(lens_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(question_it_answers) LIKE ?', [$search]);
            });
        }

        return view('admin.methodology.lenses', [
            'version' => $version,
            'lenses' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function insightCategories(Request $request): View
    {
        $version = MethodologyPublishingService::currentVersion();

        $query = InsightCategory::query()
            ->where('methodology_version_id', $version?->methodology_version_id)
            ->orderBy('display_order');

        if ($request->filled('search')) {
            $search = '%'.strtolower($request->string('search')->value()).'%';
            $query->whereRaw('LOWER(category_name) LIKE ?', [$search]);
        }

        if ($request->filled('polarity')) {
            $query->where('polarity', $request->string('polarity'));
        }

        return view('admin.methodology.insight-categories', [
            'version' => $version,
            'categories' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function templates(Request $request): View
    {
        $version = MethodologyPublishingService::currentVersion();

        $query = AssessmentTemplate::query()
            ->where('methodology_version_id', $version?->methodology_version_id)
            ->orderBy('display_order');

        if ($request->filled('search')) {
            $search = '%'.strtolower($request->string('search')->value()).'%';
            $query->where(function ($inner) use ($search): void {
                $inner->whereRaw('LOWER(template_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$search]);
            });
        }

        if ($request->filled('scope_type')) {
            $query->where('scope_type', $request->string('scope_type'));
        }

        return view('admin.methodology.templates', [
            'version' => $version,
            'templates' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function presets(Request $request): View
    {
        $version = MethodologyPublishingService::currentVersion();

        $query = ObjectivePreset::query()
            ->where('methodology_version_id', $version?->methodology_version_id)
            ->with('objective')
            ->orderBy('display_order');

        if ($request->filled('search')) {
            $search = '%'.strtolower($request->string('search')->value()).'%';
            $query->whereRaw('LOWER(preset_name) LIKE ?', [$search]);
        }

        return view('admin.methodology.presets', [
            'version' => $version,
            'presets' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function publish(
        MethodologyVersion $methodologyVersion,
        MethodologyPublishingService $publishing,
        AuditService $audit,
    ): RedirectResponse {
        $published = $publishing->publish($methodologyVersion, auth()->id());

        $audit->record('methodology.published', $published, newValues: [
            'version_number' => $published->version_number,
            'content_hash' => $published->content_hash,
        ]);

        return back()->with('success', 'Methodology version '.$published->version_number.' published. Its contents can no longer be changed.');
    }
}
