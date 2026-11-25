<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Ledger\Models\DeductionType;

/** paxhitestbed Admin\DeductionTypeController (permissions deduction-type-*). */
class DeductionTypeController extends HrListController
{
    protected function path(): string
    {
        return 'deduction-type';
    }

    protected function model(): string
    {
        return DeductionType::class;
    }

    protected function titleKey(): string
    {
        return 'deduction_types';
    }
}
