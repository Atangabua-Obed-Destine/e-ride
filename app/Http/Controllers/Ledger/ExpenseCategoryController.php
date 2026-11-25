<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Models\ExpenseCategory;

/** paxhitestbed Admin\ExpenseCategoryController (permissions expense-category-*). The work is in IncomeCategoryController. */
class ExpenseCategoryController extends IncomeCategoryController
{
    protected function side(): string
    {
        return 'expense';
    }

    protected function model(): string
    {
        return ExpenseCategory::class;
    }
}
