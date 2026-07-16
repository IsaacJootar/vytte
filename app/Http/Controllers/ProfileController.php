<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\WorkspaceMember;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        $currentMember = $workspace
            ? WorkspaceMember::where('workspace_id', $workspace->workspace_id)
                ->where('user_id', $request->user()->user_id)
                ->first()
            : null;

        $timezones = collect(DateTimeZone::listIdentifiers())
            ->filter(fn ($tz) => str_starts_with($tz, 'Africa/') ||
                str_starts_with($tz, 'Europe/') ||
                str_starts_with($tz, 'America/') ||
                str_starts_with($tz, 'Asia/') ||
                $tz === 'UTC'
            )
            ->sort()
            ->values();

        return view('profile.edit', [
            'user' => $request->user(),
            'workspace' => $workspace,
            'currentMember' => $currentMember,
            'timezones' => $timezones,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
