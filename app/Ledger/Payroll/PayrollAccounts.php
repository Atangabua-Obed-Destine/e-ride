<?php

namespace App\Ledger\Payroll;

use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Services\DefaultAccount;
use App\Ledger\Services\SyscohadaChart;

/**
 * The ledger accounts payroll posts to, from Mapping settings (group "Payroll and tax"), with
 * paxhitestbed's fallback when a set of books has no row yet (books set up before payroll
 * existed): the account of the default code (SyscohadaChart::defaultMappings()).
 *
 *   payroll           661 salaries                      (paxhitestbed mapping 'payroll')
 *   payroll_employer  664 employer social charges
 *   payroll_tax       447 taxes withheld (the State)    (paxhitestbed mapping 'payroll_tax')
 *   payroll_social    431 social security (CNPS)
 *   staff_advances    421 staff advances                (paxhitestbed PayrollSetting advance_account_id)
 */
class PayrollAccounts
{
    public const TYPES = ['payroll', 'payroll_employer', 'payroll_tax', 'payroll_social', 'staff_advances', 'salaries_payable'];

    public static function id(string $type): ?int
    {
        $id = DefaultAccount::id($type);
        if ($id) {
            return (int) $id;
        }
        $code = SyscohadaChart::defaultMappings()[$type] ?? null;

        return $code ? (ChartOfAccount::where('account_code', $code)->value('id') ?: null) : null;
    }

    /** The account, or a refusal naming the mapping to set. */
    public static function need(string $type): int
    {
        $id = self::id($type);
        if (! $id) {
            throw new \RuntimeException(__('ledger_payroll.err_no_mapping', ['what' => __('ledger_payroll.map_'.$type)]));
        }

        return $id;
    }
}
