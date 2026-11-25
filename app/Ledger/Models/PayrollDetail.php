<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** paxhitestbed PayrollDetail: an allowance (status 1) or a deduction (status 0) on a payroll; a loan repayment names its loan. */
class PayrollDetail extends Model
{
    use BelongsToBusiness;

    protected $table = 'payroll_details';

    protected $fillable = ['business_id', 'payroll_id', 'staff_loan_id', 'title', 'amount', 'is_taxable', 'status'];

    protected $casts = ['amount' => 'float', 'is_taxable' => 'boolean', 'status' => 'integer'];

    public function loan()
    {
        return $this->belongsTo(StaffLoan::class, 'staff_loan_id');
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }
}
