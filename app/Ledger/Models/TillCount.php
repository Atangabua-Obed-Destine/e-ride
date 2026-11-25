<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** One count of a counter agent's cash (E-Ride, bus books; see App\Ledger\ERide\Tills). */
class TillCount extends Model
{
    use Auditable, BelongsToBusiness;

    protected $fillable = [
        'agent_id', 'count_date', 'expected', 'counted', 'difference', 'to_account_id', 'note',
        'journal_entry_id', 'status', 'void_reason', 'voided_by', 'voided_at', 'counted_by',
    ];

    protected $casts = [
        'count_date' => 'date',
        'expected' => 'decimal:2',
        'counted' => 'decimal:2',
        'difference' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'to_account_id');
    }

    public function getAuditDescription(string $event): string
    {
        return 'Till count '.$event.': '.$this->count_date?->format('d/m/Y').' counted '.(float) $this->counted;
    }
}
