<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FacebookAuditService;
use App\Services\ReconciliationService;
use Carbon\Carbon;

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
     * Facebook/Meta compatibility audit — read-only. Answers which
     * prefixes/countries correlate with no-SMS failures and how much
     * HeroSMS cost was or was not recovered on them. Optional ?days=N
     * window; default is all history.
     */
    public function facebook(FacebookAuditService $audit)
    {
        $days = request()->integer('days');
        $since = $days > 0 ? Carbon::now()->subDays($days) : null;
        $report = $audit->buildReport($since);

        return view('admin.reconciliation.facebook', compact('report', 'days'));
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
