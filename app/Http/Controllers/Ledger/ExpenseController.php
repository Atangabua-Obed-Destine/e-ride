<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Models\Expense;
use App\Ledger\Models\ExpenseCategory;

/** paxhitestbed Admin\ExpenseController (permissions expense-*), without its school budget fields. The work is in IncomeExpenseController. */
class ExpenseController extends IncomeExpenseController
{
    protected function side(): string
    {
        return 'expense';
    }

    protected function model(): string
    {
        return Expense::class;
    }

    protected function categoryModel(): string
    {
        return ExpenseCategory::class;
    }
}
