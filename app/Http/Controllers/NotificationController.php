<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Auth::user()->notifications()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * JSON feed for the header bell dropdown — latest notifications
     * plus the unread count.
     */
    public function feed()
    {
        $user = Auth::user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->data['type'] ?? class_basename($n->type),
                'title' => $n->data['title'] ?? 'Notification',
                'message' => $n->data['message'] ?? '',
                'icon' => $n->data['icon'] ?? 'fa-bell',
                'action_url' => $n->data['action_url'] ?? null,
                'action_text' => $n->data['action_text'] ?? 'View',
                'read' => $n->read_at !== null,
                'time' => $n->created_at->diffForHumans(),
            ]);

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markAllRead(Request $request)
    {
        Auth::user()->unreadNotifications->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'unread' => 0]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    public function markRead(Request $request, string $id)
    {
        $notification = Auth::user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        // Land on the related resource when the notification links to one.
        $url = $notification->data['action_url'] ?? null;
        if ($url && str_starts_with($url, url('/'))) {
            return redirect($url);
        }

        return back();
    }
}
