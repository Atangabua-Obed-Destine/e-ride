<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * The closing of one fiscal year (clôture de l'exercice), paxhitestbed
 * App\Models\YearEndClosing: draft → in progress (checklist) → closing entries
 * generated, pending approval → completed (the year is closed) → reversed.
 * Every rule is in App\Ledger\Services\YearEndClosingService.
 */
class YearEndClosing extends Model
{
    use Auditable, BelongsToBusiness;

    const STATUS_DRAFT = 'draft';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_COMPLETED = 'completed';
    const STATUS_REVERSED = 'reversed';

    /**
     * The items the accountant ticks (paxhitestbed DEFAULT_CHECKLIST), plus
     * the stock count: PosInnova closes the stock of goods at year end.
     */
    const CHECKLIST_ITEMS = [
        'all_transactions_posted',
        'bank_reconciliations_complete',
        'stock_counted',
        'depreciation_posted',
        'accruals_posted',
        'adjusting_entries_complete',
        'trial_balance_reviewed',
        'financial_statements_prepared',
        'audit_adjustments_entered',
    ];

    protected $fillable = [
        'business_id', 'fiscal_year_id', 'closing_date', 'closing_reference', 'total_revenue', 'total_expenses', 'net_income',
        'retained_earnings_account_id', 'income_summary_account_id', 'closing_journal_entry_id', 'stock_journal_entry_id',
        'opening_stock_value', 'closing_stock_value', 'stock_valuation', 'stock_entered_by', 'stock_entered_at',
        'status', 'checklist', 'started_at', 'completed_at', 'notes', 'created_by', 'updated_by', 'approved_by', 'approved_at',
        'reversed_by', 'reversed_at',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'total_revenue' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'net_income' => 'decimal:2',
        'opening_stock_value' => 'decimal:2',
        'closing_stock_value' => 'decimal:2',
        'stock_valuation' => 'decimal:2',
        'checklist' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'reversed_at' => 'datetime',
        'stock_entered_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($closing) {
            if (empty($closing->checklist)) {
                $closing->checklist = self::blankChecklist();
            }
        });
    }

    public static function blankChecklist(): array
    {
        $out = [];
        foreach (self::CHECKLIST_ITEMS as $key) {
            $out[$key] = ['completed' => false, 'completed_by' => null, 'completed_at' => null];
        }

        return $out;
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function retainedEarningsAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'retained_earnings_account_id');
    }

    public function incomeSummaryAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'income_summary_account_id');
    }

    public function closingJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'closing_journal_entry_id');
    }

    public function stockJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'stock_journal_entry_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(\App\User::class, 'approved_by');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS, self::STATUS_PENDING_APPROVAL]);
    }

    public function getStatusLabel(): string
    {
        return __('ledger_periodend.st_'.$this->status);
    }

    /** ap-chip class of the status. */
    public function getStatusChip(): string
    {
        return [
            self::STATUS_DRAFT => 'draft',
            self::STATUS_IN_PROGRESS => 'part_paid',
            self::STATUS_PENDING_APPROVAL => 'overdue',
            self::STATUS_COMPLETED => 'closed',
            self::STATUS_REVERSED => 'void',
        ][$this->status] ?? 'draft';
    }

    /** The checklist with every item present, in order (older rows may lack the newer items). */
    public function checklistItems(): array
    {
        $stored = is_array($this->checklist) ? $this->checklist : [];
        $out = [];
        foreach (self::CHECKLIST_ITEMS as $key) {
            $item = $stored[$key] ?? null;
            if (! is_array($item)) {
                $item = ['completed' => (bool) $item, 'completed_by' => null, 'completed_at' => null];
            }
            $out[$key] = $item + ['completed' => false, 'completed_by' => null, 'completed_at' => null];
        }

        return $out;
    }

    public function getChecklistCompletedCount(): int
    {
        return count(array_filter($this->checklistItems(), fn ($i) => ! empty($i['completed'])));
    }

    public function getChecklistCompletionPercentage(): int
    {
        return (int) round($this->getChecklistCompletedCount() / count(self::CHECKLIST_ITEMS) * 100);
    }

    public function isChecklistComplete(): bool
    {
        return $this->getChecklistCompletedCount() === count(self::CHECKLIST_ITEMS);
    }

    public function getAuditDescription($event)
    {
        $year = optional($this->fiscalYear)->name ?? 'N/A';

        return "Year-End Closing {$event}: [{$this->closing_reference}] FY: {$year}, Net Income: ".number_format((float) $this->net_income, 2).", Status: {$this->status}";
    }
}
