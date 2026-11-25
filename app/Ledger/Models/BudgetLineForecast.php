<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * The working behind a budgeted figure: "12 months at 85,000" (paxhitestbed
 * BudgetLineForecast, unit basis). paxhitestbed also forecast tuition from
 * student numbers; that is school-only and not kept.
 */
class BudgetLineForecast extends Model
{
    use Auditable, BelongsToBusiness;

    const BASIS_UNIT = 'unit';

    protected $fillable = [
        'budget_id', 'budget_line_id', 'quantity', 'rate', 'rate_basis', 'computed_amount', 'note',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'rate' => 'decimal:2',
        'computed_amount' => 'decimal:2',
    ];

    public function isUnitBasis(): bool
    {
        return $this->rate_basis === self::BASIS_UNIT;
    }

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function budgetLine()
    {
        return $this->belongsTo(BudgetLine::class);
    }

    public function getAuditDescription($event)
    {
        return "Budget working {$event}: budget #{$this->budget_id} line #{$this->budget_line_id} "
            .rtrim(rtrim(number_format((float) $this->quantity, 2, '.', ''), '0'), '.').' x '.number_format((float) $this->rate, 2);
    }
}
