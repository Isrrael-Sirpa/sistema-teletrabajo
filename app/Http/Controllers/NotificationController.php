<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $perPage = max(5, min(100, (int) config('documents.notifications.per_page', 15)));
        $unreadNotifications = $user->unreadNotifications()
            ->latest()
            ->paginate($perPage, ['*'], 'no_leidas')
            ->withQueryString();
        $readNotifications = $user->readNotifications()
            ->latest()
            ->paginate($perPage, ['*'], 'leidas')
            ->withQueryString();

        return view('notifications.index', compact('unreadNotifications', 'readNotifications'));
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $storedNotification = $request->user()->notifications()->findOrFail($notification);
        $storedNotification->markAsRead();

        return back()->with('status', 'Notificación marcada como leída.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Todas las notificaciones fueron marcadas como leídas.');
    }
}
