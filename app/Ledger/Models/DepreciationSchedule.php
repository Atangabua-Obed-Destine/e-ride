<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use App\Ledger\Services\LedgerPoster;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * One month's depreciation of one asset (paxhitestbed DepreciationSchedule):
 * pending (calculated), posted (Dr 68x / Cr 28x in the ledger) or reversed.
 * One row per asset per month; a reversed month is charged again on its row.
 *
 * Posting goes through LedgerPoster, which numbers the entry, totals it from
 * its lines, numbers the lines and refuses a closed month. (paxhitestbed's
 * first version created journal entries with columns they do not have,
 * source_type and status, and without number, totals or line numbers, so no
 * depreciation could ever post.)
 */
class DepreciationSchedule extends Model
{
    use Auditable, BelongsToBusiness;

    protected $fillable = [
        'fixed_asset_id',
        'depreciation_date',
        'period_number',
        'depreciation_amount',
        'accumulated_depreciation_before',
        'accumulated_depreciation_after',
        'book_value_before',
        'book_value_after',
        'fiscal_year_id',
        'accounting_period_id',
        'journal_entry_id',
        'status',
        'posted_at',
        'posted_by',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'depreciation_date' => 'date',
        'depreciation_amount' => 'decimal:2',
        'accumulated_depreciation_before' => 'decimal:2',
        'accumulated_depreciation_after' => 'decimal:2',
        'book_value_before' => 'decimal:2',
        'book_value_after' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_POSTED = 'posted';
    const STATUS_REVERSED = 'reversed';

    protected static function booted()
    {
        static::saving(function (self $s) {
            if (! $s->period_number && $s->depreciation_date) {
                $s->period_number = (int) Carbon::parse($s->depreciation_date)->format('n');
            }
        });
    }

    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class)->withTrashed();
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function accountingPeriod()
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function postedBy()
    {
        return $this->belongsTo(\App\User::class, 'posted_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function scopeForPeriod($query, $periodId)
    {
        return $query->where('accounting_period_id', $periodId);
    }

    public function scopeForFiscalYear($query, $fiscalYearId)
    {
        return $query->where('fiscal_year_id', $fiscalYearId);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('depreciation_date', [$startDate, $endDate]);
    }

    /** Post the month's charge: Dr depreciation charge (68x), Cr accumulated depreciation (28x). */
    public function post($userId = null)
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \RuntimeException(__('ledger_assets.only_pending_can_be_posted'));
        }
        $asset = $this->fixedAsset;
        if (! $asset) {
            throw new \RuntimeException(__('ledger_assets.asset_not_found'));
        }
        $category = $asset->category;
        if (! $category || ! $category->depreciation_account_id || ! $category->accumulated_depreciation_account_id) {
            throw new \RuntimeException(__('ledger_assets.depreciation_accounts_not_configured', ['category' => $category->name ?? '']));
        }

        $userId = $userId ?? auth()->id();
        $date = Carbon::parse($this->depreciation_date);
        $amount = round((float) $this->depreciation_amount, 2);
        $month = $date->translatedFormat('F Y');

        $entry = LedgerPoster::post([
            'entry_date' => $date->toDateString(),
            'journal_type' => 'adjustment',
            'description' => __('ledger_assets.Depreciation').' — '.$asset->name.' — '.$month,
            'reference_number' => 'DEP-'.$asset->asset_code.'-'.$date->format('Ym'),
            'reference_type' => 'depreciation',
            'reference_id' => $this->id,
            'is_system_generated' => true,
        ], [
            ['account_id' => $category->depreciation_account_id, 'debit' => $amount, 'credit' => 0,
                'description' => __('ledger_assets.Depreciation').' — '.$asset->name] + array_filter(['vehicle_id' => $asset->vehicle_id]), // E-Ride: the bus
            ['account_id' => $category->accumulated_depreciation_account_id, 'debit' => 0, 'credit' => $amount,
                'description' => __('ledger_assets.Accumulated depreciation').' — '.$asset->name] + array_filter(['vehicle_id' => $asset->vehicle_id]),
        ], $userId);

        $asset->recordDepreciation($amount, $date->toDateString(), $entry->id);

        $this->forceFill([
            'status' => self::STATUS_POSTED,
            'journal_entry_id' => $entry->id,
            'fiscal_year_id' => $entry->fiscal_year_id,
            'accounting_period_id' => $entry->accounting_period_id,
            'posted_at' => now(),
            'posted_by' => $userId,
        ])->save();

        return $entry;
    }

    /**
     * Undo a posted month: the counter-entry (LedgerPoster::reverse, dated in
     * the month while it is open, else today), the asset back to its figures
     * before the month, and the month due again.
     */
    public function reverse($reason = null, $userId = null)
    {
        if ($this->status !== self::STATUS_POSTED) {
            throw new \RuntimeException(__('ledger_assets.only_posted_can_be_reversed'));
        }
        $userId = $userId ?? auth()->id();

        return \DB::transaction(function () use ($reason, $userId) {
            $asset = $this->fixedAsset;

            if ($this->journalEntry && $this->journalEntry->is_posted && ! $this->journalEntry->is_reversed) {
                LedgerPoster::reverse($this->journalEntry, 'depreciation_reversal', $this->id, $userId, $reason ?: __('ledger_assets.depreciation_reversed'));
            }

            $asset->accumulated_depreciation = round((float) $asset->accumulated_depreciation - (float) $this->depreciation_amount, 2);
            $asset->book_value = round((float) $asset->acquisition_cost - (float) $asset->accumulated_depreciation, 2);
            // the last month still charged, so this month can be charged again
            $asset->last_depreciation_date = static::where('fixed_asset_id', $asset->id)
                ->where('status', self::STATUS_POSTED)->whereKeyNot($this->id)->max('depreciation_date');
            if ($asset->status === FixedAsset::STATUS_FULLY_DEPRECIATED) {
                $asset->status = FixedAsset::STATUS_ACTIVE;
            }
            $asset->save();

            $this->status = self::STATUS_REVERSED;
            $this->notes = ($this->notes ? $this->notes."\n" : '').__('ledger_assets.reversed_on').': '.now()->format('d/m/Y H:i').($reason ? ' - '.$reason : '');
            $this->save();
        });
    }

    public static function statusLabel(?string $status): string
    {
        return $status ? __('ledger_assets.'.$status) : '';
    }

    public function getStatusLabel()
    {
        return self::statusLabel($this->status);
    }

    /** The ap-chip class for a status. */
    public static function statusChip(?string $status): string
    {
        return [self::STATUS_PENDING => 'draft', self::STATUS_POSTED => 'posted', self::STATUS_REVERSED => 'void'][$status] ?? 'draft';
    }

    public function getAuditDescription($event)
    {
        $code = $this->fixedAsset->asset_code ?? 'N/A';
        $date = $this->depreciation_date ? Carbon::parse($this->depreciation_date)->format('M Y') : 'N/A';

        return "Depreciation {$event}: Asset [{$code}], Period: {$date}, Amount: ".number_format((float) ($this->depreciation_amount ?? 0), 2).', Status: '.($this->status ?? 'N/A');
    }
}
