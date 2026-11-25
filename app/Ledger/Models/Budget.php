<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * paxhitestbed's Budget: an envelope of money for a period. The annual
 * Income & Expenditure sheet is a budget with is_institutional = true;
 * departmental and project budgets are delegated out of it (parent_id).
 *
 * PosInnova: the "department" is a business location (location_id), and what
 * a budget has spent is read from the posted ledger (BudgetActualsService),
 * never typed and never patched incrementally.
 */
class Budget extends Model
{
    use Auditable, BelongsToBusiness;

    const STATUSES = ['draft', 'pending_approval', 'approved', 'active', 'closed', 'cancelled'];

    const TYPES = ['annual', 'departmental', 'project'];

    protected $fillable = [
        'title', 'budget_code', 'type', 'location_id', 'fiscal_year', 'start_date', 'end_date',
        'total_amount', 'opening_balance', 'is_institutional', 'parent_id',
        'allocated_amount', 'spent_amount', 'remaining_amount', 'status',
        'description', 'note', 'created_by', 'updated_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'is_institutional' => 'boolean',
        'total_amount' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'allocated_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    /** The annual budget this one draws from (null on an annual sheet). */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function location()
    {
        return $this->belongsTo(\App\BusinessLocation::class, 'location_id');
    }

    public function allocations()
    {
        return $this->hasMany(BudgetAllocation::class, 'budget_id');
    }

    public function revisions()
    {
        return $this->hasMany(BudgetRevision::class, 'budget_id')->orderBy('revision_number');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(\App\User::class, 'approved_by');
    }

    public function getUtilizationPercentageAttribute()
    {
        if ((float) $this->total_amount == 0) {
            return 0;
        }

        return round(((float) $this->spent_amount / (float) $this->total_amount) * 100, 2);
    }

    /** Bootstrap 3 progress-bar colour for the utilisation. */
    public function getUtilizationColorAttribute()
    {
        $p = $this->utilization_percentage;
        if ($p < 50) {
            return 'success';
        }
        if ($p < 75) {
            return 'info';
        }
        if ($p < 90) {
            return 'warning';
        }

        return 'danger';
    }

    /** The status in words (every chip says it in words, never colour alone). */
    public static function statusLabel(?string $status): string
    {
        return $status ? __('ledger_budget.status_'.$status) : '';
    }

    /** The ap-chip class for a status. */
    public static function statusChip(?string $status): string
    {
        return [
            'draft' => 'draft', 'pending_approval' => 'part_paid', 'approved' => 'posted',
            'active' => 'open', 'closed' => 'closed', 'cancelled' => 'void',
        ][$status] ?? 'draft';
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function isOverBudget(): bool
    {
        return (float) $this->spent_amount > (float) $this->total_amount;
    }

    /** Re-add up the allocations (single source of truth, never incremented). */
    public function calculateAllocatedAmount(): void
    {
        $this->allocated_amount = (float) $this->allocations()->sum('allocated_amount');
        $this->save();
    }

    /** The next free BDG-{year}-{0001} of this business. */
    public static function nextCode(?string $year): string
    {
        $year = $year ?: date('Y');
        $n = static::where('budget_code', 'like', 'BDG-'.$year.'-%')->count() + 1;
        do {
            $code = 'BDG-'.$year.'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            $n++;
        } while (static::where('budget_code', $code)->exists());

        return $code;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Budget $budget) {
            if (empty($budget->budget_code)) {
                $budget->budget_code = static::nextCode($budget->fiscal_year);
            }
            $budget->remaining_amount = (float) $budget->total_amount - (float) $budget->spent_amount;
        });
    }

    public function getAuditDescription($event)
    {
        return "Budget {$event}: {$this->budget_code} {$this->title} ({$this->status})";
    }
}
