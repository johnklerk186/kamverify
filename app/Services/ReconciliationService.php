<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;

/**
 * Read-only financial audit engine shared by the `wallet:reconcile`
 * artisan command and the admin reconciliation page.
 *
 * Design rules:
 *  - It NEVER guesses. Every reclassification is derived from a
 *    concrete link: refund record, order reference, payment metadata,
 *    or a known description convention. Anything that cannot be
 *    positively identified is reported as ambiguous — never changed.
 *  - Nothing is deleted or created. --apply only relabels `type` /
 *    recomputes wallet totals / repairs order profit, and writes an
 *    audit_logs entry with before/after values for every change.
 *  - Wallet `balance` is authoritative and is never recomputed here —
 *    discrepancies are reported, not silently patched.
 */
class ReconciliationService
{
    /**
     * The full platform audit. Everything the command and the admin
     * page render comes from this single structure.
     */
    public function buildReport(): array
    {
        $transactions = WalletTransaction::orderBy('id')->get();
        $orders = Order::orderBy('id')->get();
        $refundTxnOrderIds = Refund::pluck('order_id', 'wallet_transaction_id')->all();
        $orderByRef = $orders->keyBy('order_id');
        $paymentIds = Payment::pluck('id', 'payment_id')->all();

        // ----------------------------------------------------------
        // Transaction classification
        // ----------------------------------------------------------
        $proposals = [];
        $ambiguous = [];
        foreach ($transactions as $txn) {
            $c = $this->classifyTransaction($txn, $orderByRef, $refundTxnOrderIds, $paymentIds);

            if ($c['confidence'] === 'ambiguous') {
                $ambiguous[] = [
                    'transaction' => $txn,
                    'reason' => $c['reason'],
                ];
                continue;
            }

            if ($c['type'] !== $txn->type) {
                $proposals[] = [
                    'transaction' => $txn,
                    'from' => $txn->type,
                    'to' => $c['type'],
                    'reason' => $c['reason'],
                ];
            }
        }

        // ----------------------------------------------------------
        // Wallet totals repairs (total_deposited / total_withdrawn)
        // ----------------------------------------------------------
        $walletFixes = [];
        foreach (Wallet::all() as $wallet) {
            $types = $transactions->where('wallet_id', $wallet->id);
            $deposited = (float) $types->where('type', 'deposit')->sum('amount');
            // Proposals change types — recompute against post-fix types.
            foreach ($proposals as $p) {
                if ($p['transaction']->wallet_id !== $wallet->id) continue;
                if ($p['from'] === 'deposit') $deposited -= (float) $p['transaction']->amount;
                if ($p['to'] === 'deposit')   $deposited += (float) $p['transaction']->amount;
            }
            $withdrawn = (float) $types->where('amount', '<', 0)->sum('amount') * -1;

            if (abs($deposited - (float) $wallet->total_deposited) > 0.01
                || abs($withdrawn - (float) $wallet->total_withdrawn) > 0.01) {
                $walletFixes[] = [
                    'wallet' => $wallet,
                    'deposited_from' => (float) $wallet->total_deposited,
                    'deposited_to' => $deposited,
                    'withdrawn_from' => (float) $wallet->total_withdrawn,
                    'withdrawn_to' => $withdrawn,
                ];
            }
        }

        // ----------------------------------------------------------
        // Order-level audit
        // ----------------------------------------------------------
        $orderFindings = [];
        $refundableOutstanding = 0.0;
        foreach ($orders as $order) {
            $finding = $this->auditOrder($order);
            if ($finding) {
                $orderFindings[] = $finding;
            }
            if (in_array($order->status, ['cancelled', 'expired', 'failed'], true)
                && (float) $order->refund_amount <= 0) {
                $refundableOutstanding += (float) $order->selling_price;
            }
        }

        // Order profit repairs (same rule refundOrder applies live)
        $profitFixes = [];
        foreach ($orders->where('refund_amount', '>', 0) as $order) {
            $costLost = $order->provider_refund_status === 'consumed'
                ? (float) $order->purchase_price : 0.0;
            $expected = round((float) $order->selling_price - (float) $order->refund_amount - $costLost, 2);
            if (abs((float) $order->profit - $expected) > 0.01) {
                $profitFixes[] = [
                    'order' => $order,
                    'from' => (float) $order->profit,
                    'to' => $expected,
                ];
            }
        }

        return [
            'generated_at' => now(),
            'transactions' => [
                'total' => $transactions->count(),
                'proposals' => $proposals,
                'ambiguous' => $ambiguous,
                'by_target' => collect($proposals)->groupBy('to')->map->count()->all(),
                'xaf_affected' => collect($proposals)->sum(fn ($p) => abs((float) $p['transaction']->amount)),
            ],
            'wallets' => $walletFixes,
            'orders' => [
                'total' => $orders->count(),
                'findings' => $orderFindings,
                'profit_fixes' => $profitFixes,
                'refundable_outstanding' => $refundableOutstanding,
            ],
            'customers' => $this->customerReconciliation($transactions),
            'provider' => $this->providerReconciliation($orders),
            'platform' => $this->platformTotals($transactions),
        ];
    }

