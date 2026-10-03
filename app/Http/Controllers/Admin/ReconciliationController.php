<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReconciliationService;

/**
 * Admin-only financial reconciliation: per-customer ledger integrity,
 * platform totals, HeroSMS provider-cost recovery, and the list of
 * records that still need manual review. Read-only — corrections are
 * applied via `php artisan wallet:reconcile --apply`.
 */
class ReconciliationController extends Controller
{
    public function index(ReconciliationService $recon)
    {
        $report = $recon->buildReport();

        return view('admin.reconciliation.index', compact('report'));
    }

    /**
     * Apply the safe corrections shown on this page — the same audited
     * path as `wallet:reconcile --apply`. Ambiguous records are never
     * touched; every write lands in audit_logs under reconcile.*.
     */
    public function apply(ReconciliationService $recon)
    {
        $report = $recon->buildReport();
        $written = $recon->applyReport($report);

        return back()->with('success',
            $written === 0
                ? 'Ledger is already consistent — nothing to correct.'
                : "{$written} correction(s) applied. Audit trail: Audit Logs → reconcile.*");
    }
}
