<?php

namespace App\Ledger\ERide;

use App\Ledger\Models\Department;

/**
 * E-Ride: the "department" of posinnova's budgets. posinnova points budgets, budget lines and
 * allocations (location_id) at a business location; the bus books point them at the payroll's
 * departments (App\Ledger\Models\Department), named under `name` as the budget screens read it.
 */
class BudgetDepartment extends Department
{
    public function getNameAttribute(): string
    {
        return (string) $this->title;
    }
}
