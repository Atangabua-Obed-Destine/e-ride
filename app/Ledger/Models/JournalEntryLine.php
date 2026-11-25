<?php

namespace App\Ledger\Models;

use Illuminate\Database\Eloquent\Model;
use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;

class JournalEntryLine extends Model
{
    use Auditable, BelongsToBusiness;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'line_number',
        'description',
        'debit',
        'credit',
        'contact_id',
        // E-Ride: the bus cost centres (trip, bus, route, channel, counter agent)
        'departure_id',
        'vehicle_id',
        'destination_id',
        'channel',
        'agent_id',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'line_number' => 'integer',
    ];

    /**
     * Get the journal entry
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the account
     */
    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * Get the net amount (debit - credit)
     */
    public function getNetAmountAttribute()
    {
        return $this->debit - $this->credit;
    }

    /**
     * Check if this is a debit line
     */
    public function isDebit()
    {
        return $this->debit > 0;
    }

    /**
     * Check if this is a credit line
     */
    public function isCredit()
    {
        return $this->credit > 0;
    }
    
    /**
     * Custom audit description for better readability
     */
    public function getAuditDescription($event)
    {
        // Load relationships if not loaded
        if (!$this->relationLoaded('account')) {
            try {
                $this->load('account');
            } catch (\Exception $e) {
                // Relationship loading failed
            }
        }
        
        $debit = $this->debit ?? 0;
        $credit = $this->credit ?? 0;
        $type = $debit > 0 ? 'Debit' : 'Credit';
        $amount = $debit > 0 ? $debit : $credit;
        
        // Get account name
        $accountName = 'Unknown Account';
        if ($this->account) {
            $accountName = $this->account->account_name ?? $this->account->name ?? 'Account #' . $this->account_id;
        } elseif ($this->account_id) {
            // Fallback: try direct query
            $account = \App\Ledger\Models\ChartOfAccount::find($this->account_id);
            if ($account) {
                $accountName = $account->account_name ?? $account->name ?? 'Account #' . $this->account_id;
            } else {
                $accountName = 'Account #' . $this->account_id;
            }
        }
        
        return "Journal line {$event}: {$accountName} - {$type} " . number_format($amount, 2);
    }
}
