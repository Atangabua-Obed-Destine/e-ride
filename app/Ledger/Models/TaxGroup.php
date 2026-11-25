<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * paxhitestbed TaxGroup, as it is: a group of bands (TaxSetting rows) charged by STEP LOOKUP, the
 * one band whose floor is the highest at or below the salary, applied once. The income tax (PIT)
 * reproduces the DGI scale with five such bands (CameroonTaxTable). Effective-dated.
 */
class TaxGroup extends Model
{
    use Auditable, BelongsToBusiness;

    protected $table = 'tax_groups';

    protected $fillable = [
        'business_id', 'title', 'code', 'description', 'is_progressive', 'status',
        'effective_from', 'effective_to', 'display_order', 'liability_account_id',
    ];

    protected $casts = [
        'is_progressive' => 'boolean',
        'status' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function brackets()
    {
        return $this->hasMany(TaxSetting::class, 'tax_group_id')
            ->orderBy('bracket_order', 'asc')
            ->orderBy('min_amount', 'asc');
    }

    public function activeBrackets()
    {
        return $this->brackets()->where('status', 1);
    }

    public function liabilityAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'liability_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeEffectiveOn($query, $date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();

        return $query->where(function ($q) use ($date) {
            $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order', 'asc')->orderBy('title', 'asc');
    }

    /** Active and effective groups, with only the bands in force on that date. */
    public static function getEffectiveGroups($date = null)
    {
        $day = $date ? Carbon::parse($date) : Carbon::today();

        return static::active()
            ->effectiveOn($day)
            ->ordered()
            ->with(['brackets' => function ($query) use ($day) {
                $query->where('status', 1)
                    ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $day))
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $day))
                    ->orderBy('bracket_order', 'asc')
                    ->orderBy('min_amount', 'asc');
            }])
            ->get();
    }

    /**
     * The tax this group charges on a salary (step lookup), honouring a staff member's exemptions
     * (keyed by tax_setting_id): a custom rate replaces the band's, no custom value means nothing.
     *
     * @return array ['amount' => float, 'breakdown' => array]
     */
    public function calculateTax($salary, $exemptions = null)
    {
        $bracket = $this->applicableBracket($salary);

        if ($bracket === null) {
            return ['group_id' => $this->id, 'group_title' => $this->title, 'is_progressive' => $this->is_progressive, 'amount' => 0, 'breakdown' => []];
        }

        $isExempt = $exemptions && $exemptions->has($bracket->id);
        $exemption = $isExempt ? $exemptions->get($bracket->id) : null;
        if ($exemption && $exemption->isExpired()) {
            $isExempt = false;
            $exemption = null;
        }

        $taxableAmount = max(0, $salary - $bracket->max_no_taxable_amount);
        $bracketTax = 0;
        $taxInfo = [
            'bracket_id' => $bracket->id,
            'title' => $bracket->title,
            'range' => $bracket->min_amount.' - '.$bracket->max_amount,
            'taxable_amount' => $taxableAmount,
            'rate' => null,
            'tax_amount' => 0,
            'is_exempt' => $isExempt,
            'custom_rate' => false,
        ];

        if ($isExempt) {
            if ($exemption->custom_percentage && $bracket->tax_type == 1) {
                $bracketTax = ($taxableAmount / 100) * $exemption->custom_percentage;
                $taxInfo['rate'] = $exemption->custom_percentage.'% (custom)';
                $taxInfo['custom_rate'] = true;
            } elseif ($exemption->custom_fixed_amount && $bracket->tax_type == 2) {
                $bracketTax = $exemption->custom_fixed_amount;
                $taxInfo['rate'] = $exemption->custom_fixed_amount.' (custom fixed)';
                $taxInfo['custom_rate'] = true;
            }
        } elseif ($bracket->tax_type == 2) {
            $bracketTax = $bracket->fixed_amount;
            $taxInfo['rate'] = $bracket->fixed_amount.' (fixed)';
        } else {
            $bracketTax = ($taxableAmount / 100) * $bracket->percentange;
            $taxInfo['rate'] = $bracket->percentange.'%';
        }

        $taxInfo['tax_amount'] = $bracketTax;

        return ['group_id' => $this->id, 'group_title' => $this->title, 'is_progressive' => $this->is_progressive,
            'amount' => (float) $bracketTax, 'breakdown' => [$taxInfo]];
    }

    /**
     * The salary ranges this group's bands do not cover: holes between bands, and everything
     * above the last band's ceiling (both are charged the band below, which the figures hide).
     */
    public function coverageGaps(): array
    {
        $bands = $this->bracketsInForce()->sortBy('min_amount')->values();
        if ($bands->isEmpty()) {
            return ['gaps' => [], 'open_top' => null];
        }

        $gaps = [];
        foreach ($bands as $i => $band) {
            $next = $bands->get($i + 1);
            if (! $next) {
                break;
            }
            if ($next->min_amount > $band->max_amount + 1) {
                $gaps[] = ['from' => (float) $band->max_amount + 1, 'to' => (float) $next->min_amount - 1, 'charged_as' => $band->title];
            }
        }
        $top = $bands->last();

        return ['gaps' => $gaps, 'open_top' => $top->max_amount > 0 ? ['from' => (float) $top->max_amount + 1, 'band' => $top->title] : null];
    }

    /** Does this salary fall in one of the group's uncovered ranges? */
    public function gapFor($salary): ?array
    {
        $coverage = $this->coverageGaps();
        foreach ($coverage['gaps'] as $gap) {
            if ($salary >= $gap['from'] && $salary <= $gap['to']) {
                return $gap;
            }
        }
        if ($coverage['open_top'] && $salary >= $coverage['open_top']['from']) {
            return ['from' => $coverage['open_top']['from'], 'to' => null, 'charged_as' => $coverage['open_top']['band']];
        }

        return null;
    }

    /** The active bands, honouring the date filter getEffectiveGroups() eager-loaded. */
    protected function bracketsInForce()
    {
        return $this->relationLoaded('brackets')
            ? $this->brackets->where('status', 1)
            : $this->brackets()->where('status', 1)->get();
    }

    /** The single band a salary falls in (highest active floor at or below it), shared by both sides. */
    public function applicableBracket($salary)
    {
        $bracket = null;
        foreach ($this->bracketsInForce() as $candidate) {
            if ($salary >= $candidate->min_amount && ($bracket === null || $candidate->min_amount >= $bracket->min_amount)) {
                $bracket = $candidate;
            }
        }

        return $bracket;
    }

    public function getAuditDescription($event)
    {
        return "Tax group {$event}: {$this->title} ({$this->code})";
    }
}
