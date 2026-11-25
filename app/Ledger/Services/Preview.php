<?php

namespace App\Ledger\Services;

use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

/**
 * What the review dialog shows (paxhitestbed's SupplierPayablesPreview shape):
 *
 *   ok        may the action be confirmed?
 *   problems  why not (the confirm button stays disabled)
 *   warnings  things to check before confirming
 *   summary   [label, value] pairs
 *   entries   the ledger entries the action would post (account, description, debit, credit)
 *   effects   before / after tables
 *   notes     plain sentences under it all
 *
 * dryRun() runs the REAL action inside a transaction that is always rolled
 * back, and reads the entries it wrote, so the preview cannot disagree with
 * what confirming does.
 */
class Preview
{
    public static function blank(): array
    {
        return ['ok' => true, 'problems' => [], 'warnings' => [], 'summary' => [], 'entries' => [], 'effects' => [], 'notes' => []];
    }

    /** Run $fn for real, capture the entries it posted, undo everything. */
    public static function dryRun(array &$p, callable $fn): void
    {
        $maxBefore = (int) DB::table('journal_entries')->max('id');
        DB::beginTransaction();
        try {
            $fn();
            $made = JournalEntry::with('lines.account')->where('id', '>', $maxBefore)->orderBy('id')->get();
            foreach ($made as $e) {
                $p['entries'][] = self::entry($e);
            }
        } catch (\Throwable $e) {
            $p['ok'] = false;
            $p['problems'][] = $e->getMessage();
        } finally {
            DB::rollBack();
        }
        if (! $p['ok']) {
            $p['entries'] = [];
            $p['effects'] = [];
        }
    }

    /** A journal entry as the preview table shows it. */
    public static function entry(JournalEntry $e, ?string $title = null): array
    {
        $period = $e->accounting_period_id ? \App\Ledger\Models\AccountingPeriod::find($e->accounting_period_id) : null;
        $lines = [];
        foreach ($e->lines as $l) {
            $lines[] = [
                'account' => $l->account ? $l->account->account_code.' '.self::accountName($l->account) : '#'.$l->account_id,
                'description' => $l->description,
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
            ];
        }

        return [
            'title' => $title,
            'date' => $e->entry_date->format('d/m/Y'),
            'period' => $period ? FiscalYear::periodLabel($period) : null,
            'description' => $e->description,
            'lines' => $lines,
            'debit' => (float) $e->lines->sum('debit'),
            'credit' => (float) $e->lines->sum('credit'),
        ];
    }

    public static function accountName(ChartOfAccount $a): string
    {
        return app()->getLocale() === 'fr' && $a->account_name_fr ? $a->account_name_fr : $a->account_name;
    }
}
