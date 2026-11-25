<?php

namespace App\Ledger\Services;

use App\Ledger\Models\AccountingPeriod;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Models\JournalEntry;
use App\Ledger\Models\JournalEntryLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The one way into the ledger. Every posting (sales, purchases, payments,
 * expenses, manual entries, reversals, closings) goes through here, so the
 * rules are checked in one place:
 *
 *  - an entry has at least two lines, each a debit or a credit, never both,
 *    never negative;
 *  - the lines balance (the lines, not the header totals);
 *  - every account belongs to this business, is a detail account and active;
 *  - the date is in a fiscal year and a month that exist and are open.
 *
 * (paxhitestbed has these rules spread over JournalEntry::post, the
 * PostsJournalEntries trait and TransactionAutoMapService, and one of its
 * paths let a closed month through; PosInnova keeps them here.)
 */
class LedgerPoster
{
    const TOLERANCE = 0.005;

    /** E-Ride: the bus cost centres a line may carry (migration ledger_bus_tags). */
    const TAGS = ['departure_id', 'vehicle_id', 'destination_id', 'channel', 'agent_id'];

    /**
     * Write and post an entry.
     *
     * @param  array  $header  entry_date, journal_type, description, reference_type, reference_id,
     *                         reference_number, is_system_generated (default true)
     * @param  array  $lines   [account_id, debit, credit, description?, contact_id?]
     */
    public static function post(array $header, array $lines, ?string $userId): JournalEntry
    {
        return DB::transaction(function () use ($header, $lines, $userId) {
            $entry = self::write($header, $lines, $userId);
            $entry->post($userId);

            return $entry->fresh(['lines']);
        });
    }

    /** Write an entry without posting it (a draft typed by hand). Same checks as posting, except the period. */
    public static function draft(array $header, array $lines, ?string $userId): JournalEntry
    {
        return DB::transaction(function () use ($header, $lines, $userId) {
            $header['is_system_generated'] = $header['is_system_generated'] ?? false;

            return self::write($header, $lines, $userId, false)->fresh(['lines']);
        });
    }

    /** Replace the lines of a draft. */
    public static function replaceLines(JournalEntry $entry, array $lines): void
    {
        if ($entry->is_posted) {
            throw new \RuntimeException(__('ledger.err_posted_locked'));
        }
        $lines = self::cleanLines($lines);
        DB::transaction(function () use ($entry, $lines) {
            $entry->lines()->delete();
            self::insertLines($entry, $lines);
            $entry->forceFill([
                'total_debit' => round(array_sum(array_column($lines, 'debit')), 2),
                'total_credit' => round(array_sum(array_column($lines, 'credit')), 2),
            ])->save();
        });
    }

    /**
     * Post the mirror image of an entry. Dated in the original's month while
     * that month is open, otherwise today (paxhitestbed's rule: a closed
     * month's figures have been reported, so the correction is a new event).
     */
    public static function reverse(JournalEntry $original, string $refType, $refId, ?string $userId, ?string $why = null, ?string $date = null): JournalEntry
    {
        if ($original->is_reversed) {
            throw new \RuntimeException(__('ledger.err_already_reversed'));
        }
        $original->loadMissing('lines');
        if ($date) {
            // a date chosen by hand (manual reversal): never before the entry it reverses
            $date = Carbon::parse($date)->toDateString();
            if ($date < $original->entry_date->toDateString()) {
                throw new \RuntimeException(__('ledger.err_reversal_before', ['date' => $original->entry_date->format('d/m/Y')]));
            }
        } else {
            $date = $original->entry_date->toDateString();
            if (! self::isOpenDate($date)) {
                $date = Carbon::today()->toDateString();
            }
        }

        $lines = [];
        foreach ($original->lines as $l) {
            $lines[] = [
                'account_id' => $l->account_id,
                'debit' => (float) $l->credit,
                'credit' => (float) $l->debit,
                'description' => __('ledger.reversal').': '.$l->description,
                'contact_id' => $l->contact_id,
            ] + array_filter($l->only(self::TAGS), fn ($v) => $v !== null);
        }

        return DB::transaction(function () use ($original, $lines, $date, $refType, $refId, $userId, $why) {
            $rev = self::post([
                'entry_date' => $date,
                'journal_type' => $original->journal_type,
                'description' => __('ledger.reversal').': '.$original->description.($why ? ' ('.$why.')' : ''),
                'reference_type' => $refType,
                'reference_id' => $refId,
                'reference_number' => $original->reference_number,
                'reversed_entry_id' => $original->id,
                'is_system_generated' => true,
            ], $lines, $userId);
            $original->forceFill(['is_reversed' => true])->save();

            return $rev;
        });
    }

