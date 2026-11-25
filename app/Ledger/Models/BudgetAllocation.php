<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * A slice of a budget (paxhitestbed BudgetAllocation).
 *
 * On the Income & Expenditure sheet it is a figure on one budget line
 * (budget_line_id). On a departmental budget it is earmarked for an expense
 * category and/or a ledger account, optionally at one location. Its spend is
 * what the posted ledger holds for that target in the budget's period, written
 * here by BudgetActualsService::refresh() - never incremented by hand.
 */
class BudgetAllocation extends Model
{
    use Auditable, BelongsToBusiness;

    const PERIODS = ['yearly', 'q1', 'q2', 'q3', 'q4', 'semester1', 'semester2'];

    protected $fillable = [
        'budget_id', 'budget_line_id', 'expense_category_id', 'account_id', 'location_id', 'title',
        'allocated_amount', 'spent_amount', 'remaining_amount', 'period', 'description', 'is_active',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function budgetLine()
    {
        return $this->belongsTo(BudgetLine::class, 'budget_line_id');
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id'); // E-Ride: the Income & Expense categories (posinnova: POS's)
    }

    public function location()
    {
        return $this->belongsTo(\App\Ledger\ERide\BudgetDepartment::class, 'location_id'); // E-Ride: the payroll's departments (posinnova: business locations)
    }

    public function getUtilizationPercentageAttribute()
    {
        if ((float) $this->allocated_amount == 0) {
            return 0;
        }

        return round(((float) $this->spent_amount / (float) $this->allocated_amount) * 100, 2);
    }

    public function getStatusColorAttribute()
    {
        $p = $this->utilization_percentage;
        if ($p < 50) {
            return 'success';
        }
        if ($p < 75) {
            return 'info';
        }
        if ($p < 90) {
            return 'warning';
        }

        return 'danger';
    }

    /** What the allocation is for, in words: the category, else the line, else the account, else its title. */
    public function getTargetLabelAttribute(): string
    {
        if ($this->expenseCategory) {
            return (string) $this->expenseCategory->name;
        }
        if ($this->budgetLine) {
            return $this->budgetLine->label;
        }
        if ($this->account) {
            return $this->account->account_code.' '.\App\Ledger\Services\Preview::accountName($this->account);
        }

        return (string) $this->title;
    }

    public static function periodLabel(?string $period): string
    {
        return $period ? __('ledger_budget.period_'.$period) : '';
    }

    public function isOverBudget(): bool
    {
        return (float) $this->spent_amount > (float) $this->allocated_amount;
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function (BudgetAllocation $a) {
            $a->remaining_amount = (float) $a->allocated_amount - (float) $a->spent_amount;
        });
    }

    public function getAuditDescription($event)
    {
        return "Budget allocation {$event}: budget #{$this->budget_id} {$this->title} ".number_format((float) $this->allocated_amount, 2);
    }
}
