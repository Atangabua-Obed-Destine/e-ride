<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * paxhitestbed TaxSetting: a band of a tax group, or a standalone tax (no group), or a dependent
 * tax (a percentage of another tax's output, e.g. the Additional Council Tax = 10% of the income
 * tax). tax_type 1 percentage, 2 fixed; paid_by employee / employer / both. The column
 * "percentange" keeps paxhitestbed's spelling so the two systems read the same.
 */
class TaxSetting extends Model
{
    use Auditable, BelongsToBusiness;

    protected $table = 'tax_settings';

    protected $fillable = [
        'business_id', 'tax_group_id', 'is_dependent', 'depends_on_type', 'depends_on_id',
        'title', 'bracket_order', 'tax_type', 'is_shared', 'paid_by',
        'min_amount', 'max_amount', 'percentange', 'employer_percentage',
        'fixed_amount', 'employer_fixed_amount', 'max_no_taxable_amount',
        'status', 'effective_from', 'effective_to', 'liability_account_id',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_shared' => 'boolean',
        'is_dependent' => 'boolean',
        'min_amount' => 'float',
        'max_amount' => 'float',
        'percentange' => 'float',
        'employer_percentage' => 'float',
        'fixed_amount' => 'float',
        'employer_fixed_amount' => 'float',
        'max_no_taxable_amount' => 'float',
    ];

    public function taxGroup()
    {
        return $this->belongsTo(TaxGroup::class, 'tax_group_id');
    }

    public function liabilityAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'liability_account_id');
    }

    public function exemptions()
    {
        return $this->hasMany(StaffTaxExemption::class, 'tax_setting_id');
    }

    /** The tax (group or standalone) this dependent tax is worked out from. */
    public function getDependencySourceAttribute()
    {
        if (! $this->is_dependent || ! $this->depends_on_type || ! $this->depends_on_id) {
            return null;
        }

        return $this->depends_on_type === 'tax_group' ? TaxGroup::find($this->depends_on_id) : TaxSetting::find($this->depends_on_id);
    }

    public function getDependencyLabelAttribute()
    {
        $source = $this->dependency_source;
        if (! $source) {
            return null;
        }

        return $source->title.' ('.__($this->depends_on_type === 'tax_group' ? 'ledger_payroll.tax_group' : 'ledger_payroll.standalone').')';
    }

    /** Employee side, after the tax-free allowance (for a dependent tax the base is the source tax). */
    public function calculateEmployeeContribution($salary)
    {
        if ($this->paid_by === 'employer') {
            return 0;
        }
        if ($this->tax_type == 1) {
            return (max(0, $salary - ($this->max_no_taxable_amount ?? 0)) * $this->percentange) / 100;
        }

        return $this->fixed_amount;
    }

    public function calculateEmployerContribution($salary)
    {
        if ($this->paid_by === 'employee') {
            return 0;
        }
        if ($this->tax_type == 1) {
            return (max(0, $salary - ($this->max_no_taxable_amount ?? 0)) * $this->employer_percentage) / 100;
        }

        return $this->employer_fixed_amount;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeStandalone($query)
    {
        return $query->whereNull('tax_group_id');
    }

    public function scopeBase($query)
    {
        return $query->where(fn ($q) => $q->where('is_dependent', false)->orWhereNull('is_dependent'));
    }

    public function scopeDependent($query)
    {
        return $query->where('is_dependent', true);
    }

    public function scopeEffectiveOn($query, $date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();

        return $query->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('bracket_order', 'asc')->orderBy('min_amount', 'asc');
    }

    /** Standalone base taxes in force on a date. */
    public static function getEffectiveBrackets($date = null)
    {
        return static::active()->standalone()->base()->effectiveOn($date)->ordered()->get();
    }

    /** Standalone dependent taxes in force on a date. */
    public static function getEffectiveDependentBrackets($date = null)
    {
        return static::active()->standalone()->dependent()->effectiveOn($date)->ordered()->get();
    }

    public function isEffective($date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();

        return (is_null($this->effective_from) || $this->effective_from <= $date) && (is_null($this->effective_to) || $this->effective_to >= $date);
    }

    public function getAuditDescription($event)
    {
        return "Tax setting {$event}: {$this->title}";
    }
}
