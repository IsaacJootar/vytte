<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class BenchmarkController extends Controller
{
    /** Keep old bookmarks working while comparisons live in one safe results view. */
    public function index(): RedirectResponse
    {
        return redirect()->route('portfolio.index')
            ->with('info', 'Comparisons are now included in Assessment Portfolio and are shown only when assessments use the same scoring rules.');
    }
}
