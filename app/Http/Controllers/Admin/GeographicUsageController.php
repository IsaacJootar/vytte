<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class GeographicUsageController extends Controller
{
    public function index(): View
    {
        $countryRows = DB::table('targets')
            ->join('assessments', 'assessments.target_id', '=', 'targets.target_id')
            ->select('targets.country', DB::raw('COUNT(assessments.assessment_id) as assessment_count'))
            ->whereNotNull('targets.country')
            ->groupBy('targets.country')
            ->orderByDesc('assessment_count')
            ->get();

        $regionRows = DB::table('targets')
            ->join('assessments', 'assessments.target_id', '=', 'targets.target_id')
            ->select('targets.country', 'targets.region', DB::raw('COUNT(assessments.assessment_id) as assessment_count'))
            ->whereNotNull('targets.country')
            ->whereNotNull('targets.region')
            ->where('targets.region', '!=', '')
            ->groupBy('targets.country', 'targets.region')
            ->orderByDesc('assessment_count')
            ->get()
            ->groupBy('country');

        $countries = $countryRows->map(fn ($row) => [
            'country' => $row->country,
            'assessment_count' => (int) $row->assessment_count,
            'regions' => $regionRows
                ->get($row->country, collect())
                ->map(fn ($r) => [
                    'region' => $r->region,
                    'assessment_count' => (int) $r->assessment_count,
                ])
                ->values()
                ->toArray(),
        ])->values()->toArray();

        $totalAssessments = array_sum(array_column($countries, 'assessment_count'));
        $countryCount = count($countries);
        $maxCount = $countryCount > 0 ? max(array_column($countries, 'assessment_count')) : 1;

        return view('admin.geographic-usage.index', compact(
            'countries',
            'totalAssessments',
            'countryCount',
            'maxCount',
        ));
    }
}
