<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** paxhitestbed PayrollTaxLine: what one tax took from one payroll, and the liability account it is owed on. */
class PayrollTaxLine extends Model
{
    use BelongsToBusiness;

    protected $table = 'payroll_tax_lines';

    protected $fillable = ['business_id', 'payroll_id', 'staff_id', 'salary_month', 'tax_group_id', 'tax_setting_id', 'label',
        'employee_amount', 'employer_amount', 'liability_account_id'];

    protected $casts = ['salary_month' => 'date', 'employee_amount' => 'float', 'employer_amount' => 'float'];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function liabilityAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'liability_account_id');
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->employee_amount + (float) $this->employer_amount;
    }
}