    /**
     * Write the report's corrections. Used by `wallet:reconcile --apply`
     * AND the admin "Apply corrections" action — both go through the
     * same audited path. Every write lands in audit_logs (reconcile.*)
     * with before/after values. Balances are never modified.
     */
    public function applyReport(array $report): int
    {
        $audit = app(AuditService::class);
        $written = 0;

        foreach ($report['transactions']['proposals'] as $p) {
            $txn = $p['transaction'];
            $audit->log('reconcile.transaction_type', $txn,
                ['type' => $p['from']],
                ['type' => $p['to'], 'reason' => $p['reason']]);
            $txn->update(['type' => $p['to']]);
            $written++;
        }

        foreach ($report['wallets'] as $w) {
            $audit->log('reconcile.wallet_totals', $w['wallet'],
                ['total_deposited' => $w['deposited_from'], 'total_withdrawn' => $w['withdrawn_from']],
                ['total_deposited' => $w['deposited_to'], 'total_withdrawn' => $w['withdrawn_to'],
                 'reason' => 'recomputed from ledger']);
            $w['wallet']->update([
                'total_deposited' => $w['deposited_to'],
                'total_withdrawn' => $w['withdrawn_to'],
            ]);
            $written++;
        }

        foreach ($report['orders']['profit_fixes'] as $f) {
            $audit->log('reconcile.order_profit', $f['order'],
                ['profit' => $f['from']],
                ['profit' => $f['to'], 'reason' => 'selling − refund − unrecovered provider cost']);
            $f['order']->update(['profit' => $f['to']]);
            $written++;
        }

        return $written;
    }

    /**
     * Classify a wallet transaction into its true ledger type.
     * Confidence 'high' means a concrete linkage exists; anything else
     * is 'ambiguous' and must be left for manual review.
     */
    public function classifyTransaction(
        WalletTransaction $txn,
        Collection $orderByRef,
        array $refundTxnOrderIds,
        array $paymentIds
    ): array {
        $meta = $txn->metadata ?? [];
        $desc = (string) $txn->description;
        $amount = (float) $txn->amount;

        // REFUND — a Refund row points at this transaction, or it was
        // written by refundOrder (description + refund_type metadata +
        // order reference).
        if (isset($refundTxnOrderIds[$txn->id])
            || !empty($meta['refund_type'])
            || ($desc === 'Order refund' && $amount > 0)) {
            return ['type' => 'refund', 'confidence' => 'high', 'reason' => 'linked to an order refund'];
        }

        // PURCHASE — debit carrying the order marker, or a debit whose
        // reference is a real order number.
        if ($amount < 0 && (($meta['order_type'] ?? null) === 'number_purchase'
            || ($txn->reference && $orderByRef->has($txn->reference))
            || ($desc === 'Order purchase'))) {
            return ['type' => 'purchase', 'confidence' => 'high', 'reason' => 'order purchase debit'];
        }

        // ADJUSTMENT — admin credit/debit, payment reversal, referral reward.
        if (!empty($meta['admin_id'])
            || !empty($meta['reward_id'])
            || str_starts_with($desc, 'Admin credit')
            || str_starts_with($desc, 'Admin debit')
            || str_starts_with($desc, 'Payment refund')) {
            return ['type' => 'adjustment', 'confidence' => 'high', 'reason' => 'admin/payment/reward adjustment'];
        }

        // DEPOSIT — credit linked to a real payment record.
        $paymentId = $meta['payment_id'] ?? $txn->reference;
        if ($amount > 0 && $paymentId && isset($paymentIds[$paymentId])) {
            return ['type' => 'deposit', 'confidence' => 'high', 'reason' => 'linked to payment ' . $paymentId];
        }
        if ($amount > 0 && str_starts_with($desc, 'Wallet deposit')) {
            // Description convention without a resolvable payment link —
            // the credit came through the deposit path but the payment
            // row is missing: ambiguous, not a confident retype.
            return ['type' => 'deposit', 'confidence' => 'ambiguous',
                'reason' => 'deposit description but no matching payment record'];
        }

        // Already typed and self-consistent → keep, high confidence.
        if ($txn->type === 'deposit' && $amount > 0) {
            return ['type' => 'deposit', 'confidence' => 'ambiguous',
                'reason' => 'unlabelled credit — no payment/order/refund linkage'];
        }
        if (in_array($txn->type, ['purchase', 'withdrawal']) && $amount < 0
            && ($meta['order_type'] ?? null) !== 'number_purchase'
            && !($txn->reference && $orderByRef->has($txn->reference))) {
            return ['type' => $txn->type, 'confidence' => 'ambiguous',
                'reason' => 'unlabelled debit — no order linkage'];
        }

        return ['type' => $txn->type, 'confidence' => 'high', 'reason' => 'already consistent'];
    }

