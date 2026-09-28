<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $tickets = SupportTicket::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('support.index', compact('tickets'));
    }

    public function create()
    {
        return view('support.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'category' => 'required|in:general,payment,order,technical,other',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        try {
            $ticket = SupportTicket::create([
                'user_id' => Auth::id(),
                'ticket_id' => 'TKT-' . strtoupper(uniqid()),
                'category' => $request->category,
                'subject' => $request->subject,
                'status' => 'open',
                'priority' => 'medium',
            ]);

            SupportMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => Auth::id(),
                'message' => $request->message,
                'is_admin' => false,
            ]);

            return redirect()->route('support.show', $ticket->id)
                ->with('success', 'Support ticket created successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create ticket: ' . $e->getMessage());
        }
    }

    public function show(SupportTicket $ticket)
    {
        $this->authorize('view', $ticket);

        $messages = $ticket->messages()->orderBy('created_at', 'asc')->get();

        return view('support.show', compact('ticket', 'messages'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $this->authorize('reply', $ticket);

        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        try {
            SupportMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => Auth::id(),
                'message' => $request->message,
                'is_admin' => Auth::user()->isAdmin(),
            ]);

            // Update ticket status if it was closed
            if ($ticket->status === 'closed') {
                $ticket->update(['status' => 'open']);
            }

            return back()->with('success', 'Reply sent successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send reply: ' . $e->getMessage());
        }
    }

    public function close(SupportTicket $ticket)
    {
        $this->authorize('close', $ticket);

        try {
            $ticket->update(['status' => 'closed']);

            return back()->with('success', 'Ticket closed successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to close ticket: ' . $e->getMessage());
        }
    }
}