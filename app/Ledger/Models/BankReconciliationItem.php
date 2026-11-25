<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * One movement of a reconciliation: a posted ledger line on the bank account
 * (deposit in transit / outstanding payment until it is ticked as on the
 * statement), or a charge / interest posted from the reconciliation
 * (paxhitestbed App\Models\BankReconciliationItem).
 */
class BankReconciliationItem extends Model
{
    use BelongsToBusiness;

    const TYPE_OUTSTANDING_CHECK = 'outstanding_check';
    const TYPE_DEPOSIT_IN_TRANSIT = 'deposit_in_transit';
    const TYPE_BANK_CHARGE = 'bank_charge';
    const TYPE_BANK_INTEREST = 'bank_interest';

    const EFFECT_ADD = 'add';
    const EFFECT_SUBTRACT = 'subtract';

    protected $fillable = [
        'business_id', 'bank_reconciliation_id', 'item_type', 'account_id', 'reference_number', 'transaction_date',
        'description', 'amount', 'effect', 'is_cleared', 'cleared_date', 'journal_entry_id', 'journal_entry_line_id', 'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'cleared_date' => 'date',
        'amount' => 'decimal:2',
        'is_cleared' => 'boolean',
    ];

    public function bankReconciliation()
    {
        return $this->belongsTo(BankReconciliation::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function isAdjustment(): bool
    {
        return in_array($this->item_type, [self::TYPE_BANK_CHARGE, self::TYPE_BANK_INTEREST], true);
    }
}
