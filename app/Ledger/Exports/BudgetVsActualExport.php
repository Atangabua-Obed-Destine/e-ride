<?php

namespace App\Ledger\Exports;

/** Budget vs Actual as rows (paxhitestbed's budget_vs_actual CSV columns). */
class BudgetVsActualExport extends LedgerRowsExport
{
    public function __construct(array $report)
    {
        $this->rows[] = [__('ledger_budget.code'), __('ledger_budget.line'), __('ledger_budget.section'), __('ledger_budget.budget'),
            __('ledger_budget.actual'), __('ledger_budget.variance'), __('ledger_budget.variance_percentage')];
        foreach ($report as $r) {
            $this->rows[] = [$r['account_code'], ($r['is_header'] ? '' : '  ').$r['account_name'], __('ledger_budget.section_'.$r['section']),
                self::amount($r['budget']), self::amount($r['actual']), self::amount($r['variance']), round($r['variance_pct'], 1)];
        }
    }

    public function title(): string
    {
        return mb_substr(__('ledger_budget.budget_vs_actual'), 0, 31);
    }
}
