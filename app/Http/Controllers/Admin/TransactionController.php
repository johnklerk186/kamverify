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

        // Batch-resolve related orders (reference column or metadata.order_id)
        $orderRefs = $transactions->getCollection()
            ->map(fn ($t) => $t->reference ?? ($t->metadata['order_id'] ?? null))
            ->filter()->unique()->values();
        $relatedOrders = \App\Models\Order::whereIn('order_id', $orderRefs)->get()->keyBy('order_id');
        $types = WalletTransaction::select('type')->distinct()->pluck('type');
        $totals = [
            'deposits' => WalletTransaction::where('type', 'deposit')->where('status', 'completed')->sum('amount'),
            'purchases' => abs(WalletTransaction::where('type', 'purchase')->where('status', 'completed')->sum('amount')),
            'refunds' => WalletTransaction::where('type', 'refund')->where('status', 'completed')->sum('amount'),
            'adjustments' => WalletTransaction::where('type', 'adjustment')->where('status', 'completed')->sum('amount'),
        ];

        return view('admin.transactions.index', compact('transactions', 'types', 'totals', 'relatedOrders'));
    }
}
