<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use Illuminate\Support\Facades\DB;

/**
 * Can these figures be taken into a meeting? (paxhitestbed
 * App\Services\Accounting\LedgerConfidence)
 *
 * The handful of facts that decide whether a number on the analysis is safe
 * to present, so the accountant meets them before the meeting. Read-only.
 *
 * paxhitestbed's checks, adapted:
 *   money on no budget line   -> money on the suspense account (4711)
 *   payroll not yet posted    -> documents not yet in the ledger (failed mappings)
 *   entries in the wrong exercice, entries with no accounting period, debits equal credits: as there
 *   budget matches an exercice: no budgets in PosInnova, not ported
 * Every query is limited to the current business.
 */
class DaybookConfidence
{
    public const GOOD = 'good';

    public const WARNING = 'warning';

    public const CRITICAL = 'critical';

    /** Every check, worst first. $rows: the daybook rows the analysis covers. */
    public function checks(array $rows): array
    {
        $checks = [
            $this->unallocated($rows),
            $this->unposted(),
            $this->misfiledEntries(),
            $this->entriesWithoutPeriod(),
            $this->ledgerBalances(),
        ];
        $rank = [self::CRITICAL => 0, self::WARNING => 1, self::GOOD => 2];
        usort($checks, fn ($a, $b) => $rank[$a['state']] <=> $rank[$b['state']]);

        return $checks;
    }

    public function allClear(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['state'] !== self::GOOD) {
                return false;
            }
        }

        return true;
    }

    public function concerns(array $checks): int
    {
        return count(array_filter($checks, fn ($c) => $c['state'] !== self::GOOD));
    }

    /** Money in the cash boxes that nobody has said what it was. */
    protected function unallocated(array $rows): array
    {
        $u = app(DaybookService::class)->unallocatedSummary($rows);

        return [
            'key' => 'unallocated',
            'label' => __('ledger_daybook.chk_unallocated'),
            'state' => $u['amount'] > 0 ? ($u['share'] >= 5 ? self::CRITICAL : self::WARNING) : self::GOOD,
            'figure' => Ledger::money($u['amount']),
            'detail' => $u['amount'] > 0
                ? __('ledger_daybook.chk_unallocated_bad', ['n' => $u['count'], 'pct' => number_format($u['share'], 1)])
                : __('ledger_daybook.chk_unallocated_ok'),
            'action' => $u['amount'] > 0 ? __('ledger_daybook.chk_unallocated_do') : null,
        ];
    }

    /** Documents the ledger could not post: their money is in no figure here. */
    protected function unposted(): array
    {
        $q = DB::table('transaction_mappings')->where('business_id', Ledger::businessId())->where('status', 'failed');
        $count = (clone $q)->count();

        return [
            'key' => 'unposted',
            'label' => __('ledger_daybook.chk_unposted'),
            'state' => $count > 0 ? self::WARNING : self::GOOD,
            'figure' => (string) $count,
            'detail' => $count > 0 ? __('ledger_daybook.chk_unposted_bad', ['n' => $count]) : __('ledger_daybook.chk_unposted_ok'),
            'action' => $count > 0 ? __('ledger_daybook.chk_unposted_do') : null,
        ];
    }

    /** Entries filed under a fiscal year that does not contain their own date. */
    protected function misfiledEntries(): array
    {
        $bid = Ledger::businessId();
        $count = DB::table('journal_entries')->where('business_id', $bid)->whereNull('deleted_at')->whereNotNull('fiscal_year_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('fiscal_years')
                    ->whereColumn('fiscal_years.id', 'journal_entries.fiscal_year_id')
                    ->whereColumn('fiscal_years.business_id', 'journal_entries.business_id')
                    ->whereColumn('fiscal_years.start_date', '<=', 'journal_entries.entry_date')
                    ->whereColumn('fiscal_years.end_date', '>=', 'journal_entries.entry_date');
            })->count();

        return [
            'key' => 'misfiled',
            'label' => __('ledger_daybook.chk_misfiled'),
            'state' => $count > 0 ? self::CRITICAL : self::GOOD,
            'figure' => (string) $count,
            'detail' => $count > 0 ? __('ledger_daybook.chk_misfiled_bad') : __('ledger_daybook.chk_misfiled_ok'),
            'action' => $count > 0 ? __('ledger_daybook.chk_misfiled_do') : null,
        ];
    }

    protected function entriesWithoutPeriod(): array
    {
        $count = DB::table('journal_entries')->where('business_id', Ledger::businessId())->whereNull('deleted_at')
            ->where('is_posted', 1)->whereNull('accounting_period_id')->count();

        return [
            'key' => 'no_period',
            'label' => __('ledger_daybook.chk_no_period'),
            'state' => $count > 0 ? self::WARNING : self::GOOD,
            'figure' => (string) $count,
            'detail' => $count > 0 ? __('ledger_daybook.chk_no_period_bad') : __('ledger_daybook.chk_no_period_ok'),
            'action' => $count > 0 ? __('ledger_daybook.chk_no_period_do') : null,
        ];
    }

    /**
     * The oldest check in bookkeeping, on the lines of posted entries (the
     * lines are what the statements add up, not the header totals).
     */
    protected function ledgerBalances(): array
    {
        $bid = Ledger::businessId();
        $sum = DB::table('journal_entry_lines as l')->join('journal_entries as je', 'je.id', '=', 'l.journal_entry_id')
            ->where('l.business_id', $bid)->where('je.business_id', $bid)->where('je.is_posted', 1)->whereNull('je.deleted_at')
            ->selectRaw('COALESCE(SUM(l.debit), 0) as d, COALESCE(SUM(l.credit), 0) as c')->first();
        $debits = (float) $sum->d;
        $credits = (float) $sum->c;
        $balanced = number_format($debits, 2, '.', '') === number_format($credits, 2, '.', '');

        return [
            'key' => 'balanced',
            'label' => __('ledger_daybook.chk_balanced'),
            'state' => $balanced ? self::GOOD : self::CRITICAL,
            'figure' => Ledger::money($debits),
            'detail' => $balanced ? __('ledger_daybook.chk_balanced_ok') : __('ledger_daybook.chk_balanced_bad', [
                'debits' => Ledger::money($debits), 'credits' => Ledger::money($credits), 'diff' => Ledger::money(abs($debits - $credits)),
            ]),
            'action' => $balanced ? null : __('ledger_daybook.chk_balanced_do'),
        ];
    }
}
