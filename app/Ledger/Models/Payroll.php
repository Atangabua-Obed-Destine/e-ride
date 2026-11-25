<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * paxhitestbed Payroll: one staff member, one month. status 0 generated (a draft that can be
 * regenerated, and approved when the books require it), 1 paid. Paying posts the whole entry
 * (PostingRules::forPayroll, paxhitestbed's PayrollAccountingService); unpaying reverses it.
 */
class Payroll extends Model
{
    use Auditable, BelongsToBusiness;

    protected $table = 'payrolls';

    protected $fillable = [
        'business_id', 'staff_id', 'basic_salary', 'salary_type', 'total_earning', 'total_allowance', 'bonus', 'total_deduction',
        'gross_salary', 'taxable_salary', 'hours_worked', 'paid_days', 'period_days', 'tax', 'employer_tax', 'net_salary', 'total_cost',
        'salary_month', 'pay_date', 'payment_method', 'payment_account_id', 'note', 'status', 'approved_by', 'approved_at',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'salary_month' => 'date',
        'pay_date' => 'date',
        'approved_at' => 'datetime',
        'basic_salary' => 'float', 'total_earning' => 'float', 'total_allowance' => 'float', 'bonus' => 'float',
        'total_deduction' => 'float', 'gross_salary' => 'float', 'taxable_salary' => 'float', 'tax' => 'float',
        'employer_tax' => 'float', 'net_salary' => 'float', 'total_cost' => 'float', 'status' => 'integer',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function details()
    {
        return $this->hasMany(PayrollDetail::class, 'payroll_id');
    }

    public function taxLines()
    {
        return $this->hasMany(PayrollTaxLine::class, 'payroll_id');
    }

    public function approver()
    {
        return $this->belongsTo(\App\User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return ! empty($this->approved_at);
    }

    public function isPaid(): bool
    {
        return (int) $this->status === 1;
    }

    /** What loans and advances this payroll takes back (credited to staff advances, not to the expense). */
    public function loanRepayments(): float
    {
        return round((float) $this->details->where('status', 0)->whereNotNull('staff_loan_id')->sum('amount'), 2);
    }

    public function getAuditDescription($event)
    {
        $name = $this->staff ? $this->staff->name : 'Staff #'.$this->staff_id;

        return "Payroll {$event} for {$name}: ".($this->salary_month?->format('Y-m') ?? '').', Net Salary: '.number_format((float) $this->net_salary, 0);
    }
}
