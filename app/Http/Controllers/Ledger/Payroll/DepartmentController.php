<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Ledger\Models\Department;

/** paxhitestbed Admin\DepartmentController (permissions department-*). */
class DepartmentController extends HrListController
{
    protected function path(): string
    {
        return 'department';
    }

    protected function model(): string
    {
        return Department::class;
    }

    protected function titleKey(): string
    {
        return 'departments';
    }

    protected function columns(): array
    {
        return [];
    }

    protected function inUse($row): bool
    {
        return $row->staff()->exists();
    }
}
