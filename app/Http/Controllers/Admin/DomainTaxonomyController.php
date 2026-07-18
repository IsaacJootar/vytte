<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DomainTaxonomy;
use App\Models\DomainTaxonomyVersion;
use Illuminate\Contracts\View\View;

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

        return view('admin.domain-taxonomies.show', compact('version'));
    }
}
