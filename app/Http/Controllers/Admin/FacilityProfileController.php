<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentModule;
use App\Models\FacilityProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FacilityProfileController extends Controller
{
    public function index(Request $request): View
    {
        $query = FacilityProfile::with('settingType')
            ->withCount('departments')
            ->orderBy('display_order')
            ->orderBy('profile_name');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('setting_type_code')) {
            $query->where('setting_type_code', $request->string('setting_type_code'));
        }

        return view('admin.facility-profiles.index', [
            'profiles' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function show(FacilityProfile $profile): View
    {
        $profile->load(['settingType', 'departments.frameworkVersions']);

        return view('admin.facility-profiles.show', [
            'profile' => $profile,
            'modules' => AssessmentModule::where('is_active', true)->orderBy('module_name')->get(),
        ]);
    }

    public function update(Request $request, FacilityProfile $profile): RedirectResponse
    {
        $validated = $request->validate([
            'profile_name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:DRAFT,PUBLISHED'],
            'display_order' => ['required', 'integer', 'min:0', 'max:999'],
            'departments' => ['nullable', 'array'],
            'departments.*.module_id' => ['required', 'integer', Rule::exists('assessment_modules', 'module_id')],
            'departments.*.applicability' => ['required', 'in:REQUIRED,DEFAULT,OPTIONAL,UNAVAILABLE'],
            'departments.*.display_order' => ['required', 'integer', 'min:0', 'max:999'],
            'departments.*.removal_allowed' => ['nullable', 'boolean'],
        ]);

        $profile->update([
            'profile_name' => $validated['profile_name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'display_order' => $validated['display_order'],
        ]);

        $sync = [];
        foreach ($validated['departments'] ?? [] as $department) {
            $sync[$department['module_id']] = [
                'applicability' => $department['applicability'],
                'display_order' => $department['display_order'],
                'removal_allowed' => (bool) ($department['removal_allowed'] ?? false),
            ];
        }
        $profile->departments()->sync($sync);

        return back()->with('success', 'Facility profile saved.');
    }
}
