<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One fixed asset of the business (paxhitestbed FixedAsset): its cost, how it
 * is depreciated, and where its figures stand. The figures change only through
 * DepreciationSchedule::post() / reverse() and DepreciationService::disposeAsset(),
 * each of which posts its entry through LedgerPoster.
 */
class FixedAsset extends Model
{
    use SoftDeletes, Auditable, BelongsToBusiness;

    protected $fillable = [
        'vehicle_id', // E-Ride: a bus of the fleet
        'asset_code',
        'name',
        'name_fr',
        'description',
        'category_id',
        'serial_number',
        'model',
        'manufacturer',
        'location',
        'vendor',
        'custodian_id',
        'acquisition_date',
        'acquisition_cost',
        'salvage_value',
        'useful_life_months',
        'depreciation_method',
        'declining_balance_rate',
        'depreciation_start_date',
        'accumulated_depreciation',
        'book_value',
        'last_depreciation_date',
        'status',
        'disposal_date',
        'disposal_value',
        'disposal_method',
        'disposal_reason',
        'disposal_journal_entry_id',
        'warranty_expiry',
        'insurance_value',
        'insurance_policy_number',
        'notes',
        'image_path',
        'purchase_journal_entry_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'depreciation_start_date' => 'date',
        'last_depreciation_date' => 'date',
        'disposal_date' => 'date',
        'warranty_expiry' => 'date',
        'acquisition_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'declining_balance_rate' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'book_value' => 'decimal:2',
        'disposal_value' => 'decimal:2',
        'insurance_value' => 'decimal:2',
        'useful_life_months' => 'integer',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_FULLY_DEPRECIATED = 'fully_depreciated';
    const STATUS_DISPOSED = 'disposed';
    const STATUS_UNDER_MAINTENANCE = 'under_maintenance';
    const STATUS_LOST = 'lost';

    /** How an asset leaves the books (the dispose dialog's choices). */
    const DISPOSAL_METHODS = ['sale', 'scrap', 'donation', 'theft', 'damage'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($asset) {
            if (empty($asset->asset_code)) {
                $asset->asset_code = static::generateAssetCode();
            }
            if ($asset->book_value === null || $asset->book_value === '') {
                $asset->book_value = $asset->acquisition_cost - ($asset->accumulated_depreciation ?? 0);
            }
        });
    }