    /** Called by JournalEntry::post(): the checks every posting passes. */
    public static function assertPostable(JournalEntry $entry): void
    {
        $lines = $entry->lines()->get();
        if ($lines->count() < 2) {
            throw new \RuntimeException(__('ledger.err_two_lines'));
        }
        $dr = round((float) $lines->sum('debit'), 2);
        $cr = round((float) $lines->sum('credit'), 2);
        if (abs($dr - $cr) > self::TOLERANCE || $dr <= 0) {
            throw new \RuntimeException(__('ledger.err_unbalanced', ['debit' => \App\Ledger\Ledger::money($dr), 'credit' => \App\Ledger\Ledger::money($cr)]));
        }
        $ids = $lines->pluck('account_id')->unique();
        $ok = ChartOfAccount::whereIn('id', $ids)->where('account_category', 'detail')->where('is_active', true)->pluck('id');
        if ($ok->count() !== $ids->count()) {
            throw new \RuntimeException(__('ledger.err_account_not_postable'));
        }

        [$year, $period] = self::openPeriodFor($entry->entry_date->toDateString());
        $entry->fiscal_year_id = $year->id;
        $entry->accounting_period_id = $period->id;
        $entry->total_debit = $dr;
        $entry->total_credit = $cr;
    }

    /** Is $date in an open fiscal year and an open month? */
    public static function isOpenDate(string $date): bool
    {
        try {
            self::openPeriodFor($date);

            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /** @return array{0: FiscalYear, 1: AccountingPeriod} */
    public static function openPeriodFor(string $date): array
    {
        $nice = Carbon::parse($date)->format('d/m/Y');
        $year = FiscalYear::forDate($date);
        if (! $year) {
            throw new \RuntimeException(__('ledger.err_no_fiscal_year', ['date' => $nice]));
        }
        if ($year->is_closed) {
            throw new \RuntimeException(__('ledger.err_year_closed', ['date' => $nice]));
        }
        $period = FiscalYear::periodForDate($date);
        if (! $period) {
            throw new \RuntimeException(__('ledger.err_no_period', ['date' => $nice]));
        }
        if ($period->is_closed) {
            throw new \RuntimeException(__('ledger.err_period_closed', ['date' => $nice]));
        }

        return [$year, $period];
    }

    // ------------------------------------------------------------------

    private static function write(array $header, array $lines, ?string $userId, bool $forPosting = true): JournalEntry
    {
        $lines = self::cleanLines($lines);
        $date = Carbon::parse($header['entry_date'])->toDateString();

        if ($forPosting) {
            [$year, $period] = self::openPeriodFor($date);
        } else {
            $year = FiscalYear::forDate($date);
            $period = FiscalYear::periodForDate($date);
        }

        $entry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber($date),
            'entry_date' => $date,
            'fiscal_year_id' => $year->id ?? null,
            'accounting_period_id' => $period->id ?? null,
            'journal_type' => $header['journal_type'] ?? 'general',
            'description' => mb_substr((string) ($header['description'] ?? ''), 0, 250),
            'reference_number' => $header['reference_number'] ?? null,
            'reference_type' => $header['reference_type'] ?? null,
            'reference_id' => $header['reference_id'] ?? null,
            'total_debit' => round(array_sum(array_column($lines, 'debit')), 2),
            'total_credit' => round(array_sum(array_column($lines, 'credit')), 2),
            'is_posted' => false,
            'is_system_generated' => $header['is_system_generated'] ?? true,
            'created_by' => $userId,
        ]);
        if (! empty($header['reversed_entry_id'])) {
            $entry->forceFill(['reversed_entry_id' => $header['reversed_entry_id']])->save();
        }
        self::insertLines($entry, $lines);

        return $entry;
    }

    private static function insertLines(JournalEntry $entry, array $lines): void
    {
        $n = 1;
        foreach ($lines as $l) {
            JournalEntryLine::create([
                'business_id' => $entry->business_id,
                'journal_entry_id' => $entry->id,
                'line_number' => $n++,
                'account_id' => $l['account_id'],
                'description' => isset($l['description']) ? mb_substr((string) $l['description'], 0, 500) : null,
                'debit' => $l['debit'],
                'credit' => $l['credit'],
                'contact_id' => $l['contact_id'] ?? null,
            ] + array_intersect_key($l, array_flip(self::TAGS)));
        }
    }

    /** Round to 2 decimals, drop zero lines, refuse malformed ones. */
    private static function cleanLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $l) {
            $dr = round((float) ($l['debit'] ?? 0), 2);
            $cr = round((float) ($l['credit'] ?? 0), 2);
            if ($dr < 0 || $cr < 0) {
                throw new \RuntimeException(__('ledger.err_negative'));
            }
            if ($dr > 0 && $cr > 0) {
                throw new \RuntimeException(__('ledger.err_both_sides'));
            }
            if ($dr == 0 && $cr == 0) {
                continue;
            }
            if (empty($l['account_id'])) {
                throw new \RuntimeException(__('ledger.err_no_account'));
            }
            $out[] = ['account_id' => (int) $l['account_id'], 'debit' => $dr, 'credit' => $cr,
                'description' => $l['description'] ?? null, 'contact_id' => $l['contact_id'] ?? null]
                + array_filter(array_intersect_key($l, array_flip(self::TAGS)), fn ($v) => $v !== null && $v !== '');
        }
        if (count($out) < 2) {
            throw new \RuntimeException(__('ledger.err_two_lines'));
        }

        return $out;
    }
}
