<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\AccountingPeriod;
use App\Ledger\Models\AccountingSetting;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Month-end: close a month once its books are checked, so nothing more can be
 * posted into it, and reopen it (with a reason) when something must change.
 * paxhitestbed App\Services\Accounting\PeriodControls (the month part; the
 * reversal of a manual entry lives with the journal entries).
 *
 * A closed month refuses postings in LedgerPoster::openPeriodFor; POS
 * documents recorded later for its dates are posted on the day they are
 * recorded (LedgerSync::postingDate).
 *
 * Each action has a preview that runs it for real and rolls it back
 * (Preview::dryRun), so the review dialog cannot disagree with confirming.
 */
class PeriodControls
{
    /** @throws \RuntimeException when the month cannot be closed */
    public function closeMonth(AccountingPeriod $period, ?string $userId, ?string $note = null): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $userId, $note) {
            $period = AccountingPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            $year = FiscalYear::find($period->fiscal_year_id);
            $name = FiscalYear::periodLabel($period);

            if ($period->is_closed) {
                throw new \RuntimeException(__('ledger_fy.already_closed', ['month' => $name]));
            }
            if ($year && $year->is_closed) {
                throw new \RuntimeException(__('ledger_fy.year_closed'));
            }

            $earlierOpen = AccountingPeriod::where('fiscal_year_id', $period->fiscal_year_id)
                ->whereDate('start_date', '<', $period->start_date)
                ->where('is_closed', false)
                ->orderBy('start_date')
                ->get()->map(fn ($p) => FiscalYear::periodLabel($p));
            if ($earlierOpen->isNotEmpty()) {
                throw new \RuntimeException(__('ledger_fy.close_in_order', ['months' => $earlierOpen->implode(', ')]));
            }

            $unposted = $this->entriesIn($period)->where('is_posted', false)->count();
            if ($unposted > 0) {
                throw new \RuntimeException(trans_choice('ledger_fy.unposted_in_month', $unposted, ['count' => $unposted]));
            }

            $period->forceFill([
                'is_closed' => true,
                'closed_by' => $userId,
                'closed_at' => now(),
                'closing_note' => $note,
            ])->save();

            return $period;
        });
    }

    /** @throws \RuntimeException when the month cannot be reopened */
    public function reopenMonth(AccountingPeriod $period, ?string $userId, string $reason): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $userId, $reason) {
            $period = AccountingPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            $year = FiscalYear::find($period->fiscal_year_id);
            $name = FiscalYear::periodLabel($period);

            if (! $period->is_closed) {
                throw new \RuntimeException(__('ledger_fy.not_closed', ['month' => $name]));
            }
            if ($year && $year->is_closed) {
                throw new \RuntimeException(__('ledger_fy.year_closed_reopen'));
            }
            if (trim($reason) === '') {
                throw new \RuntimeException(__('ledger_fy.give_reason'));
            }

            $laterClosed = AccountingPeriod::where('fiscal_year_id', $period->fiscal_year_id)
                ->whereDate('start_date', '>', $period->start_date)
                ->where('is_closed', true)
                ->orderBy('start_date')
                ->get()->map(fn ($p) => FiscalYear::periodLabel($p));
            if ($laterClosed->isNotEmpty()) {
                throw new \RuntimeException(__('ledger_fy.reopen_later_first', ['months' => $laterClosed->implode(', ')]));
            }

            $u = $userId ? \App\User::find($userId) : null; // E-Ride: users have a first and last name, no username
            $who = $userId ? (trim(optional($u)->first_name.' '.optional($u)->last_name) ?: '#'.$userId) : '-';
            $period->forceFill([
                'is_closed' => false,
                'closing_note' => trim(($period->closing_note ? $period->closing_note."\n" : '')
                    .__('ledger_fy.reopened_note', ['when' => now()->format('d/m/Y H:i'), 'user' => $who, 'reason' => trim($reason)])),
                'closed_by' => null,
                'closed_at' => null,
            ])->save();

            return $period;
        });
    }

    /** What closing the month would do, and anything that stops it. */
    public function previewClose(AccountingPeriod $period, ?string $userId): array
    {
        $p = Preview::blank();
        $p['summary'] = $this->monthSummary($period);

        Preview::dryRun($p, fn () => $this->closeMonth($period, $userId));

        foreach ($this->monthWarnings($period) as $warning) {
            $p['warnings'][] = $warning;
        }

        $p['notes'][] = __('ledger_fy.note_close', ['month' => FiscalYear::periodLabel($period)]);

        return $p;
    }

    public function previewReopen(AccountingPeriod $period, ?string $userId): array
    {
        $p = Preview::blank();
        $p['summary'] = $this->monthSummary($period);
        Preview::dryRun($p, fn () => $this->reopenMonth($period, $userId, 'preview'));
        $p['notes'][] = __('ledger_fy.note_reopen', ['month' => FiscalYear::periodLabel($period)]);

        return $p;
    }

    /* ------------------------------------------------------------------ helpers */

    /** The month's entries: filed in it, or dated in it. */
    protected function entriesIn(AccountingPeriod $period)
    {
        return JournalEntry::query()
            ->where(function ($q) use ($period) {
                $q->where('accounting_period_id', $period->id)
                    ->orWhere(fn ($q) => $q->whereDate('entry_date', '>=', $period->start_date)
                        ->whereDate('entry_date', '<=', $period->end_date));
            });
    }

    protected function monthSummary(AccountingPeriod $period): array
    {
        $ids = $this->entriesIn($period)->where('is_posted', true)->pluck('id');
        $byClass = DB::table('journal_entry_lines as l')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('l.business_id', Ledger::businessId())
            ->whereIn('l.journal_entry_id', $ids)
            ->selectRaw('LEFT(a.account_code, 1) as cls, SUM(l.debit) as d, SUM(l.credit) as c')
            ->groupBy('cls')->get()->keyBy('cls');

        $income = (float) (($byClass['7']->c ?? 0) - ($byClass['7']->d ?? 0));
        $expenses = (float) (($byClass['6']->d ?? 0) - ($byClass['6']->c ?? 0));
        $cash = (float) (($byClass['5']->d ?? 0) - ($byClass['5']->c ?? 0));

        return [
            [__('ledger_fy.sum_month'), FiscalYear::periodLabel($period).' ('.Carbon::parse($period->start_date)->format('d/m/Y').' – '.Carbon::parse($period->end_date)->format('d/m/Y').')'],
            [__('ledger_fy.sum_posted_entries'), number_format($ids->count(), 0, ',', ' ')],
            [__('ledger_fy.sum_income'), Ledger::money($income)],
            [__('ledger_fy.sum_expenses'), Ledger::money($expenses)],
            [__('ledger_fy.sum_result'), Ledger::money($income - $expenses)],
            [__('ledger_fy.sum_cash'), Ledger::money($cash)],
        ];
    }

    /**
     * Things worth checking before a month is locked; none of them blocks it.
     *
     * paxhitestbed checks its expenses / incomes, draft supplier bills and bank
     * reconciliations. PosInnova's documents are the POS transactions and
     * payments that post themselves: the warning is any of them dated in the
     * month (from the start of the books) that is not in the ledger yet or
     * could not be posted (Ledger health lists them).
     */
    protected function monthWarnings(AccountingPeriod $period): array
    {
        $bid = Ledger::businessId();
        $from = Carbon::parse($period->start_date)->toDateString();
        $to = Carbon::parse($period->end_date)->toDateString();
        $since = (string) (AccountingSetting::getValue('post_from_date') ?: '1970-01-01');
        if ($since > $from) {
            $from = $since;
        }
        if ($from > $to) {
            return [];
        }

        $missing = function ($q) {
            $q->whereNull('m.id')->orWhere('m.status', 'failed');
        };

        // E-Ride: the bus records of the month that post themselves (money moves, trips that
        // left), not in the ledger yet or refused. Anything else the sync tried and failed on
        // (statements, income, expenses...) is counted from its mapping.
        $agency = DB::table('ledger_businesses')->where('id', $bid)->where('owner_type', \App\Ledger\ERide\Books::AGENCY)->value('owner_id');
        $documents = 0;
        if ($agency) {
            $documents += DB::table('bus_money_moves as mv')->where('mv.agency_id', $agency)->whereNull('mv.skipped')
                ->where('mv.at', '>=', $from.' 00:00:00')->where('mv.at', '<=', $to.' 23:59:59')
                ->leftJoin('transaction_mappings as m', function ($j) use ($bid) {
                    $j->on('m.transaction_id', '=', 'mv.id')->where('m.transaction_type', PostingRules::MOVE)->where('m.business_id', $bid);
                })->where($missing)->count();
            $documents += DB::table('bus_departures as d')->where('d.agency_id', $agency)
                ->where('d.status', \Modules\BusManagement\Entities\BusDeparture::DEPARTED)
                ->whereDate('d.date', '>=', $from)->whereDate('d.date', '<=', $to)
                ->leftJoin('transaction_mappings as m', function ($j) use ($bid) {
                    $j->on('m.transaction_id', '=', 'd.id')->where('m.transaction_type', PostingRules::DEPARTURE)->where('m.business_id', $bid);
                })->where($missing)->count();
        }
        $payments = DB::table('transaction_mappings')->where('business_id', $bid)->where('status', 'failed')
            ->whereNotIn('transaction_type', [PostingRules::MOVE, PostingRules::DEPARTURE])
            ->whereDate('transaction_date', '>=', $from)->whereDate('transaction_date', '<=', $to)->count();

        $warnings = [];
        if ($documents + $payments > 0) {
            $warnings[] = __('ledger_fy.warn_not_in_ledger', ['documents' => $documents, 'payments' => $payments]);
        }

        return $warnings;
    }
}
