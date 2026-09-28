<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('payment_id', 'like', "%{$search}%")
                  ->orWhere('provider_payment_id', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%"));
            });
        }

        $payments = $query->latest()->paginate(25)->withQueryString();
        $providers = Payment::select('provider')->distinct()->pluck('provider');
        $totals = [
            'completed' => Payment::where('status', 'completed')->sum('amount'),
            'pending' => Payment::where('status', 'pending')->sum('amount'),
            'failed' => Payment::where('status', 'failed')->count(),
        ];

        return view('admin.payments.index', compact('payments', 'providers', 'totals'));
    }

    public function show(Payment $payment)
    {
        $payment->load('user', 'walletTransaction');

        return view('admin.payments.show', compact('payment'));
    }
}
