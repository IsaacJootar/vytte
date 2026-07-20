<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Notifications\AccountReactivatedNotification;
use App\Notifications\AccountSuspendedNotification;
use App\Services\AuditService;
use App\Services\SessionRevocationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformUserController extends Controller
{
    public function __construct(private SessionRevocationService $sessions) {}

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

        if ($request->filled('status')) {
            $request->string('status')->value() === 'suspended'
                ? $query->whereNotNull('suspended_at')
                : $query->whereNull('suspended_at');
        }

        return view('admin.platform-users.index', [
            'users' => $query->paginate(30)->withQueryString(),
            'counts' => [
                'total' => User::count(),
                'admins' => User::where('platform_role', 'PLATFORM_ADMIN')->count(),
                'suspended' => User::whereNotNull('suspended_at')->count(),
                'pending' => WorkspaceInvitation::whereNull('accepted_at')->count(),
            ],
        ]);
    }

    public function show(User $user): View
    {
        $user->load(['activeWorkspace', 'workspaceMemberships.workspace']);

        return view('admin.platform-users.show', [
            'user' => $user,
            // What this person did, plus anything done to their account.
            'history' => AuditLog::where('user_id', $user->user_id)
                ->orWhere(function ($query) use ($user): void {
                    $query->where('auditable_type', User::class)
                        ->where('auditable_id', $user->user_id);
                })
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
            'invitations' => WorkspaceInvitation::where('email', $user->email)
                ->with('workspace')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function updateRole(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'platform_role' => ['nullable', Rule::in(['PLATFORM_ADMIN'])],
        ]);

        $newRole = $validated['platform_role'] ?? null;
        $oldRole = $user->platform_role;

        if ($this->wouldRemoveLastAdmin($user, $oldRole, $newRole)) {
            return back()->withErrors(['platform_role' => 'At least one Vytte Platform Admin must remain.']);
        }

        $user->update(['platform_role' => $newRole]);
        $audit->record('platform.user.role_updated', $user, ['platform_role' => $oldRole], ['platform_role' => $newRole]);

        return back()->with('success', $newRole === 'PLATFORM_ADMIN'
            ? $user->name.' can now govern the platform.'
            : $user->name.' no longer has platform administration access.');
    }

    /**
     * Suspending blocks sign-in. It removes nothing: memberships, assessments and
     * everything the person authored stay exactly where they are.
     */
    public function suspend(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'suspension_reason' => ['required', 'string', 'max:255'],
        ]);

        if ($user->is($request->user())) {
            return back()->withErrors(['suspension_reason' => 'You cannot suspend your own account.']);
        }

        if ($this->wouldRemoveLastAdmin($user, $user->platform_role, null)) {
            return back()->withErrors(['suspension_reason' => 'At least one Vytte Platform Admin must remain able to sign in.']);
        }

        $user->update([
            'suspended_at' => now(),
            'suspension_reason' => $validated['suspension_reason'],
        ]);

        // Blocking sign-in is not enough on its own: an existing session would let them
        // keep working until it expired.
        $this->sessions->forUser($user);

        // Tell them, rather than leaving them to discover it by failing to sign in.
        $user->notify(new AccountSuspendedNotification($user->suspension_reason));

        $audit->record('platform.user.suspended', $user, ['suspended_at' => null], [
            'suspended_at' => $user->suspended_at?->toIso8601String(),
            'suspension_reason' => $user->suspension_reason,
        ]);

        return back()->with('success', $user->name.' has been suspended and can no longer sign in. Nothing was deleted.');
    }

    public function reactivate(User $user, AuditService $audit): RedirectResponse
    {
        if (! $user->isSuspended()) {
            return back()->with('success', 'No change — this account is already active.');
        }

        $old = [
            'suspended_at' => $user->suspended_at?->toIso8601String(),
            'suspension_reason' => $user->suspension_reason,
        ];

        $user->update(['suspended_at' => null, 'suspension_reason' => null]);
        $user->notify(new AccountReactivatedNotification);
        $audit->record('platform.user.reactivated', $user, $old, ['suspended_at' => null]);

        return back()->with('success', $user->name.' can sign in again.');
    }

    /**
     * A platform admin can only be demoted or blocked while another active one remains,
     * otherwise the platform locks itself out of its own governance controls.
     */
    private function wouldRemoveLastAdmin(User $user, ?string $oldRole, ?string $newRole): bool
    {
        if ($oldRole !== 'PLATFORM_ADMIN' || $newRole === 'PLATFORM_ADMIN') {
            return false;
        }

        return User::where('platform_role', 'PLATFORM_ADMIN')
            ->whereNull('suspended_at')
            ->whereKeyNot($user->getKey())
            ->doesntExist();
    }
}
