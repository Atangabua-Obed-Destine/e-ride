<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Ledger\Models\AllowanceType;

/** paxhitestbed Admin\AllowanceTypeController (permissions allowance-type-*): with "Taxed on payroll". */
class AllowanceTypeController extends HrListController
{
    protected function path(): string
    {
        return 'allowance-type';
    }

    protected function model(): string
    {
        return AllowanceType::class;
    }

    protected function titleKey(): string
    {
        return 'allowance_types';
    }

    protected function columns(): array
    {
        return ['description', 'is_taxable'];
    }
}