    /**
     * Per-order consistency check. Returns a finding array or null.
     */
    protected function auditOrder(Order $order): ?array
    {
        $hasRefundRow = Refund::where('order_id', $order->id)->exists();
        $hasRefundTxn = WalletTransaction::where('reference', $order->order_id)
            ->where('type', 'refund')->exists();
        $woundDown = in_array($order->status, ['cancelled', 'expired', 'failed', 'refunded'], true);

        // Money was debited, order ended, nothing returned.
        if ($woundDown && (float) $order->refund_amount <= 0 && !$hasRefundRow) {
            return [
                'order' => $order,
                'issue' => 'refund_owed',
                'detail' => "Order ended as '{$order->status}' but no refund was recorded — customer may be owed " . xaf($order->selling_price),
            ];
        }

        // Refund recorded but no matching wallet transaction.
        if (((float) $order->refund_amount > 0 || $hasRefundRow) && !$hasRefundTxn) {
            return [
                'order' => $order,
                'issue' => 'refund_without_txn',
                'detail' => 'Refund record exists but no REFUND wallet transaction found.',
            ];
        }

        // Wound-down order that held an activation but the provider
        // outcome was never recorded (pre-fix history).
        if ($woundDown && $order->provider_activation_id && !$order->provider_refund_status) {
            return [
                'order' => $order,
                'issue' => 'provider_outcome_unrecorded',
                'detail' => 'Activation ' . $order->provider_activation_id . ' ended with no recorded provider outcome — cost recovery unverified.',
            ];
        }

        return null;
    }

    /**
     * Per-customer ledger integrity: actual balance vs Σ transactions.
     */
    protected function customerReconciliation(Collection $transactions): array
    {
        $rows = [];
        $wallets = Wallet::with('user')->get();

        foreach ($wallets as $wallet) {
            $txns = $transactions->where('wallet_id', $wallet->id)
                ->where('status', 'completed');
            $expected = round((float) $txns->sum('amount'), 2);
            $actual = round((float) $wallet->balance, 2);
            $diff = round($actual - $expected, 2);

            $rows[] = [
                'user' => $wallet->user,
                'wallet' => $wallet,
                'expected' => $expected,
                'actual' => $actual,
                'difference' => $diff,
                'match' => abs($diff) < 0.01,
                'deposits' => (float) $txns->where('type', 'deposit')->sum('amount'),
                'refunds' => (float) $txns->where('type', 'refund')->sum('amount'),
                'purchases' => (float) $txns->where('type', 'purchase')->sum('amount'),
                'adjustments' => (float) $txns->where('type', 'adjustment')->sum('amount'),
            ];
        }

        return $rows;
    }

    /**
     * HeroSMS provider-cost reconciliation — why the provider balance
     * is decreasing, in ledger terms.
     */
    protected function providerReconciliation(Collection $orders): array
    {
        $activations = $orders->whereNotNull('provider_activation_id');

        return [
            'activations' => $activations->count(),
            'completed' => $activations->whereIn('status', ['completed', 'sms_received'])->count(),
            'wound_down' => $activations->whereIn('status', ['cancelled', 'expired', 'failed', 'refunded'])->count(),
            'active' => $activations->filter->isActive()->count(),
            'provider_released' => $activations->whereIn('provider_refund_status', ['released', 'provider_resolved'])->count(),
            'provider_consumed' => $activations->where('provider_refund_status', 'consumed')->count(),
            'provider_unrecorded' => $activations
                ->whereIn('status', ['cancelled', 'expired', 'failed', 'refunded'])
                ->whereNull('provider_refund_status')->count(),
            'total_cost' => (float) $activations->sum('purchase_price'),
            'recovered_cost' => (float) $activations
                ->whereIn('provider_refund_status', ['released', 'provider_resolved'])
                ->sum('purchase_price'),
            'lost_cost' => (float) $activations
                ->where('provider_refund_status', 'consumed')
                ->sum('purchase_price'),
            'unverified_cost' => (float) $activations
                ->whereIn('status', ['cancelled', 'expired', 'failed', 'refunded'])
                ->whereNull('provider_refund_status')
                ->sum('purchase_price'),
        ];
    }

    /**
     * Platform-level reconciliation: every XAF in wallets vs the ledger.
     */
    protected function platformTotals(Collection $transactions): array
    {
        $completed = $transactions->where('status', 'completed');

        return [
            'wallet_balances' => round((float) Wallet::sum('balance'), 2),
            'ledger_net' => round((float) $completed->sum('amount'), 2),
            'deposits' => round((float) $completed->where('type', 'deposit')->sum('amount'), 2),
            'refunds' => round((float) $completed->where('type', 'refund')->sum('amount'), 2),
            'purchases' => round(abs((float) $completed->where('type', 'purchase')->sum('amount')), 2),
            'adjustments' => round((float) $completed->where('type', 'adjustment')->sum('amount'), 2),
        ];
    }
}
