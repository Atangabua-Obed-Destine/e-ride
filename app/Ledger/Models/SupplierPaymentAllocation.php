<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** How much of one payment went to one bill (paxhitestbed SupplierPaymentAllocation). */
class SupplierPaymentAllocation extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'supplier_payment_id', 'supplier_bill_id', 'amount'];

    protected $casts = ['amount' => 'float'];

    public function payment()
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function bill()
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }
}
