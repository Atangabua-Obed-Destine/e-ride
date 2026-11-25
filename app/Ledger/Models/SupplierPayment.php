<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * Money paid to a supplier, allocated to one or more of its bills (paxhitestbed
 * SupplierPayment). A voided payment keeps its row and its allocations; it simply
 * stops counting, so every sum of what has been paid uses scopeLive().
 */
class SupplierPayment extends Model
{
    use Auditable, BelongsToBusiness;

    protected $fillable = [
        'business_id', 'supplier_id', 'number', 'payment_date', 'amount', 'payment_method',
        'cash_account_id', 'payment_account_id', 'payment_account_transaction_id',
        'reference', 'note', 'journal_entry_id',
        'void_journal_entry_id', 'voided_by', 'voided_at', 'void_reason', 'created_by',
    ];

    protected $casts = ['payment_date' => 'date', 'amount' => 'float', 'voided_at' => 'datetime'];

    public function supplier()
    {
        return $this->belongsTo(ItemSupplier::class, 'supplier_id')->withTrashed();
    }

    public function allocations()
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'supplier_payment_id');
    }

    public function cashAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'cash_account_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function voidJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'void_journal_entry_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function voider()
    {
        return $this->belongsTo(\App\User::class, 'voided_by');
    }

    public function isVoid(): bool
    {
        return $this->voided_at !== null;
    }

    public function scopeLive($query)
    {
        return $query->whereNull('voided_at');
    }

    public function getAuditDescription($event)
    {
        return "Supplier payment {$event}: {$this->number}";
    }
}
