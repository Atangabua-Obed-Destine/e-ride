<?php

namespace App\Ledger\Exports;

use App\Ledger\Services\FinancialStatements;
use Illuminate\Support\Collection;

/**
 * The trial balance as the screen shows it: opening, period and closing
 * balances, each as debit and credit, and a total row.
 */
class TrialBalanceExport extends LedgerRowsExport
{
    /** @param  Collection  $accounts  the trial balance screen's accounts (opening_balance in the normal direction, period_debit, period_credit) */
    public function __construct(Collection $accounts)
    {
        $this->rows[] = [__('ledger_gl.x_code'), __('ledger_gl.x_account'), __('ledger_gl.opening_debit'), __('ledger_gl.opening_credit'),
            __('ledger_gl.period_debit'), __('ledger_gl.period_credit'), __('ledger_gl.closing_debit'), __('ledger_gl.closing_credit')];
        $totals = array_fill(0, 6, 0.0);
        foreach ($accounts as $a) {
            $sign = $a->normal_balance === 'debit' ? 1 : -1;
            $opening = $sign * (float) $a->opening_balance;
            $closing = $opening + $a->period_debit - $a->period_credit;
            $line = [max($opening, 0), max(-$opening, 0), (float) $a->period_debit, (float) $a->period_credit, max($closing, 0), max(-$closing, 0)];
            foreach ($line as $i => $v) {
                $totals[$i] += $v;
            }
            $this->rows[] = array_merge([(string) $a->account_code, FinancialStatements::name($a)], array_map([self::class, 'amount'], $line));
        }
        $this->rows[] = array_merge(['', __('ledger_gl.total')], array_map([self::class, 'amount'], $totals));
    }

    public function title(): string
    {
        return mb_substr(__('ledger_gl.trial_balance'), 0, 31);
    }
}
