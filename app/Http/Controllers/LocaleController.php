<?php

namespace App\Http\Controllers;

use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    private const SUPPORTED = ['en', 'fr'];

    public function store(Request $request): RedirectResponse
    {
        $locale = $request->input('locale', 'en');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'en';
        }

        if ($locale !== 'en' && app()->bound('current.workspace')) {
            $workspace = app('current.workspace');
            if (! PlanService::workspaceCanAccess($workspace, 'localization')) {
                return back()->with('error', 'Multi-language support is not available on your current plan. Upgrade to switch languages.');
            }
        }

        $request->session()->put('locale', $locale);

        if ($request->user()) {
            $request->user()->update(['locale' => $locale]);
        }

        return redirect()->back();
    }
}
