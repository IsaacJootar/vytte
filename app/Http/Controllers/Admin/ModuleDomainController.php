<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModuleDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ModuleDomainController extends Controller
{
    public function update(Request $request, ModuleDomain $domain): RedirectResponse
    {
        $validated = $request->validate([
            'domain_label' => ['required', 'string', 'max:150'],
        ]);

        $domain->update($validated);

        return back()->with('success', 'Domain label updated.');
    }
}
