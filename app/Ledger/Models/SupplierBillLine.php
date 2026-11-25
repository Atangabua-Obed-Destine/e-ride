<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of a supplier bill, filed under an expense category (paxhitestbed
 * SupplierBillLine). account_id is decided on approval and kept: a category's
 * mapping can change later, the bill keeps the account it was posted to.
 */
class SupplierBillLine extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'supplier_bill_id', 'line_number', 'description', 'expense_category_id', 'account_id', 'amount'];

    protected $casts = ['amount' => 'float'];

    public function bill()
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id')->withTrashed();
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
