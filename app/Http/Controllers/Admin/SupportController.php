<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicket::with('user')->withCount('messages');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ticket_id', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%"));
            });
        }

        $tickets = $query->latest()->paginate(25)->withQueryString();
        $counts = SupportTicket::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.support.index', compact('tickets', 'counts'));
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load(['user', 'messages.user']);

        // Mark in-progress when an admin opens an open ticket
        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress', 'assigned_to' => Auth::id()]);
        }

        return view('admin.support.show', compact('ticket'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $request->validate(['message' => 'required|string|max:5000']);

        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $request->message,
            'is_admin' => true,
        ]);

        if ($ticket->status === 'closed') {
            $ticket->update(['status' => 'in_progress']);
        }

        return back()->with('success', 'Reply sent.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        $ticket->update($request->only(['status', 'priority']));

        return back()->with('success', 'Ticket updated.');
    }
}
