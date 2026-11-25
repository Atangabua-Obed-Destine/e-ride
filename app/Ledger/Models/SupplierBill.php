<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * What a supplier invoiced (paxhitestbed SupplierBill).
 *
 * Draft bills post nothing and can be edited. Approving one posts the cost and
 * the debt; after that it is corrected only by voiding it, which reverses the
 * entry and keeps the row. What is still owed is never stored: it is the total
 * less the live allocations (App\Ledger\Services\SupplierPayables).
 */
class SupplierBill extends Model
{
    use Auditable, BelongsToBusiness;

    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const VOID = 'void';

    public const KIND_BILL = 'bill';

    public const KIND_OPENING = 'opening_balance';

    protected $fillable = [
        'business_id', 'supplier_id', 'kind', 'number', 'supplier_reference', 'bill_date', 'due_date',
        'description', 'vat_amount', 'total', 'status', 'payable_account_id', 'attach',
        'journal_entry_id', 'approved_by', 'approved_at',
        'void_journal_entry_id', 'voided_by', 'voided_at', 'void_reason',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'total' => 'float',
        'vat_amount' => 'float',
        'approved_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(ItemSupplier::class, 'supplier_id')->withTrashed();
    }

    public function lines()
    {
        return $this->hasMany(SupplierBillLine::class, 'supplier_bill_id')->orderBy('line_number');
    }

    public function allocations()
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'supplier_bill_id');
    }

    public function payableAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'payable_account_id');
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

    public function approver()
    {
        return $this->belongsTo(\App\User::class, 'approved_by');
    }

    public function voider()
    {
        return $this->belongsTo(\App\User::class, 'voided_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    public function isVoid(): bool
    {
        return $this->status === self::VOID;
    }

    public function isOpeningBalance(): bool
    {
        return $this->kind === self::KIND_OPENING;
    }

    public function getAuditDescription($event)
    {
        return "Supplier bill {$event}: {$this->number}";
    }
}
