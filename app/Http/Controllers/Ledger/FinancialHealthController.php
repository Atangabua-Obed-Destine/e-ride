<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Services\FinancialHealth;
use Illuminate\Http\Request;

/**
 * Financial health (paxhitestbed AccountingReportsController@financialHealth):
 * read-only, from the posted ledger of the business. Guarded by
 * accounting-report-view, as in paxhitestbed.
 */
class FinancialHealthController extends LedgerController
{
    public function index(Request $request)
    {
        $this->allow('accounting-report-view');
        $request->validate(['as_of' => 'nullable|date']);

        return view('ledger.financial-health.index', [
            'title' => __('ledger_assets.financial_health'),
            'h' => (new FinancialHealth())->summary($request->input('as_of')),
        ]);
    }
}
