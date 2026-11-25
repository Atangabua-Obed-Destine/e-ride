<?php

namespace App\Ledger\Exports;

use App\Ledger\Services\FinancialStatements;
use Carbon\Carbon;

/**
 * The balance sheet, one row per account: side (actif / passif), section,
 * code, account, amount, and the comparative date's amount when asked for.
 */
class BalanceSheetExport extends LedgerRowsExport
{
    /** @param  array  $d  FinancialStatements::balanceSheet() plus 'comparative' */
    public function __construct(array $d, string $asOfDate, ?string $comparativeDate)
    {
        $cmp = $d['comparative'] ?? null;
        $head = [__('ledger_gl.x_side'), __('ledger_gl.x_section'), __('ledger_gl.x_code'), __('ledger_gl.x_account'), Carbon::parse($asOfDate)->format('d/m/Y')];
        if ($cmp) {
            $head[] = Carbon::parse($comparativeDate)->format('d/m/Y');
        }
        $this->rows[] = $head;

        $add = function ($side, $section, $code, $name, $amount, $cmpAmount = null) use ($cmp) {
            $row = [$side, $section, (string) $code, $name, self::amount($amount)];
            if ($cmp) {
                $row[] = self::amount($cmpAmount);
            }
            $this->rows[] = $row;
        };
        $sections = [
            __('ledger_gl.actif') => [
                'fixedAssets' => __('ledger_gl.x_fixed_assets'),
                'inventory' => __('ledger_gl.x_inventory'),
                'receivables' => __('ledger_gl.x_receivables'),
                'cashAndBanks' => __('ledger_gl.x_cash_banks'),
            ],
            __('ledger_gl.passif') => [
                'equity' => __('ledger_gl.x_equity'),
                'financialDebts' => __('ledger_gl.x_borrowings'),
                'payables' => __('ledger_gl.x_payables'),
                'treasuryLiabilities' => __('ledger_gl.x_overdrafts'),
            ],
        ];
        foreach ($sections as $side => $list) {
            foreach ($list as $key => $label) {
                foreach ($d[$key] as $a) {
                    $add($side, $label, $a->account_code, FinancialStatements::name($a), $a->balance,
                        $cmp ? ($cmp[$key]->firstWhere('id', $a->id)->balance ?? 0) : null);
                }
                if ($key === 'equity') {
                    $add($side, $label, '', __('ledger_gl.x_report_a_nouveau'), $d['priorResult'], $cmp['priorResult'] ?? null);
                    $add($side, $label, '', __('ledger_gl.result_for_year'), $d['yearResult'], $cmp['yearResult'] ?? null);
                }
            }
        }
        $add(__('ledger_gl.actif'), '', '', __('ledger_gl.x_total_assets'), $d['totalAssets'], $cmp['totalAssets'] ?? null);
        $add(__('ledger_gl.passif'), '', '', __('ledger_gl.x_total_liabilities'), $d['totalLiabilities'], $cmp['totalLiabilities'] ?? null);
    }

    public function title(): string
    {
        return mb_substr(__('ledger_gl.balance_sheet'), 0, 31);
    }
}
