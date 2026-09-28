<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = WalletTransaction::with('user');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%"));
            });
        }

        $transactions = $query->latest()->paginate(25)->withQueryString();
        $types = WalletTransaction::select('type')->distinct()->pluck('type');
        $totals = [
            'deposits' => WalletTransaction::where('type', 'deposit')->where('status', 'completed')->sum('amount'),
            'withdrawals' => abs(WalletTransaction::where('type', 'withdrawal')->where('status', 'completed')->sum('amount')),
            'refunds' => WalletTransaction::where('type', 'refund')->where('status', 'completed')->sum('amount'),
        ];

        return view('admin.transactions.index', compact('transactions', 'types', 'totals'));
    }
}
