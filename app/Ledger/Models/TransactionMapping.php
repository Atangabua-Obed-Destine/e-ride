<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * A POS document and the journal entry it posted (Transaction mappings ›
 * View transactions). One row per document; when the document changes, its
 * entry is reversed and a new one posted, and the row points at the new one.
 *
 * status: posted (in the ledger), none (nothing to post, e.g. a draft or a
 * zero amount), failed (could not post: the reason is in `error`; Ledger
 * health lists these and the sync retries them).
 */
class TransactionMapping extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'transaction_type', 'transaction_id', 'debit_account_id', 'credit_account_id',
        'amount', 'transaction_date', 'description', 'journal_entry_id', 'fingerprint',
        'mapped_at', 'mapped_by', 'status', 'error',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'mapped_at' => 'datetime',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function debitAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    public function creditAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }

    public function mappedBy()
    {
        return $this->belongsTo(\App\User::class, 'mapped_by');
    }
}
