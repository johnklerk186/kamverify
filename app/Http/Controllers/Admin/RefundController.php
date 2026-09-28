<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function index(Request $request)
    {
        $query = Refund::with(['user', 'order']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('refund_id', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%"))
                  ->orWhereHas('order', fn ($o) => $o->where('order_id', 'like', "%{$search}%"));
            });
        }

        $refunds = $query->latest()->paginate(25)->withQueryString();
        $totals = [
            'processed' => Refund::where('status', 'processed')->sum('amount'),
            'count' => Refund::count(),
            'today' => Refund::whereDate('created_at', today())->sum('amount'),
        ];

        return view('admin.refunds.index', compact('refunds', 'totals'));
    }
}
