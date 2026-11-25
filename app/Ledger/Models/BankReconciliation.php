<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One bank (or cash, or mobile money) statement matched against the books
 * (paxhitestbed App\Models\BankReconciliation). Every rule is in
 * App\Ledger\Services\BankReconciliationService.
 */
class BankReconciliation extends Model
{
    use SoftDeletes, Auditable, BelongsToBusiness;

    const STATUS_DRAFT = 'draft';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'business_id', 'chart_of_account_id', 'statement_date', 'reconciliation_date', 'statement_reference',
        'statement_opening_balance', 'statement_closing_balance', 'book_balance', 'adjusted_book_balance',
        'outstanding_deposits', 'outstanding_checks', 'bank_charges', 'bank_interest', 'other_adjustments',
        'difference', 'status', 'notes', 'reconciled_by', 'reconciled_at', 'approved_by', 'approved_at',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'reconciliation_date' => 'date',
        'statement_opening_balance' => 'decimal:2',
        'statement_closing_balance' => 'decimal:2',
        'book_balance' => 'decimal:2',
        'adjusted_book_balance' => 'decimal:2',
        'outstanding_deposits' => 'decimal:2',
        'outstanding_checks' => 'decimal:2',
        'bank_charges' => 'decimal:2',
        'bank_interest' => 'decimal:2',
        'other_adjustments' => 'decimal:2',
        'difference' => 'decimal:2',
        'reconciled_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function items()
    {
        return $this->hasMany(BankReconciliationItem::class);
    }

    public function reconciledBy()
    {
        return $this->belongsTo(\App\User::class, 'reconciled_by');
    }

    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_APPROVED]);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS]);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS], true);
    }

    public function isBalanced(): bool
    {
        return abs((float) $this->difference) < 0.01;
    }

    public function getAuditDescription($event)
    {
        $account = optional($this->chartOfAccount)->account_code ?? '#'.$this->chart_of_account_id;
        $date = $this->statement_date ? $this->statement_date->format('Y-m-d') : 'N/A';

        return "Bank Reconciliation {$event}: {$account} for {$date}, Status: {$this->status}";
    }
}
