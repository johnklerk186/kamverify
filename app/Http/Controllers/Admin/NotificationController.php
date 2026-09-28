<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\KamVerifyNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('notifications')
            ->leftJoin('users', 'notifications.notifiable_id', '=', 'users.id')
            ->select('notifications.*', 'users.email as user_email', 'users.name as user_name')
            ->orderByDesc('notifications.created_at');

        if ($request->filled('type')) {
            $query->where('notifications.type', 'like', '%' . $request->type . '%');
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('users.email', 'like', "%{$search}%")
                  ->orWhere('notifications.type', 'like', "%{$search}%");
            });
        }

        $notifications = $query->paginate(25)->withQueryString();
        $notifications->getCollection()->transform(function ($n) {
            $n->data = json_decode($n->data, true) ?? [];
            $n->short_type = class_basename($n->type);
            return $n;
        });

        $stats = [
            'total' => DB::table('notifications')->count(),
            'unread' => DB::table('notifications')->whereNull('read_at')->count(),
            'today' => DB::table('notifications')->whereDate('created_at', today())->count(),
        ];

        return view('admin.notifications.index', compact('notifications', 'stats'));
    }

    /**
     * Compose form — send to all customers, selected customers, or one.
     */
    public function create(Request $request)
    {
        $customers = User::where('role', 'customer')
            ->orderBy('email')
            ->get(['id', 'name', 'email']);

        return view('admin.notifications.create', compact('customers'));
    }

    /**
     * Persist + dispatch a KamVerify notification. Stored in the
     * notification centre immediately; browsers subscribed to Web Push
     * also receive a push. Every send is audit-logged.
     */
    public function send(Request $request)
    {
        $data = $request->validate([
            'audience'   => 'required|in:all,selected,one',
            'user_ids'   => 'required_if:audience,selected,one|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'title'      => 'required|string|max:120',
            'message'    => 'required|string|max:1000',
            'type'       => 'required|in:announcement,account,security,maintenance',
            'link'       => 'nullable|url|max:500',
        ]);

        $query = User::where('role', 'customer');

        if ($data['audience'] !== 'all') {
            $query->whereIn('id', $data['user_ids']);
        }

        $count = 0;
        $query->chunkById(200, function ($users) use ($data, &$count) {
            foreach ($users as $user) {
                $user->notify(new KamVerifyNotification(
                    $data['type'] === 'announcement' ? 'announcement' : 'account',
                    $data['title'],
                    $data['message'],
                    $data['link'] ?? null,
                    'Open',
                    'fa-bullhorn'
                ));
                $count++;
            }
        });

        app(\App\Services\AuditService::class)->log(
            'notification.send',
            null,
            null,
            [
                'audience' => $data['audience'],
                'recipients' => $count,
                'title' => $data['title'],
                'type' => $data['type'],
            ]
        );

        return redirect()->route('admin.notifications.index')
            ->with('success', "Notification sent to {$count} customer(s).");
    }
}
