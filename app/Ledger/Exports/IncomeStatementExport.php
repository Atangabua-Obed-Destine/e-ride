<?php

namespace App\Ledger\Exports;

use App\Ledger\Services\FinancialStatements;

/**
 * The income statement: revenue (class 7), expenses (class 6), other results
 * (class 8) account by account with their totals, and the net result.
 */
class IncomeStatementExport extends LedgerRowsExport
{
    /** @param  array  $d  FinancialStatements::incomeStatement() */
    public function __construct(array $d)
    {
        $this->rows[] = [__('ledger_gl.x_section'), __('ledger_gl.x_code'), __('ledger_gl.x_account'), __('ledger_gl.amount')];
        $rev = __('ledger_gl.x_revenue_class');
        $exp = __('ledger_gl.x_expenses_class');
        $oth = __('ledger_gl.x_other_class');
        foreach ($d['revenue'] as $a) {
            $this->rows[] = [$rev, (string) $a->account_code, FinancialStatements::name($a), self::amount($a->amount)];
        }
        $this->rows[] = [$rev, '', __('ledger_gl.total_revenue'), self::amount($d['totalRevenue'])];
        foreach ($d['expenses'] as $a) {
            $this->rows[] = [$exp, (string) $a->account_code, FinancialStatements::name($a), self::amount($a->amount)];
        }
        $this->rows[] = [$exp, '', __('ledger_gl.total_expenses'), self::amount($d['totalExpenses'])];
        foreach ($d['otherResults'] as $a) {
            $this->rows[] = [$oth, (string) $a->account_code, FinancialStatements::name($a), self::amount($a->amount)];
        }
        $this->rows[] = ['', '', __('ledger_gl.x_net_result'), self::amount($d['netProfit'])];
    }

    public function title(): string
    {
        return mb_substr(__('ledger_gl.income_statement'), 0, 31);
    }
}
