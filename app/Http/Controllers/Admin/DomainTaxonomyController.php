<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\DomainTaxonomy;
use App\Models\DomainTaxonomyVersion;
use App\Services\DomainTaxonomyPublishingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class DomainTaxonomyController extends Controller
{
    public function index(): View
    {
        $taxonomies = DomainTaxonomy::with(['versions.definitions'])
            ->orderBy('taxonomy_name')
            ->get();

        return view('admin.domain-taxonomies.index', compact('taxonomies'));
    }

    public function show(DomainTaxonomyVersion $version): View
    {
        $version->load([
            'taxonomy',
            'definitions.indicatorMappings.indicator.frameworkVersion.module',
            'definitions.indicatorMappings.indicator.section',
        ]);

        return view('admin.domain-taxonomies.show', [
            'version' => $version,
            // Measurement domains that exist but this version does not define. They would
            // carry no scores and report nothing, so the screen names them rather than
            // letting them look active.
            'undefinedDomains' => Domain::whereNotIn('domain_id', $version->definitions->pluck('domain_id'))
                ->orderBy('display_order')
                ->get(),
        ]);
    }

    /**
     * Opens a new draft carrying forward the published version and adding any
     * measurement domain it does not yet define.
     */
    public function startVersion(DomainTaxonomy $taxonomy, DomainTaxonomyPublishingService $publishing): RedirectResponse
    {
        $draft = $publishing->startNewVersion($taxonomy, auth()->id());

        return redirect()
            ->route('admin.domain-taxonomies.show', $draft)
            ->with('success', 'Version '.$draft->version_number.' started as a draft. Review the wording, then publish it to bring it into force.');
    }

    public function publish(DomainTaxonomyVersion $version, DomainTaxonomyPublishingService $publishing): RedirectResponse
    {
        $published = $publishing->publish($version, auth()->id());

        return back()->with('success',
            'Version '.$published->version_number.' published. It is now the taxonomy in force, and its contents can no longer be changed.');
    }
}
