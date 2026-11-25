<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Ledger\Models\Designation;

/** paxhitestbed Admin\DesignationController (permissions designation-*). */
class DesignationController extends HrListController
{
    protected function path(): string
    {
        return 'designation';
    }

    protected function model(): string
    {
        return Designation::class;
    }

    protected function titleKey(): string
    {
        return 'designations';
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
