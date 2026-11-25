<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Models\Income;
use App\Ledger\Models\IncomeCategory;

/** paxhitestbed Admin\IncomeController (permissions income-*). The work is in IncomeExpenseController. */
class IncomeController extends IncomeExpenseController
{
    protected function side(): string
    {
        return 'income';
    }

    protected function model(): string
    {
        return Income::class;
    }

    protected function categoryModel(): string
    {
        return IncomeCategory::class;
    }
}
