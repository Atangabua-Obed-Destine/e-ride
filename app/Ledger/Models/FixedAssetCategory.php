<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A kind of fixed asset and the accounts its figures post to (paxhitestbed
 * FixedAssetCategory). One business's categories (BelongsToBusiness).
 *
 *   asset_account_id                     2x   cost of the asset
 *   accumulated_depreciation_account_id  28x  amortissements (credit balance)
 *   depreciation_account_id              68x  dotation, the monthly charge
 *   disposal_account_id                  gain / loss on disposal; empty = 82x for a gain, 81x for a loss
 *
 * (paxhitestbed's category screens and controller used three different names
 * for the same two accounts: depreciation_account_id was labelled the
 * accumulated account on the form and read as the charge by the posting.
 * Here each column has one meaning, the posting's.)
 */
class FixedAssetCategory extends Model
{
    use SoftDeletes, Auditable, BelongsToBusiness;

    protected $fillable = [
        'code',
        'name',
        'name_fr',
        'description',
        'depreciation_method',
        'useful_life_years',
        'salvage_value_percent',
        'asset_account_id',
        'depreciation_account_id',
        'accumulated_depreciation_account_id',
        'disposal_account_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'useful_life_years' => 'integer',
        'salvage_value_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    const METHOD_STRAIGHT_LINE = 'straight_line';
    const METHOD_DECLINING_BALANCE = 'declining_balance';
    const METHOD_SUM_OF_YEARS = 'sum_of_years';
    const METHOD_UNITS_OF_PRODUCTION = 'units_of_production';
    const METHOD_NONE = 'none';

    /** The methods the screens offer, in paxhitestbed's order. */
    const METHODS = [self::METHOD_STRAIGHT_LINE, self::METHOD_DECLINING_BALANCE, self::METHOD_SUM_OF_YEARS, self::METHOD_UNITS_OF_PRODUCTION];

    public function assets()
    {
        return $this->hasMany(FixedAsset::class, 'category_id');
    }

    public function assetAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'asset_account_id');
    }

    public function depreciationAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'depreciation_account_id');
    }

    public function accumulatedDepreciationAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'accumulated_depreciation_account_id');
    }

    public function disposalAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'disposal_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function methodLabel(?string $method): string
    {
        return $method ? __('ledger_assets.'.$method) : '';
    }

    public function getMethodLabel(): string
    {
        return self::methodLabel($this->depreciation_method);
    }

    public function getUsefulLifeMonthsAttribute()
    {
        return $this->useful_life_years * 12;
    }

    public function hasValidAccountMappings(): bool
    {
        if ($this->depreciation_method === self::METHOD_NONE) {
            return $this->asset_account_id !== null;
        }

        return $this->asset_account_id !== null
            && $this->depreciation_account_id !== null
            && $this->accumulated_depreciation_account_id !== null;
    }

    public function getAuditDescription($event)
    {
        return "Fixed Asset Category {$event}: ".($this->name ?? 'N/A').', Method: '.($this->depreciation_method ?? 'N/A');
    }
}
