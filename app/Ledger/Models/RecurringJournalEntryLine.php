<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** One line of a recurring entry's template (paxhitestbed App\Models\RecurringJournalEntryLine). */
class RecurringJournalEntryLine extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'recurring_journal_entry_id', 'account_id', 'debit', 'credit', 'description', 'line_number'];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'line_number' => 'integer',
    ];

    public function recurringJournalEntry()
    {
        return $this->belongsTo(RecurringJournalEntry::class);
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function isDebit()
    {
        return $this->debit > 0;
    }

    public function isCredit()
    {
        return $this->credit > 0;
    }

    public function getAmount()
    {
        return $this->debit > 0 ? $this->debit : $this->credit;
    }
}
