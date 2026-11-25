<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use App\Ledger\Services\LedgerPoster;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * A recurring entry (écriture récurrente): a template written into the ledger
 * on a schedule (paxhitestbed App\Models\RecurringJournalEntry, its corrected
 * working tree). Each run writes one numbered entry dated the day it was due,
 * in the fiscal year and month of that date, through LedgerPoster: posted when
 * the template says auto-post, otherwise a draft for the accountant to check
 * and post. A closed month or a date with no open fiscal year refuses the run.
 */
class RecurringJournalEntry extends Model
{
    use SoftDeletes, Auditable, BelongsToBusiness;

    const FREQUENCY_DAILY = 'daily';
    const FREQUENCY_WEEKLY = 'weekly';
    const FREQUENCY_BIWEEKLY = 'biweekly';
    const FREQUENCY_MONTHLY = 'monthly';
    const FREQUENCY_QUARTERLY = 'quarterly';
    const FREQUENCY_SEMIANNUALLY = 'semiannually';
    const FREQUENCY_ANNUALLY = 'annually';

    protected $fillable = [
        'business_id', 'name', 'description', 'journal_type', 'frequency', 'day_of_month', 'day_of_week', 'month_of_year',
        'start_date', 'end_date', 'next_run_date', 'last_run_date', 'occurrences', 'occurrences_completed',
        'is_active', 'auto_post', 'total_debit', 'total_credit', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_run_date' => 'date',
        'last_run_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'is_active' => 'boolean',
        'auto_post' => 'boolean',
        'occurrences' => 'integer',
        'occurrences_completed' => 'integer',
        'day_of_month' => 'integer',
        'day_of_week' => 'integer',
        'month_of_year' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($entry) {
            if (empty($entry->next_run_date)) {
                $entry->next_run_date = $entry->start_date;
            }
        });
    }

    public function lines()
    {
        return $this->hasMany(RecurringJournalEntryLine::class)->orderBy('line_number');
    }

    /** The entries it wrote (journal entries carry reference_type / reference_id). */
    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'reference_id')->where('reference_type', 'recurring_entry');
    }

    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDueToRun($query, $date = null)
    {
        $date = Carbon::parse($date ?? now())->toDateString();

        return $query->where('is_active', true)
            ->whereDate('next_run_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereColumn('end_date', '>=', 'next_run_date'))
            ->where(fn ($q) => $q->whereNull('occurrences')->orWhereRaw('occurrences_completed < occurrences'));
    }

    /** value => label (the screens' wording). */
    public static function getFrequencies(): array
    {
        return [
            self::FREQUENCY_DAILY => __('ledger_periodend.daily'),
            self::FREQUENCY_WEEKLY => __('ledger_periodend.weekly'),
            self::FREQUENCY_BIWEEKLY => __('ledger_periodend.biweekly'),
            self::FREQUENCY_MONTHLY => __('ledger_periodend.monthly'),
            self::FREQUENCY_QUARTERLY => __('ledger_periodend.quarterly'),
            self::FREQUENCY_SEMIANNUALLY => __('ledger_periodend.semiannually'),
            self::FREQUENCY_ANNUALLY => __('ledger_periodend.yearly'),
        ];
    }

    public function getFrequencyLabel(): string
    {
        return self::getFrequencies()[$this->frequency] ?? (string) $this->frequency;
    }

    public function calculateNextRunDate($fromDate = null): Carbon
    {
        $fromDate = Carbon::parse($fromDate ?? $this->next_run_date ?? now());

        switch ($this->frequency) {
            case self::FREQUENCY_DAILY:
                return $fromDate->copy()->addDay();
            case self::FREQUENCY_WEEKLY:
                return $fromDate->copy()->addWeek();
            case self::FREQUENCY_BIWEEKLY:
                return $fromDate->copy()->addWeeks(2);
            case self::FREQUENCY_QUARTERLY:
                return $this->monthsAhead($fromDate, 3);
            case self::FREQUENCY_SEMIANNUALLY:
                return $this->monthsAhead($fromDate, 6);
            case self::FREQUENCY_ANNUALLY:
                return $this->monthsAhead($fromDate, 12);
            case self::FREQUENCY_MONTHLY:
            default:
                return $this->monthsAhead($fromDate, 1);
        }
    }

    /**
     * $months later, on the entry's own day (its day of month, else the day it
     * started), or the month's last day when that month is shorter: 31 January
     * is followed by 28 February, then 31 March.
     */
    protected function monthsAhead(Carbon $from, int $months): Carbon
    {
        $day = (int) ($this->day_of_month ?: ($this->start_date ? Carbon::parse($this->start_date)->day : $from->day));
        $next = $from->copy()->startOfMonth()->addMonthsNoOverflow($months);

        return $next->day(min($day, $next->daysInMonth));
    }

    public function shouldRun($date = null): bool
    {
        $date = Carbon::parse($date ?? now())->endOfDay();

        if (! $this->is_active || ! $this->next_run_date) {
            return false;
        }
        if ($this->end_date && Carbon::parse($this->end_date)->endOfDay()->lt(Carbon::parse($this->next_run_date))) {
            return false;
        }
        if ($this->occurrences && $this->occurrences_completed >= $this->occurrences) {
            return false;
        }

        return Carbon::parse($this->next_run_date)->lte($date);
    }

    /**
     * Write the entry that is due. Null when nothing is due.
     *
     * @throws \RuntimeException when its date has no open fiscal year or month, or the lines are wrong
     */
    public function generateJournalEntry($entryDate = null, ?string $userId = null): ?JournalEntry
    {
        if (! $this->shouldRun($entryDate ?? now())) {
            return null;
        }
        $this->validateLines();

        // The run's own date: the one it was due on.
        $date = Carbon::parse($this->next_run_date)->toDateString();
        LedgerPoster::openPeriodFor($date); // no open fiscal year / closed year / closed month: refused, said in words
        $userId = $userId ?? auth()->id() ?? $this->created_by;

        return DB::transaction(function () use ($date, $userId) {
            $lines = [];
            foreach ($this->lines->sortBy('line_number') as $line) {
                $lines[] = [
                    'account_id' => $line->account_id,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'description' => $line->description ?: ($this->description ?: $this->name),
                ];
            }
            $header = [
                'entry_date' => $date,
                'journal_type' => $this->journal_type ?: 'general',
                'description' => $this->description ?: $this->name,
                'reference_number' => $this->template_reference.'-'.Carbon::parse($date)->format('Ymd'),
                'reference_type' => 'recurring_entry',
                'reference_id' => $this->id,
                'is_system_generated' => false, // the accountant's own entry: edited and posted like one typed by hand
            ];
            $entry = $this->auto_post ? LedgerPoster::post($header, $lines, $userId) : LedgerPoster::draft($header, $lines, $userId);

            $this->last_run_date = $date;
            $this->next_run_date = $this->calculateNextRunDate($date);
            $this->occurrences_completed = (int) $this->occurrences_completed + 1;
            if ($this->occurrences && $this->occurrences_completed >= $this->occurrences) {
                $this->is_active = false;
            }
            if ($this->end_date && Carbon::parse($this->end_date)->lt($this->next_run_date)) {
                $this->is_active = false;
            }
            $this->save();

            return $entry;
        });
    }

    /* ---- The names the screens use, answered from the real columns ---- */

    public function getTemplateReferenceAttribute(): string
    {
        return 'REC-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getTemplateNameAttribute(): ?string
    {
        return $this->name;
    }

    public function getTotalOccurrencesAttribute(): ?int
    {
        return $this->occurrences;
    }

    public function getOccurrencesCreatedAttribute(): int
    {
        return (int) $this->occurrences_completed;
    }

    /** active, paused (switched off) or expired (end date or occurrences reached). */
    public function getStatusAttribute(): string
    {
        if (($this->end_date && Carbon::parse($this->end_date)->lt(now()->startOfDay()))
            || ($this->occurrences && $this->occurrences_completed >= $this->occurrences)) {
            return 'expired';
        }

        return $this->is_active ? 'active' : 'paused';
    }

    /** @throws \RuntimeException */
    public function validateLines(): bool
    {
        $lines = $this->lines->filter(fn ($l) => (float) $l->debit > 0 || (float) $l->credit > 0);
        if ($lines->count() < 2) {
            throw new \RuntimeException(__('ledger_periodend.minimum_two_lines_required'));
        }
        foreach ($lines as $l) {
            if ((float) $l->debit > 0 && (float) $l->credit > 0) {
                throw new \RuntimeException(__('ledger.err_both_sides'));
            }
        }
        $dr = round((float) $lines->sum('debit'), 2);
        $cr = round((float) $lines->sum('credit'), 2);
        if (abs($dr - $cr) > 0.005) {
            throw new \RuntimeException(__('ledger.err_unbalanced', ['debit' => \App\Ledger\Ledger::money($dr), 'credit' => \App\Ledger\Ledger::money($cr)]));
        }

        return true;
    }

    public function isBalanced(): bool
    {
        return abs((float) $this->lines->sum('debit') - (float) $this->lines->sum('credit')) < 0.01;
    }

    public function getAuditDescription($event)
    {
        return "Recurring Entry {$event}: [{$this->template_reference}] {$this->name}, Frequency: {$this->frequency}, Status: ".($this->is_active ? 'Active' : 'Inactive');
    }
}
