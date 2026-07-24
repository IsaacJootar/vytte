<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class BenchmarkController extends Controller
{
    /**
     * Benchmarking is folded into the Reports hub ("Compare your assessment targets"). This
     * route is kept so old links still land somewhere useful.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('reports.index');
    }
}
