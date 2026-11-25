<?php

namespace App\Ledger\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;

class AccountingPeriod extends Model
{
    use Auditable, BelongsToBusiness;

    protected $fillable = [
        'fiscal_year_id',
        'name',
        'french_name',
        'period_number',
        'start_date',
        'end_date',
        'is_closed',
        'closed_by',
        'closed_at',
        'closing_note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
        'period_number' => 'integer',
    ];

    /**
     * Get the fiscal year this period belongs to
     */
    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Get journal entries for this period
     */
    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * Get the user who closed this period
     */
    public function closedBy()
    {
        return $this->belongsTo(\App\User::class, 'closed_by');
    }

    /**
     * Get creator
     */
    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * Get updater
     */
    public function updater()
    {
        return $this->belongsTo(\App\User::class, 'updated_by');
    }

    /**
     * Scope for open periods
     */
    public function scopeOpen($query)
    {
        return $query->where('is_closed', false);
    }

    /**
     * Scope for closed periods
     */
    public function scopeClosed($query)
    {
        return $query->where('is_closed', true);
    }

    /**
     * Check if a date falls within this period
     */
    public function containsDate($date)
    {
        $date = Carbon::parse($date);
        return $date->between($this->start_date, $this->end_date);
    }

    /**
     * Get current open period for a date
     */
    public static function getCurrentPeriodForDate($date)
    {
        $date = Carbon::parse($date);
        return static::where('is_closed', false)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        // Load fiscal year relationship if not loaded
        try {
            if (!$this->relationLoaded('fiscalYear') && $this->fiscal_year_id) {
                $this->load('fiscalYear');
            }
        } catch (\Exception $e) {
            // Silently handle loading errors
        }
        
        $name = $this->name ?? 'N/A';
        $periodNumber = $this->period_number ?? 'N/A';
        
        // Get fiscal year name with fallback
        $fiscalYearName = 'Unknown Fiscal Year';
        if ($this->fiscalYear) {
            $fiscalYearName = $this->fiscalYear->name ?? 'FY #' . $this->fiscal_year_id;
        } elseif ($this->fiscal_year_id) {
            $fiscalYear = \App\Ledger\Models\FiscalYear::find($this->fiscal_year_id);
            $fiscalYearName = $fiscalYear ? ($fiscalYear->name ?? 'FY #' . $this->fiscal_year_id) : 'FY #' . $this->fiscal_year_id;
        }
        
        $startDate = $this->start_date ? $this->start_date->format('Y-m-d') : 'N/A';
        $endDate = $this->end_date ? $this->end_date->format('Y-m-d') : 'N/A';
        $isClosed = $this->is_closed ? 'Closed' : 'Open';
        
        return "Accounting Period {$event}: {$name} (Period #{$periodNumber}), FY: {$fiscalYearName} ({$startDate} to {$endDate}), Status: {$isClosed}";
    }
}
