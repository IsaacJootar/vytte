<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(DatabaseNotification $notification): RedirectResponse
    {
        if ($notification->notifiable_id !== auth()->id()) {
            abort(403);
        }

        $notification->markAsRead();

        $redirect = $notification->data['url'] ?? route('notifications.index');

        return redirect($redirect);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
