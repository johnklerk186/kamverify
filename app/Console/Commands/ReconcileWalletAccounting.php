<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Historical ledger repair for records written before typed
 * transactions existed (refunds stored as 'deposit', purchases as
 * 'withdrawal', rewards as 'deposit').
 *
 * DRY-RUN BY DEFAULT — prints exactly what would change.
 * Pass --apply to write the corrections.
 *
 * What it fixes:
 *   wallet_transactions.type  — retyped by description/metadata markers
 *   wallets.total_deposited   — recomputed from real 'deposit' rows only
 *   wallets.total_withdrawn   — recomputed from all debit rows
 *   orders.profit             — refunded orders recomputed to reflect
 *                               the returned amount (+ provider loss
 *                               when the activation was consumed)
 *
 * It never touches balances — the balance column is the authoritative
 * ledger position and is only moved by WalletService under row locks.
 */
class ReconcileWalletAccounting extends Command
{
    protected $signature = 'wallet:reconcile {--apply : Write the corrections (default is dry-run)}';

    protected $description = 'Report (or repair, with --apply) historical wallet transaction types and wallet/order totals.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'APPLY MODE — corrections will be written.' : 'DRY RUN — nothing will be written. Pass --apply to apply.');

        $changes = 0;

        // ---------------------------------------------------------
        // 1. Retype historical wallet transactions
        // ---------------------------------------------------------
        $retypes = [
            // label => [target type, closure deciding if a row matches]
            'refund' => fn ($t) => $t->type === 'deposit'
                && ((string) $t->description === 'Order refund'
                    || !empty($t->metadata['refund_type'])),
            'purchase' => fn ($t) => $t->type === 'withdrawal'
                && ($t->metadata['order_type'] ?? null) === 'number_purchase',
            'adjustment' => fn ($t) => ($t->type === 'deposit' && !empty($t->metadata['reward_id']))
                || str_starts_with((string) $t->description, 'Admin credit')
                || str_starts_with((string) $t->description, 'Admin debit')
                || str_starts_with((string) $t->description, 'Payment refund'),
        ];

        foreach ($retypes as $target => $matches) {
            $rows = WalletTransaction::all()->filter($matches);
            foreach ($rows as $row) {
                $this->line(sprintf(
                    '  txn %s: type %s → %s (%s, %s, %s)',
                    $row->transaction_id, $row->type, $target,
                    $row->amount, $row->description, $row->created_at
                ));
                if ($apply) {
                    $row->update(['type' => $target]);
                }
                $changes++;
            }
        }

        // ---------------------------------------------------------
        // 2. Recompute wallet totals from the (corrected) ledger
        // ---------------------------------------------------------
        foreach (Wallet::all() as $wallet) {
            $deposited = (float) WalletTransaction::where('wallet_id', $wallet->id)
                ->where('type', 'deposit')->sum('amount');
            $withdrawn = (float) WalletTransaction::where('wallet_id', $wallet->id)
                ->where('amount', '<', 0)->sum('amount') * -1;

            if (abs($deposited - (float) $wallet->total_deposited) > 0.01
                || abs($withdrawn - (float) $wallet->total_withdrawn) > 0.01) {
                $this->line(sprintf(
                    '  wallet #%s (user %s): total_deposited %s → %s, total_withdrawn %s → %s',
                    $wallet->id, $wallet->user_id,
                    $wallet->total_deposited, $deposited,
                    $wallet->total_withdrawn, $withdrawn
                ));
                if ($apply) {
                    $wallet->update([
                        'total_deposited' => $deposited,
                        'total_withdrawn' => $withdrawn,
                    ]);
                }
                $changes++;
            }
        }

        // ---------------------------------------------------------
        // 3. Recompute profit on refunded orders
        // ---------------------------------------------------------
        $refunded = Order::where('refund_amount', '>', 0)->get();
        foreach ($refunded as $order) {
            $costLost = $order->provider_refund_status === 'consumed'
                ? (float) $order->purchase_price : 0.0;
            $expected = round((float) $order->selling_price - (float) $order->refund_amount - $costLost, 2);

            if (abs((float) $order->profit - $expected) > 0.01) {
                $this->line(sprintf(
                    '  order %s: profit %s → %s (refund %s, provider %s)',
                    $order->order_id, $order->profit, $expected,
                    $order->refund_amount, $order->provider_refund_status ?? 'unrecorded'
                ));
                if ($apply) {
                    $order->update(['profit' => $expected]);
                }
                $changes++;
            }
        }

        $this->info($changes === 0
            ? 'Ledger is already consistent — nothing to change.'
            : "{$changes} record(s) " . ($apply ? 'corrected.' : 'would be corrected. Re-run with --apply.'));

        return self::SUCCESS;
    }
}
