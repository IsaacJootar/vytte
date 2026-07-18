<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformUserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::with('activeWorkspace')
            ->withCount('workspaceMemberships')
            ->latest();

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->lower().'%';
            $query->where(function ($inner) use ($search): void {
                $inner->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$search]);
            });
        }

        if ($request->filled('platform_role')) {
            $query->where('platform_role', $request->string('platform_role'));
        }

        return view('admin.platform-users.index', [
            'users' => $query->paginate(30)->withQueryString(),
        ]);
    }

    public function updateRole(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'platform_role' => ['nullable', Rule::in(['PLATFORM_ADMIN'])],
        ]);

        $newRole = $validated['platform_role'] ?? null;
        $oldRole = $user->platform_role;

        if ($user->is(auth()->user()) && $oldRole === 'PLATFORM_ADMIN' && $newRole !== 'PLATFORM_ADMIN') {
            $remainingAdmins = User::where('platform_role', 'PLATFORM_ADMIN')
                ->whereKeyNot($user->getKey())
                ->count();
            if ($remainingAdmins === 0) {
                return back()->withErrors(['platform_role' => 'At least one Vytte Platform Admin must remain.']);
            }
        }

        $user->update(['platform_role' => $newRole]);
        $audit->record('platform.user.role_updated', $user, ['platform_role' => $oldRole], ['platform_role' => $newRole]);

        return back()->with('success', 'Platform role updated.');
    }
}
