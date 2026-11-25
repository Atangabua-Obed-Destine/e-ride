<?php

namespace App\Ledger\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;

class FiscalYear extends Model
{
    use Auditable, BelongsToBusiness;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
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
        'is_active' => 'boolean',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    /**
     * Boot the model and set up event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // When creating or updating, ensure only one active fiscal year
        static::saving(function ($fiscalYear) {
            if ($fiscalYear->is_active) {
                // Deactivate all other fiscal years
                static::where('id', '!=', $fiscalYear->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });
    }

    /**
     * Get accounting periods for this fiscal year
     */
    public function accountingPeriods()
    {
        return $this->hasMany(AccountingPeriod::class)->orderBy('period_number');
    }

    /**
     * Get journal entries for this fiscal year
     */
    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * Get the user who closed this year
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
     * Scope for active fiscal year
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_closed', false);
    }

    /**
     * Scope for open fiscal years
     */
    public function scopeOpen($query)
    {
        return $query->where('is_closed', false);
    }

    /**
     * Check if a date falls within this fiscal year
     */
    public function containsDate($date)
    {
        $date = Carbon::parse($date);
        return $date->between($this->start_date, $this->end_date);
    }

    /**
     * Get the current active fiscal year
     */
    public static function getActiveFiscalYear()
    {
        return static::where('is_active', true)->where('is_closed', false)->first();
    }

    /**
     * The fiscal year a given date belongs to — or null.
     *
     * Null, deliberately, and never "the active one instead". The whole
     * accounting module used to fall back to whichever year was active when no
     * year contained the date, and the result on this database was 912 of 924
     * journal entries stamped with a fiscal year that does not contain them and
     * carrying no accounting period at all. Every report filtered by fiscal
     * year was therefore reporting two calendar years as one.
     *
     * A date with no fiscal year is a real condition with a real answer — make
     * the year first — and silently filing it somewhere wrong is not it.
     */
    public static function forDate($date): ?self
    {
        if (!$date) {
            return null;
        }

        $date = Carbon::parse($date)->toDateString();

        return static::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderBy('start_date')
            ->first();
    }

    /**
     * The accounting period a date belongs to, within its own fiscal year.
     */
    public static function periodForDate($date): ?AccountingPeriod
    {
        $fiscalYear = static::forDate($date);

        if (!$fiscalYear) {
            return null;
        }

        $date = Carbon::parse($date)->toDateString();

        return AccountingPeriod::where('fiscal_year_id', $fiscalYear->id)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    /**
     * Any other fiscal year whose dates overlap this one's.
     *
     * Two fiscal years covering the same day make "which year does this entry
     * belong to" unanswerable, and every report built on the answer arbitrary.
     */
    public function overlapping()
    {
        return static::where('id', '!=', $this->id ?: 0)
            ->whereDate('start_date', '<=', $this->end_date)
            ->whereDate('end_date', '>=', $this->start_date)
            ->get();
    }

    /**
     * Does this year follow the OHADA shape — twelve months ending 31 December?
     *
     * Article 7 of the AUDCIF: the exercice covers twelve months and coincides
     * with the calendar year. The first exercice may depart from that — shorter
     * when it begins in the first half of the year, longer when it begins in
     * the second — but it still ends on 31 December.
     *
     * An academic year (October to July) is not an exercice, and the two being
     * confused is what put this database in the state described above.
     *
     * @return string|null  what is irregular about it, or null when it is fine
     */
    public function shapeWarning(): ?string
    {
        if (!$this->start_date || !$this->end_date) {
            return null;
        }

        $end = Carbon::parse($this->end_date);
        $start = Carbon::parse($this->start_date);

        if ($end->month !== 12 || $end->day !== 31) {
            return sprintf('Under OHADA an exercice ends on 31 December; this one ends on %s.',
                $end->format('j F Y'));
        }

        // A year that starts on 1 January and ends 31 December is the norm.
        if ($start->month === 1 && $start->day === 1 && $start->year === $end->year) {
            return null;
        }

        // Otherwise it can only be a first exercice, and then the rule is about
        // which half of the year it began in.
        $startsInSecondHalf = $start->month >= 7;

        if (!$startsInSecondHalf && $end->year !== $start->year) {
            return sprintf('A first exercice beginning in the first half of the year (%s) may be shorter'
                . ' than twelve months, but not longer — it should end on 31 December %s.',
                $start->format('j F'), $start->year);
        }

        if ($startsInSecondHalf && $end->year > $start->year + 1) {
            return 'A first exercice beginning in the second half of the year may run to 31 December of'
                . ' the FOLLOWING year at the latest.';
        }

        return null;
    }

    /**
     * One accounting period per calendar month of the year, cut at the year's
     * edges (paxhitestbed FiscalYearController::createMonthlyPeriods).
     */
    public function generatePeriods(?string $userId = null): int
    {
        if ($this->accountingPeriods()->count() > 0) {
            throw new \RuntimeException(__('ledger.periods_already_exist'));
        }
        $fr = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
            7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);
        $cur = $start->copy()->startOfMonth();
        $n = 1;
        while ($cur <= $end) {
            $ps = $cur->copy()->startOfMonth();
            $pe = $cur->copy()->endOfMonth();
            if ($ps < $start) {
                $ps = $start->copy();
            }
            if ($pe > $end) {
                $pe = $end->copy();
            }
            AccountingPeriod::create([
                'business_id' => $this->business_id,
                'fiscal_year_id' => $this->id,
                'name' => $cur->copy()->locale('en')->isoFormat('MMMM').' '.$cur->year,
                'french_name' => $fr[$cur->month].' '.$cur->year,
                'period_number' => $n++,
                'start_date' => $ps->toDateString(),
                'end_date' => $pe->toDateString(),
                'created_by' => $userId,
            ]);
            $cur->addMonthNoOverflow()->startOfMonth();
        }

        return $n - 1;
    }

    /** The period's name in the user's language. */
    public static function periodLabel(AccountingPeriod $p): string
    {
        return app()->getLocale() === 'fr' && $p->french_name ? $p->french_name : $p->name;
    }

    /**
     * Get duration in months
     */
    public function getDurationInMonthsAttribute()
    {
        return $this->start_date->diffInMonths($this->end_date) + 1;
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $name = $this->name ?? 'N/A';
        $startDate = $this->start_date ? $this->start_date->format('Y-m-d') : 'N/A';
        $endDate = $this->end_date ? $this->end_date->format('Y-m-d') : 'N/A';
        $isActive = $this->is_active ? 'Active' : 'Inactive';
        $isClosed = $this->is_closed ? 'Closed' : 'Open';
        
        return "Fiscal Year {$event}: {$name} ({$startDate} to {$endDate}), Status: {$isActive}, {$isClosed}";
    }
}
