<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        $query = User::where('role', 'customer');

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $wallet = $user->wallet;
        $orders = $user->orders()->orderBy('created_at', 'desc')->limit(20)->get();
        $transactions = $wallet ? $wallet->transactions()->orderBy('created_at', 'desc')->limit(20)->get() : collect();

        return view('admin.users.show', compact('user', 'wallet', 'orders', 'transactions'));
    }

    public function toggleStatus(User $user)
    {
        $oldValues = ['is_active' => $user->is_active];
        $user->update(['is_active' => !$user->is_active]);

        $this->auditService->log(
            'user.status.toggle',
            $user,
            $oldValues,
            ['is_active' => $user->is_active]
        );

        return back()->with('success', 'User status updated successfully.');
    }

    /**
     * Manual wallet adjustment — credit (deposit/compensation) or debit
     * (correction). Whole XAF only, wallet row-locked, audit-logged, and
     * the customer is notified. Debits can never push a balance negative.
     */
    public function adjustWallet(Request $request, User $user)
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot adjust an admin wallet.');
        }

        $data = $request->validate([
            'direction' => 'required|in:credit,debit',
            'amount'    => 'required|integer|min:1|max:10000000',
            'reason'    => 'required|string|max:255',
        ]);

        $amount = (int) $data['amount'];
        $wallet = $user->wallet;
        $oldBalance = $wallet ? (float) $wallet->balance : 0.0;
        $walletService = app(\App\Services\WalletService::class);

        try {
            if ($data['direction'] === 'credit') {
                $txn = $walletService->deposit(
                    $user,
                    $amount,
                    'Admin credit — ' . $data['reason'],
                    ['admin_id' => $request->user()->id, 'reason' => $data['reason']]
                );
            } else {
                $txn = $walletService->withdraw(
                    $user,
                    $amount,
                    'Admin debit — ' . $data['reason'],
                    ['admin_id' => $request->user()->id, 'reason' => $data['reason']]
                );
            }
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Adjustment failed: ' . $e->getMessage());
        }

        $newBalance = (float) $user->wallet->fresh()->balance;

        $this->auditService->log(
            'user.wallet.adjust',
            $user,
            ['balance' => $oldBalance],
            [
                'balance' => $newBalance,
                'direction' => $data['direction'],
                'amount' => $amount,
                'reason' => $data['reason'],
                'transaction' => $txn->transaction_id,
            ]
        );

        $user->notify(new \App\Notifications\KamVerifyNotification(
            'wallet_adjusted',
            $data['direction'] === 'credit' ? 'Wallet Credited' : 'Wallet Debited',
            ($data['direction'] === 'credit' ? '+' : '−') . xaf($amount)
                . ' was ' . ($data['direction'] === 'credit' ? 'added to' : 'deducted from')
                . ' your wallet. Reason: ' . $data['reason'],
            route('wallet.index'),
            'View Wallet',
            'fa-wallet'
        ));

        return back()->with('success',
            ucfirst($data['direction']) . ' of ' . xaf($amount) . ' applied. New balance: ' . xaf($newBalance) . '.');
    }

    public function destroy(User $user)
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot delete admin users.');
        }

        $this->auditService->log('user.delete', $user);

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}