<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * A logged change to the total of an approved or active budget (paxhitestbed
 * BudgetRevision). Applied at once by approve(), as in paxhitestbed.
 */
class BudgetRevision extends Model
{
    use Auditable, BelongsToBusiness;

    protected $fillable = [
        'budget_id', 'revision_number', 'previous_amount', 'new_amount', 'change_amount', 'change_type',
        'reason', 'justification', 'status', 'requested_by', 'approved_by', 'approved_at', 'rejected_reason',
    ];

    protected $casts = [
        'previous_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(\App\User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(\App\User::class, 'approved_by');
    }

    /** Apply the revision to its budget. */
    public function approve($approverId): bool
    {
        $this->status = 'approved';
        $this->approved_by = $approverId;
        $this->approved_at = now();
        $this->save();

        $budget = $this->budget;
        $budget->total_amount = $this->new_amount;
        $budget->remaining_amount = (float) $budget->total_amount - (float) $budget->spent_amount;
        $budget->updated_by = $approverId;
        $budget->save();

        return true;
    }

    public function reject($approverId, $reason): bool
    {
        $this->status = 'rejected';
        $this->approved_by = $approverId;
        $this->approved_at = now();
        $this->rejected_reason = $reason;
        $this->save();

        return true;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (BudgetRevision $r) {
            $last = static::where('budget_id', $r->budget_id)->max('revision_number');
            $r->revision_number = $last ? $last + 1 : 1;
            if (! $r->change_amount) {
                $r->change_amount = (float) $r->new_amount - (float) $r->previous_amount;
            }
            if (! $r->change_type) {
                $r->change_type = $r->change_amount >= 0 ? 'increase' : 'decrease';
            }
        });
    }

    public function getAuditDescription($event)
    {
        return "Budget revision {$event}: budget #{$this->budget_id} no. {$this->revision_number} "
            .number_format((float) $this->previous_amount, 2).' -> '.number_format((float) $this->new_amount, 2);
    }
}