    /** FA-YYYY-0001, numbered per business (the global scope limits the search to it). */
    public static function generateAssetCode(): string
    {
        $prefix = 'FA-'.date('Y').'-';
        $last = static::withTrashed()->where('asset_code', 'like', $prefix.'%')->orderBy('asset_code', 'desc')->value('asset_code');
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function category()
    {
        return $this->belongsTo(FixedAssetCategory::class, 'category_id')->withTrashed();
    }

    public function depreciationSchedules()
    {
        return $this->hasMany(DepreciationSchedule::class)->orderBy('depreciation_date');
    }

    /** E-Ride: the bus of the fleet this asset is, when it is one. */
    public function vehicle()
    {
        return $this->belongsTo(\Modules\BusManagement\Entities\BusVehicle::class, 'vehicle_id');
    }

    /** E-Ride: "registration · name" of the bus, or null. */
    public function getBusLabelAttribute(): ?string
    {
        $v = $this->vehicle_id ? $this->vehicle : null;

        return $v ? trim($v->registration.' · '.$v->name, ' ·') : null;
    }

    public function custodian()
    {
        return $this->belongsTo(\App\User::class, 'custodian_id');
    }

    public function disposalJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'disposal_journal_entry_id');
    }

    public function purchaseJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'purchase_journal_entry_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(\App\User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDepreciable($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('depreciation_method', '!=', 'none')
            ->where('book_value', '>', 0);
    }

    /** The screens read net_book_value; the column is book_value. */
    public function getNetBookValueAttribute()
    {
        return (float) $this->book_value;
    }

    public function getUsefulLifeYearsAttribute()
    {
        return $this->useful_life_months ? round($this->useful_life_months / 12, 1) + 0 : null;
    }

    /** Has anything about this asset reached the ledger (purchase, depreciation, disposal)? */
    public function isInLedger(): bool
    {
        return $this->purchase_journal_entry_id || $this->disposal_journal_entry_id
            || $this->depreciationSchedules()->where('status', DepreciationSchedule::STATUS_POSTED)->exists();
    }

    /**
     * The charge for one month. $month is the month being charged (for the
     * sum-of-the-years'-digits method, which charges less each year); by
     * default the next month due.
     */
    public function calculateMonthlyDepreciation($month = null)
    {
        if ($this->depreciation_method === 'none' || (float) $this->book_value <= (float) $this->salvage_value) {
            return 0;
        }

        switch ($this->depreciation_method) {
            case 'straight_line':
                $amount = $this->calculateStraightLineDepreciation();
                break;
            case 'declining_balance':
                $amount = $this->calculateDecliningBalanceDepreciation();
                break;
            case 'sum_of_years':
                $amount = $this->calculateSumOfYearsDepreciation($month);
                break;
            default:
                // units_of_production needs the units produced: not charged by the monthly run
                return 0;
        }

        return round(max(0, $amount), 2);
    }

    protected function calculateStraightLineDepreciation()
    {
        $monthly = ((float) $this->acquisition_cost - (float) $this->salvage_value) / max(1, $this->useful_life_months);

        // never below the salvage value: the last month takes what is left
        return min($monthly, (float) $this->book_value - (float) $this->salvage_value);
    }

    protected function calculateDecliningBalanceDepreciation()
    {
        // The rate is a yearly fraction (0.40) or, as typed on a form, a percentage (40).
        $rate = $this->declining_balance_rate !== null ? (float) $this->declining_balance_rate : (2 / max(1 / 12, $this->useful_life_months / 12));
        if ($rate > 1) {
            $rate = $rate / 100;
        }
        $monthly = (float) $this->book_value * $rate / 12;

        return min($monthly, (float) $this->book_value - (float) $this->salvage_value);
    }

    /**
     * Sum of the years' digits (paxhitestbed offered it and charged nothing):
     * year k of n is charged (n - k + 1) / (n(n+1)/2) of the depreciable amount,
     * a twelfth of it each month.
     */
    protected function calculateSumOfYearsDepreciation($month = null)
    {
        $years = max(1, (int) ceil($this->useful_life_months / 12));
        $month = $month ? Carbon::parse($month)->startOfMonth() : $this->nextDueMonth();
        $elapsed = max(0, Carbon::parse($this->depreciation_start_date)->startOfMonth()->diffInMonths($month, false));
        $k = min($years, intdiv($elapsed, 12) + 1);
        $annual = ((float) $this->acquisition_cost - (float) $this->salvage_value) * ($years - $k + 1) / ($years * ($years + 1) / 2);

        return min($annual / 12, (float) $this->book_value - (float) $this->salvage_value);
    }

    /** The first day of the next month to charge. */
    public function nextDueMonth(): Carbon
    {
        return $this->last_depreciation_date
            ? Carbon::parse($this->last_depreciation_date)->startOfMonth()->addMonthNoOverflow()
            : Carbon::parse($this->depreciation_start_date)->startOfMonth();
    }

    public function getRemainingLifeMonths()
    {
        $months = Carbon::parse($this->depreciation_start_date)->startOfMonth()->diffInMonths(now()->startOfMonth());

        return max(0, $this->useful_life_months - $months);
    }

    public function isFullyDepreciated()
    {
        return (float) $this->book_value <= (float) $this->salvage_value || $this->status === self::STATUS_FULLY_DEPRECIATED;
    }

    /**
     * Is a charge due for the month of $date? Once per calendar month, the
     * month after the last one charged (paxhitestbed's fix: addMonth() on
     * 31 January landed on 3 March and skipped February).
     */
    public function isDepreciationDue($date = null)
    {
        $date = Carbon::parse($date ?? now());

        if ($this->isFullyDepreciated() || $this->status !== self::STATUS_ACTIVE || $this->depreciation_method === 'none') {
            return false;
        }

        if ($this->last_depreciation_date === null) {
            return Carbon::parse($this->depreciation_start_date)->lte($date);
        }

        return $this->nextDueMonth()->lte($date);
    }

    /** A posted month's charge on the asset's figures. */
    public function recordDepreciation($amount, $depreciationDate, $journalEntryId = null)
    {
        $this->accumulated_depreciation = round((float) $this->accumulated_depreciation + (float) $amount, 2);
        $this->book_value = round((float) $this->acquisition_cost - (float) $this->accumulated_depreciation, 2);
        $this->last_depreciation_date = $depreciationDate;

        if ((float) $this->book_value <= (float) $this->salvage_value) {
            $this->status = self::STATUS_FULLY_DEPRECIATED;
        }

        $this->save();
    }

    public function dispose($disposalDate, $disposalValue, $reason = null, $journalEntryId = null, $method = null)
    {
        $this->status = self::STATUS_DISPOSED;
        $this->disposal_date = $disposalDate;
        $this->disposal_value = $disposalValue;
        $this->disposal_method = $method;
        $this->disposal_reason = $reason;
        $this->disposal_journal_entry_id = $journalEntryId;
        $this->save();
    }

    public static function statusLabel(?string $status): string
    {
        return $status ? __('ledger_assets.'.$status) : '';
    }

    public function getStatusLabel()
    {
        return self::statusLabel($this->status);
    }

    /** The ap-chip class for a status (state in words and colour). */
    public static function statusChip(?string $status): string
    {
        return [
            self::STATUS_ACTIVE => 'open',
            self::STATUS_FULLY_DEPRECIATED => 'posted',
            self::STATUS_DISPOSED => 'void',
            self::STATUS_UNDER_MAINTENANCE => 'part_paid',
            self::STATUS_LOST => 'unpaid',
        ][$status] ?? 'draft';
    }

    public function getAuditDescription($event)
    {
        return "Fixed Asset {$event}: [".($this->asset_code ?? 'N/A').'] '.($this->name ?? 'N/A')
            .', Status: '.($this->status ?? 'N/A').', Book Value: '.number_format((float) ($this->book_value ?? 0), 2);
    }
}
