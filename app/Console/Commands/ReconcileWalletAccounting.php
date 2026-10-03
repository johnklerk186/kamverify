<?php

namespace App\Console\Commands;

use App\Services\AuditService;
use App\Services\ReconciliationService;
use Illuminate\Console\Command;

/**
 * Full financial reconciliation. DRY-RUN BY DEFAULT: prints the audit
 * report and exactly what --apply would change. Nothing is written
 * without --apply. Every write is recorded in audit_logs with
 * before/after values (action: reconcile.*).
 */
class ReconcileWalletAccounting extends Command
{
    protected $signature = 'wallet:reconcile {--apply : Write the corrections (default is dry-run)}';

    protected $description = 'Audit wallet transactions, orders and balances; report (or repair with --apply) misclassified records.';

    public function handle(ReconciliationService $recon, AuditService $audit): int
    {
        $apply = (bool) $this->option('apply');
        $report = $recon->buildReport();

        $this->info('══════════════════════════════════════════════════════════');
        $this->info(' KAMVERIFY FINANCIAL RECONCILIATION — ' . $report['generated_at']);
        $this->info(' ' . ($apply ? 'APPLY MODE' : 'DRY RUN — pass --apply to write corrections'));
        $this->info('══════════════════════════════════════════════════════════');

        // ── Transactions ──────────────────────────────────────────
        $t = $report['transactions'];
        $this->newLine();
        $this->info("TRANSACTIONS AUDITED: {$t['total']}");
        $this->line("  Proposed reclassifications: " . count($t['proposals']) . "  (" . xaf($t['xaf_affected']) . " affected)");
        foreach ($t['by_target'] as $target => $n) {
            $this->line("    → {$target}: {$n}");
        }
        foreach ($t['proposals'] as $p) {
            $this->line(sprintf('    %s: %s → %s (%s, %s)',
                $p['transaction']->transaction_id, $p['from'], $p['to'],
                xaf($p['transaction']->amount), $p['reason']));
        }
        $this->line("  Ambiguous (flagged, never changed): " . count($t['ambiguous']));
        foreach ($t['ambiguous'] as $a) {
            $this->warn(sprintf('    %s: %s — %s (%s)',
                $a['transaction']->transaction_id, $a['transaction']->type,
                $a['reason'], xaf($a['transaction']->amount)));
        }

        // ── Wallets ───────────────────────────────────────────────
        $this->newLine();
        $this->info('WALLET TOTAL REPAIRS: ' . count($report['wallets']));
        foreach ($report['wallets'] as $w) {
            $this->line(sprintf('    wallet #%s (user %s): deposited %s → %s, withdrawn %s → %s',
                $w['wallet']->id, $w['wallet']->user_id,
                xaf($w['deposited_from']), xaf($w['deposited_to']),
                xaf($w['withdrawn_from']), xaf($w['withdrawn_to'])));
        }

        // ── Orders ────────────────────────────────────────────────
        $o = $report['orders'];
        $this->newLine();
        $this->info("ORDERS AUDITED: {$o['total']}   Refundable outstanding: " . xaf($o['refundable_outstanding']));
        foreach ($o['findings'] as $f) {
            $this->warn("    {$f['order']->order_id} [{$f['issue']}] {$f['detail']}");
        }
        $this->line("  Profit corrections: " . count($o['profit_fixes']));
        foreach ($o['profit_fixes'] as $f) {
            $this->line(sprintf('    %s: profit %s → %s',
                $f['order']->order_id, xaf($f['from']), xaf($f['to'])));
        }

        // ── Customers ─────────────────────────────────────────────
        $this->newLine();
        $this->info('CUSTOMER RECONCILIATION:');
        $mismatches = 0;
        foreach ($report['customers'] as $c) {
            $flag = $c['match'] ? 'MATCH' : 'DISCREPANCY';
            if (!$c['match']) $mismatches++;
            $this->line(sprintf('    %s %s (#%s): actual %s vs expected %s (diff %s)',
                $flag, $c['user']->email ?? '—', $c['user']->id ?? '—',
                xaf($c['actual']), xaf($c['expected']), xaf($c['difference'])));
        }
        $this->line("  {$mismatches} of " . count($report['customers']) . " customers out of balance.");

        // ── Provider ──────────────────────────────────────────────
        $pr = $report['provider'];
        $this->newLine();
        $this->info('HEROSMS PROVIDER RECONCILIATION:');
        $this->line("  Activations: {$pr['activations']}  (completed {$pr['completed']}, wound-down {$pr['wound_down']}, active {$pr['active']})");
        $this->line("  Provider cost total: " . xaf(usdToXaf($pr['total_cost'])) . " USD {$pr['total_cost']}");
        $this->line("  Cost recovered (released/resolved): {$pr['provider_released']} activations, " . xaf(usdToXaf($pr['recovered_cost'])));
        $this->line("  Cost consumed (kept by provider):   {$pr['provider_consumed']} activations, " . xaf(usdToXaf($pr['lost_cost'])));
        $this->line("  Cost unverified (no outcome recorded): {$pr['provider_unrecorded']} activations, " . xaf(usdToXaf($pr['unverified_cost'])) . " — likely the HeroSMS balance drain");

        // ── Platform totals ───────────────────────────────────────
        $pl = $report['platform'];
        $this->newLine();
        $this->info('PLATFORM:');
        $this->line("  Wallet balances: " . xaf($pl['wallet_balances']) . "  vs ledger net " . xaf($pl['ledger_net'])
            . "  → " . (abs($pl['wallet_balances'] - $pl['ledger_net']) < 0.01 ? 'MATCH' : 'DISCREPANCY ' . xaf($pl['wallet_balances'] - $pl['ledger_net'])));
        $this->line("  Deposits " . xaf($pl['deposits']) . " | Refunds " . xaf($pl['refunds'])
            . " | Purchases " . xaf($pl['purchases']) . " | Adjustments " . xaf($pl['adjustments']));

        // ── Apply ─────────────────────────────────────────────────
        if (!$apply) {
            $this->newLine();
            $this->info('Dry run complete. No records were changed. Re-run with --apply to write the corrections above.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Applying corrections…');
        $written = 0;

        foreach ($t['proposals'] as $p) {
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
                ['total_deposited' => $w['deposited_to'], 'total_withdrawn' => $w['withdrawn_to'], 'reason' => 'recomputed from ledger']);
            $w['wallet']->update([
                'total_deposited' => $w['deposited_to'],
                'total_withdrawn' => $w['withdrawn_to'],
            ]);
            $written++;
        }

        foreach ($o['profit_fixes'] as $f) {
            $audit->log('reconcile.order_profit', $f['order'],
                ['profit' => $f['from']],
                ['profit' => $f['to'], 'reason' => 'selling − refund − unrecovered provider cost']);
            $f['order']->update(['profit' => $f['to']]);
            $written++;
        }

        $this->info("{$written} correction(s) written — every change is in audit_logs under reconcile.*");
        return self::SUCCESS;
    }
}
