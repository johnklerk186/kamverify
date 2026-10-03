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
}
