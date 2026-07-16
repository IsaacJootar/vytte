<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformSettingController extends Controller
{
    public function index(): View
    {
        $emailEnabled = PlatformSetting::get('email.notifications_enabled', false);

        return view('admin.settings.index', compact('emailEnabled'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email_notifications_enabled' => ['nullable', 'boolean'],
        ]);

        PlatformSetting::set(
            'email.notifications_enabled',
            $request->boolean('email_notifications_enabled'),
            'boolean'
        );

        return back()->with('success', 'Platform settings saved.');
    }
}
