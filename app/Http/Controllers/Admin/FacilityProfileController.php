<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FacilityProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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

        return view('admin.facility-profiles.show', compact('profile'));
    }
}
