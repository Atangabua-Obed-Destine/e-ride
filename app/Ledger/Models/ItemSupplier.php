<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A supplier the books owe (paxhitestbed ItemSupplier, the fields the payables screens use). */
class ItemSupplier extends Model
{
    use SoftDeletes, Auditable, BelongsToBusiness;

    protected $table = 'item_suppliers';

    protected $fillable = ['business_id', 'title', 'contact_person', 'phone', 'email', 'address', 'tax_number', 'payment_terms_days', 'status', 'created_by'];

    protected $casts = ['payment_terms_days' => 'integer', 'status' => 'boolean'];

    public function bills()
    {
        return $this->hasMany(SupplierBill::class, 'supplier_id');
    }

    public function getAuditDescription($event)
    {
        return "Supplier {$event}: {$this->title}";
    }
}
