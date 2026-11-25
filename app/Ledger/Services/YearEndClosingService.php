<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\AccountingPeriod;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Models\JournalEntry;
use App\Ledger\Models\RecurringJournalEntry;
use App\Ledger\Models\YearEndClosing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Year-end closing (clôture de l'exercice): paxhitestbed
 * App\Models\YearEndClosing + Admin\YearEndClosingController, same steps:
 *
 *   create (year, closing date, retained earnings 12x, result 13x)
 *   → start → checklist → generate the closing entries (review)
 *   → approve (review): the year is closed → reverse (review, with a reason)
 *
 * The closing entry brings every class 6, 7 and 8 account of the year to zero
 * through the result account (130 by default) and carries the net result to
 * retained earnings (121 by default), as paxhitestbed does.
 *
 * PosInnova keeps its stock of goods the SYSCOHADA intermittent way: purchases
 * go to 6011 and the stock account 311 only changes at year end. So before
 * the closing entry, a stock entry dated the closing date:
 *
 *   Dr 6031 / Cr 311   the opening stock (what 311 carries)
 *   Dr 311 / Cr 6031   the closing stock (counted, valued at cost)
 *
 * and the closing entry then closes 6031 with the other expenses. The closing
 * stock is entered by the accountant, prefilled with PosInnova's own stock
 * valuation at the closing date (TransactionUtil::getOpeningClosingStock, the
 * "closing stock by purchase price" of the Profit / Loss report): that
 * valuation is rebuilt from purchase and sale lines and does not date stock
 * adjustments or returns, so the count is the figure that counts.
 *
 * PosInnova's poster refuses closed months, so every month of the year is
 * closed before the closing entries except the one holding the closing date,
 * which approval closes together with the year.
 */
class YearEndClosingService
{
    const STOCK_ACCOUNT = '311';

    const STOCK_CHANGE_ACCOUNT = '6031';

    /* ---------------------------------------------------------------- steps */

    /** @throws \RuntimeException */
    public function create(array $data, ?string $userId): YearEndClosing
    {
        return DB::transaction(function () use ($data, $userId) {
            $year = FiscalYear::findOrFail((int) $data['fiscal_year_id']);
            if ($year->is_closed) {
                throw new \RuntimeException(__('ledger_periodend.ye_year_closed'));
            }
            $existing = YearEndClosing::where('fiscal_year_id', $year->id)
                ->where('status', '!=', YearEndClosing::STATUS_REVERSED)->lockForUpdate()->first();
            if ($existing) {
                throw new \RuntimeException(__('ledger_periodend.closing_already_exists_for_fiscal_year'));
            }
            $date = Carbon::parse($data['closing_date'])->toDateString();
            if ($date < $year->start_date->toDateString() || $date > $year->end_date->toDateString()) {
                throw new \RuntimeException(__('ledger_periodend.ye_date_outside', [
                    'from' => $year->start_date->format('d/m/Y'), 'to' => $year->end_date->format('d/m/Y')]));
            }
            $this->assertAccount((int) $data['retained_earnings_account_id'], '12');
            $this->assertAccount((int) $data['income_summary_account_id'], '13');

            $n = YearEndClosing::count() + 1;
            $closing = YearEndClosing::create([
                'fiscal_year_id' => $year->id,
                'closing_date' => $date,
                'closing_reference' => 'YEC-'.$year->end_date->format('Y').'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'retained_earnings_account_id' => (int) $data['retained_earnings_account_id'],
                'income_summary_account_id' => (int) $data['income_summary_account_id'],
                'status' => YearEndClosing::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            return $closing;
        });
    }

    public function start(YearEndClosing $closing): YearEndClosing
    {
        if ($closing->status !== YearEndClosing::STATUS_DRAFT) {
            throw new \RuntimeException(__('ledger_periodend.only_draft_closings_can_be_started'));
        }
        $closing->forceFill(['status' => YearEndClosing::STATUS_IN_PROGRESS, 'started_at' => now()])->save();

        return $closing;
    }

    /** Tick exactly the items in $ticked; who and when is kept for each one ticked. */
    public function saveChecklist(YearEndClosing $closing, array $ticked, $user): YearEndClosing
    {
        if ($closing->status !== YearEndClosing::STATUS_IN_PROGRESS) {
            throw new \RuntimeException(__('ledger_periodend.closing_must_be_in_progress'));
        }
        $items = $closing->checklistItems();
        foreach ($items as $key => $item) {
            $on = in_array($key, $ticked, true);
            if ($on && empty($item['completed'])) {
                // E-Ride: the user's first and last name (no user_full_name)
                $items[$key] = ['completed' => true, 'completed_by' => $user ? trim($user->first_name.' '.$user->last_name) : null, 'completed_at' => now()->toDateTimeString()];
            } elseif (! $on) {
                $items[$key] = ['completed' => false, 'completed_by' => null, 'completed_at' => null];
            }
        }
        $closing->checklist = $items;
        $closing->save();

        return $closing;
    }

    /** The counted closing stock, at cost. */
    public function setStock(YearEndClosing $closing, float $value, ?string $userId): YearEndClosing
    {
        if (! in_array($closing->status, [YearEndClosing::STATUS_DRAFT, YearEndClosing::STATUS_IN_PROGRESS], true)) {
            throw new \RuntimeException(__('ledger_periodend.closing_must_be_in_progress'));
        }
        if ($value < 0) {
            throw new \RuntimeException(__('ledger_periodend.ye_stock_negative'));
        }
        $closing->forceFill([
            'closing_stock_value' => round($value, 2),
            'stock_valuation' => $this->stockValuation($closing->closing_date->toDateString()),
            'stock_entered_by' => $userId,
            'stock_entered_at' => now(),
        ])->save();

        return $closing;
    }

    /**
     * Post the stock entry and the closing entry; the closing then waits for approval.
     *
     * @throws \RuntimeException
     */
    public function generateClosingEntries(YearEndClosing $closing, ?string $userId): YearEndClosing
    {
        if ($closing->status !== YearEndClosing::STATUS_IN_PROGRESS) {
            throw new \RuntimeException(__('ledger_periodend.closing_must_be_in_progress'));
        }
        if (! $closing->isChecklistComplete()) {
            throw new \RuntimeException(__('ledger_periodend.complete_checklist_before_closing'));
        }
        $blocking = array_filter($this->checks($closing), fn ($c) => ! $c['ok'] && $c['blocking']);
        if ($blocking) {
            throw new \RuntimeException(__('ledger_periodend.ye_checks_fail', ['list' => implode(' ', array_column($blocking, 'detail'))]));
        }

        return DB::transaction(function () use ($closing, $userId) {
            $year = $closing->fiscalYear;
            $date = $closing->closing_date->toDateString();
            $yearName = $year->name ?? '';

            // 1. The stock of goods: the opening stock out, the closing stock in.
            $opening = $this->stockOnBooks($date);
            $counted = round((float) $closing->closing_stock_value, 2);
            $stockId = $this->accountId(self::STOCK_ACCOUNT);
            $changeId = $this->accountId(self::STOCK_CHANGE_ACCOUNT);
            $lines = [];
            if (abs($opening) >= 0.005) {
                $a = abs($opening);
                $lines[] = ['account_id' => $opening > 0 ? $changeId : $stockId, 'debit' => $a, 'credit' => 0, 'description' => __('ledger_periodend.l_opening_stock')];
                $lines[] = ['account_id' => $opening > 0 ? $stockId : $changeId, 'debit' => 0, 'credit' => $a, 'description' => __('ledger_periodend.l_opening_stock')];
            }
            if ($counted >= 0.005) {
                $lines[] = ['account_id' => $stockId, 'debit' => $counted, 'credit' => 0, 'description' => __('ledger_periodend.l_closing_stock')];
                $lines[] = ['account_id' => $changeId, 'debit' => 0, 'credit' => $counted, 'description' => __('ledger_periodend.l_closing_stock')];
            }
            $stockEntry = null;
            if ($lines) {
                $stockEntry = LedgerPoster::post([
                    'entry_date' => $date,
                    'journal_type' => 'adjustment',
                    'description' => __('ledger_periodend.ye_stock_entry').' - '.$yearName,
                    'reference_type' => 'year_end_closing',
                    'reference_id' => $closing->id,
                    'reference_number' => $closing->closing_reference,
                    'is_system_generated' => true,
                ], $lines, $userId);
            }

            // 2. Classes 6, 7 and 8 to the result, the result to retained earnings.
            $amounts = $this->amounts($year);
            $summary = (int) $closing->income_summary_account_id;
            $retained = (int) $closing->retained_earnings_account_id;
            $lines = [];
            foreach ($amounts['accounts'] as $a) {
                $bal = $a['balance']; // credit − debit
                $lines[] = [
                    'account_id' => $a['id'],
                    'debit' => $bal > 0 ? $bal : 0,
                    'credit' => $bal < 0 ? -$bal : 0,
                    'description' => __($a['revenue'] ? 'ledger_periodend.close_revenue_account' : 'ledger_periodend.close_expense_account').' '.$a['code'],
                ];
            }
            $rev = $amounts['revenue'];
            $exp = $amounts['expenses'];
            $net = $amounts['net'];
            if (abs($rev) >= 0.005) {
                $lines[] = ['account_id' => $summary, 'debit' => $rev < 0 ? -$rev : 0, 'credit' => $rev > 0 ? $rev : 0, 'description' => __('ledger_periodend.revenue_to_income_summary')];
            }
            if (abs($exp) >= 0.005) {
                $lines[] = ['account_id' => $summary, 'debit' => $exp > 0 ? $exp : 0, 'credit' => $exp < 0 ? -$exp : 0, 'description' => __('ledger_periodend.expenses_to_income_summary')];
            }
            if (abs($net) >= 0.005) {
                $lines[] = ['account_id' => $summary, 'debit' => $net > 0 ? $net : 0, 'credit' => $net < 0 ? -$net : 0, 'description' => __('ledger_periodend.income_summary_to_retained_earnings')];
                $lines[] = ['account_id' => $retained, 'debit' => $net < 0 ? -$net : 0, 'credit' => $net > 0 ? $net : 0, 'description' => __('ledger_periodend.net_income_to_retained_earnings')];
            }
            $closingEntry = null;
            if (count(array_filter($lines, fn ($l) => $l['debit'] > 0 || $l['credit'] > 0)) >= 2) {
                $closingEntry = LedgerPoster::post([
                    'entry_date' => $date,
                    'journal_type' => 'closing',
                    'description' => __('ledger_periodend.year_end_closing_entry').' - '.$yearName,
                    'reference_type' => 'year_end_closing',
                    'reference_id' => $closing->id,
                    'reference_number' => $closing->closing_reference,
                    'is_system_generated' => true,
                ], $lines, $userId);
            }

            $closing->forceFill([
                'opening_stock_value' => $opening,
                'stock_journal_entry_id' => optional($stockEntry)->id,
                'closing_journal_entry_id' => optional($closingEntry)->id,
                'total_revenue' => $rev,
                'total_expenses' => $exp,
                'net_income' => $net,
                'status' => YearEndClosing::STATUS_PENDING_APPROVAL,
                'updated_by' => $userId,
            ])->save();

            return $closing;
        });
    }

    /** Approve: the closing month and the year are closed. @throws \RuntimeException */
    public function approve(YearEndClosing $closing, ?string $userId): YearEndClosing
    {
        if ($closing->status !== YearEndClosing::STATUS_PENDING_APPROVAL) {
            throw new \RuntimeException(__('ledger_periodend.only_pending_closings_can_be_approved'));
        }
        $this->assertEarlierYearsClosed($closing->fiscalYear);

        return DB::transaction(function () use ($closing, $userId) {
            $year = FiscalYear::whereKey($closing->fiscal_year_id)->lockForUpdate()->firstOrFail();
            $controls = app(PeriodControls::class);
            foreach (AccountingPeriod::where('fiscal_year_id', $year->id)->where('is_closed', false)->orderBy('start_date')->get() as $period) {
                $controls->closeMonth($period, $userId, __('ledger_periodend.ye_closed_note', ['ref' => $closing->closing_reference]));
            }
            if (JournalEntry::where('fiscal_year_id', $year->id)->where('is_posted', false)->exists()) {
                throw new \RuntimeException(__('ledger_periodend.ye_drafts_left'));
            }
            $year->update([
                'is_closed' => true,
                'is_active' => false,
                'closed_by' => $userId,
                'closed_at' => now(),
                'closing_note' => __('ledger_periodend.ye_closed_note', ['ref' => $closing->closing_reference]),
            ]);
            $closing->forceFill([
                'status' => YearEndClosing::STATUS_COMPLETED,
                'completed_at' => now(),
                'approved_by' => $userId,
                'approved_at' => now(),
            ])->save();

            return $closing;
        });
    }

    /**
     * Reverse a completed closing: the year and its closing month reopen, and
     * the closing and stock entries are reversed on their own date (never
     * deleted or un-posted). @throws \RuntimeException
     */
    public function reverse(YearEndClosing $closing, ?string $userId, string $reason): YearEndClosing
    {
        if ($closing->status !== YearEndClosing::STATUS_COMPLETED) {
            throw new \RuntimeException(__('ledger_periodend.only_completed_closings_can_be_reversed'));
        }
        if (trim($reason) === '') {
            throw new \RuntimeException(__('ledger_periodend.ye_give_reason'));
        }
        $year = $closing->fiscalYear;
        if ($year && FiscalYear::whereDate('start_date', '>', $year->end_date)->where('is_closed', true)->exists()) {
            throw new \RuntimeException(__('ledger_periodend.ye_later_year_closed'));
        }

        return DB::transaction(function () use ($closing, $userId, $reason, $year) {
            // The year opens first: nothing can be posted into a closed year.
            $year->update(['is_closed' => false, 'closed_by' => null, 'closed_at' => null]);
            $period = FiscalYear::periodForDate($closing->closing_date->toDateString());
            if ($period && $period->is_closed) {
                app(PeriodControls::class)->reopenMonth($period, $userId, $reason);
            }

            foreach ([$closing->closing_journal_entry_id, $closing->stock_journal_entry_id] as $id) {
                $entry = $id ? JournalEntry::with('lines')->find($id) : null;
                if ($entry && $entry->is_posted && ! $entry->is_reversed) {
                    LedgerPoster::reverse($entry, 'year_end_closing_reversal', $closing->id, $userId, $reason, $entry->entry_date->toDateString());
                }
            }

            $closing->forceFill([
                'status' => YearEndClosing::STATUS_REVERSED,
                'reversed_by' => $userId,
                'reversed_at' => now(),
                'notes' => trim(($closing->notes ? $closing->notes."\n" : '').__('ledger_periodend.reversed_on').': '.now()->format('d/m/Y H:i').' - '.trim($reason)),
            ])->save();

            return $closing;
        });
    }

    /** What reversing would do (run for real, rolled back). */
    public function previewReverse(YearEndClosing $closing, ?string $userId): array
    {
        $p = Preview::blank();
        $p['summary'] = [
            [__('ledger_periodend.fiscal_year'), $closing->fiscalYear->name ?? '-'],
            [__('ledger_periodend.closing_date'), $closing->closing_date->format('d/m/Y')],
            [__('ledger_periodend.net_income'), Ledger::money($closing->net_income)],
        ];
        Preview::dryRun($p, fn () => $this->reverse($closing, $userId, 'preview'));
        $p['notes'][] = __('ledger_periodend.reverse_note');

        return $p;
    }

    public function delete(YearEndClosing $closing): void
    {
        if ($closing->status !== YearEndClosing::STATUS_DRAFT) {
            throw new \RuntimeException(__('ledger_periodend.only_draft_closings_can_be_deleted'));
        }
        $closing->delete();
    }

    /* ------------------------------------------------------------ figures */

    /**
     * The year's class 6, 7 and 8 balances (posted entries of the year) and the
     * totals: revenue (7 and credit 8), expenses (6 and debit 8), net result.
     */
    public function amounts(FiscalYear $year): array
    {
        $bid = Ledger::businessId();
        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('l.business_id', $bid)->where('e.business_id', $bid)->where('a.business_id', $bid)
            ->where('e.fiscal_year_id', $year->id)->where('e.is_posted', 1)->whereNull('e.deleted_at')
            ->where(fn ($q) => $q->where('a.account_code', 'like', '6%')->orWhere('a.account_code', 'like', '7%')->orWhere('a.account_code', 'like', '8%'))
            ->groupBy('a.id', 'a.account_code', 'a.normal_balance')
            ->orderBy('a.account_code')
            ->selectRaw('a.id, a.account_code, a.normal_balance, SUM(l.credit) - SUM(l.debit) as bal')
            ->get();

        $accounts = [];
        $revenue = 0.0;
        $expenses = 0.0;
        foreach ($rows as $r) {
            $bal = round((float) $r->bal, 2);
            if (abs($bal) < 0.005) {
                continue;
            }
            $isRevenue = str_starts_with($r->account_code, '7') || (str_starts_with($r->account_code, '8') && $r->normal_balance === 'credit');
            $accounts[] = ['id' => (int) $r->id, 'code' => $r->account_code, 'balance' => $bal, 'revenue' => $isRevenue];
            if ($isRevenue) {
                $revenue += $bal;
            } else {
                $expenses -= $bal;
            }
        }
        $revenue = round($revenue, 2);
        $expenses = round($expenses, 2);

        return ['accounts' => $accounts, 'revenue' => $revenue, 'expenses' => $expenses, 'net' => round($revenue - $expenses, 2)];
    }

    /**
     * What the closing will show before its entries exist: the ledger's figures
     * with the stock change still to post (opening − closing, an expense).
     */
    public function projection(YearEndClosing $closing): array
    {
        $a = $this->amounts($closing->fiscalYear);
        $pending = ! $closing->stock_journal_entry_id;
        $opening = $pending ? $this->stockOnBooks($closing->closing_date->toDateString()) : (float) $closing->opening_stock_value;
        $change = $closing->closing_stock_value === null ? null : round($opening - (float) $closing->closing_stock_value, 2);
        $expenses = $a['expenses'] + ($pending && $change !== null ? $change : 0);

        return [
            'revenue' => $a['revenue'],
            'expenses_before_stock' => $pending ? $a['expenses'] : round($a['expenses'] - (float) $change, 2),
            'stock_change' => $change,
            'expenses' => round($expenses, 2),
            'net' => round($a['revenue'] - $expenses, 2),
            'opening_stock' => $opening,
        ];
    }

    /** What the stock account (311) carries at the end of $date: the opening stock until the year-end entry. */
    public function stockOnBooks(string $date): float
    {
        $bid = Ledger::businessId();
        $account = ChartOfAccount::where('account_code', self::STOCK_ACCOUNT)->first();
        if (! $account) {
            return 0.0;
        }

        return round((float) $account->opening_balance + (float) DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.business_id', $bid)->where('e.business_id', $bid)->where('l.account_id', $account->id)
            ->where('e.is_posted', 1)->whereNull('e.deleted_at')->where('e.entry_date', '<=', $date)
            ->sum(DB::raw('l.debit - l.credit')), 2);
    }

    /** PosInnova's stock valuation at the end of $date, by purchase price (the Profit / Loss report's closing stock). */
    public function stockValuation(string $date): float
    {
        try {
            return round((float) app(\App\Utils\TransactionUtil::class)->getOpeningClosingStock(Ledger::businessId(), Carbon::parse($date)->toDateString(), null), 2);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * The checks the system makes before the closing entries (paxhitestbed:
     * earlier years closed, ledger health clean), plus PosInnova's month and
     * stock checks. Each: key, label, ok, blocking, detail.
     */
    public function checks(YearEndClosing $closing): array
    {
        $year = $closing->fiscalYear;
        $date = $closing->closing_date->toDateString();
        $out = [];
        $add = function (string $key, bool $ok, bool $blocking, ?string $detail = null) use (&$out) {
            $out[] = ['key' => $key, 'label' => __('ledger_periodend.chk_'.$key), 'ok' => $ok, 'blocking' => $blocking,
                'detail' => $detail ?? ($ok ? __('ledger_periodend.chk_ok') : __('ledger_periodend.chk_'.$key.'_todo'))];
        };

        $earlier = FiscalYear::whereDate('end_date', '<', $year->start_date)->where('is_closed', false)->orderBy('start_date')->pluck('name');
        $add('earlier_years', $earlier->isEmpty(), true, $earlier->isEmpty() ? null : __('ledger_periodend.chk_earlier_years_todo', ['years' => $earlier->implode(', ')]));

        $drafts = JournalEntry::where(function ($q) use ($year) {
            $q->where('fiscal_year_id', $year->id)
                ->orWhere(fn ($q) => $q->whereDate('entry_date', '>=', $year->start_date)->whereDate('entry_date', '<=', $year->end_date));
        })->where('is_posted', false)->count();
        $add('drafts', $drafts === 0, true, $drafts ? trans_choice('ledger_periodend.chk_drafts_todo', $drafts, ['count' => $drafts]) : null);

        $periods = AccountingPeriod::where('fiscal_year_id', $year->id)->orderBy('start_date')->get();
        $closingPeriod = $periods->first(fn ($p) => $p->start_date->toDateString() <= $date && $p->end_date->toDateString() >= $date);
        $open = $periods->filter(fn ($p) => ! $p->is_closed && (! $closingPeriod || $p->id !== $closingPeriod->id))->map(fn ($p) => FiscalYear::periodLabel($p));
        $add('months', $periods->isNotEmpty() && $open->isEmpty(), true, $periods->isEmpty() ? __('ledger_periodend.chk_months_none')
            : ($open->isEmpty() ? __('ledger_periodend.chk_months_ok', ['month' => $closingPeriod ? FiscalYear::periodLabel($closingPeriod) : '-'])
                : __('ledger_periodend.chk_months_todo', ['months' => $open->implode(', ')])));
        $add('closing_month', $closingPeriod && ! $closingPeriod->is_closed, true, ! $closingPeriod ? __('ledger_periodend.chk_months_none')
            : ($closingPeriod->is_closed ? __('ledger_periodend.chk_closing_month_todo', ['month' => FiscalYear::periodLabel($closingPeriod)]) : null));

        $critical = [];
        try {
            // E-Ride: the full checks read posinnova's POS tables; until the Ledger health screen has its
            // bus version, the closing asks the ones that read only the ledger.
            $health = new LedgerHealth();
            $all = \App\Ledger\ERide\Screens::isReady('ledger.ledger-health') ? $health->checks() : $health->ledgerChecks();
            $critical = array_column(array_filter($all, fn ($c) => $c['state'] === LedgerHealth::CRITICAL), 'label');
        } catch (\Throwable $e) {
            $critical = [$e->getMessage()];
        }
        $add('ledger_health', ! $critical, true, $critical ? __('ledger_periodend.chk_ledger_health_todo', ['list' => implode('; ', $critical)]) : null);

        $missing = array_filter([self::STOCK_ACCOUNT, self::STOCK_CHANGE_ACCOUNT], fn ($code) => ! ChartOfAccount::postable()->where('account_code', $code)->exists());
        $add('stock_accounts', ! $missing, true, $missing ? __('ledger_periodend.chk_stock_accounts_todo', ['codes' => implode(', ', $missing)]) : null);

        $entered = $closing->closing_stock_value !== null;
        // E-Ride: bus branches keep no stock: with nothing on 311 at the closing date the closing stock is 0 until one is entered.
        $noStock = ! $entered && abs($this->stockOnBooks($date)) < 0.005;
        $add('stock_value', $entered || $noStock, true, $entered ? __('ledger_periodend.chk_stock_value_ok', ['amount' => Ledger::money($closing->closing_stock_value)])
            : ($noStock ? __('ledger_periodend.chk_stock_value_ok', ['amount' => Ledger::money(0)]) : null));

        // Not blocking: things to look at.
        $notReconciled = app(BankReconciliationService::class)->notReconciledTo($year->start_date->toDateString(), $year->end_date->toDateString());
        $add('bank', ! $notReconciled, false, $notReconciled ? __('ledger_periodend.chk_bank_todo', ['accounts' => implode(', ',
            array_map(fn ($a) => $a[0].' ('.($a[1] ? __('ledger_periodend.reconciled_to').' '.$a[1] : __('ledger_periodend.never_reconciled')).')', $notReconciled))]) : null);

        $due = RecurringJournalEntry::dueToRun($date)->count();
        $add('recurring', $due === 0, false, $due ? trans_choice('ledger_periodend.chk_recurring_todo', $due, ['count' => $due]) : null);

        $onBooks = $this->stockOnBooks($year->start_date->copy()->subDay()->toDateString());
        if (abs($onBooks) < 0.005) {
            $before = $this->stockValuation($year->start_date->copy()->subDay()->toDateString());
            $add('opening_stock', $before < 0.005, false, $before >= 0.005 ? __('ledger_periodend.chk_opening_stock_todo', [
                'amount' => Ledger::money($before), 'date' => $year->start_date->copy()->subDay()->format('d/m/Y')]) : null);
        }

        return $out;
    }

    /* ------------------------------------------------------------ helpers */

    protected function assertEarlierYearsClosed(?FiscalYear $year): void
    {
        if (! $year) {
            return;
        }
        $open = FiscalYear::whereDate('end_date', '<', $year->start_date)->where('is_closed', false)->orderBy('start_date')->pluck('name');
        if ($open->isNotEmpty()) {
            throw new \RuntimeException(__('ledger_periodend.ye_close_earlier', ['years' => $open->implode(', ')]));
        }
    }

    protected function assertAccount(int $id, string $prefix): void
    {
        $a = ChartOfAccount::postable()->whereKey($id)->first();
        if (! $a || ! str_starts_with($a->account_code, $prefix)) {
            throw new \RuntimeException(__('ledger_periodend.ye_account_class', ['class' => $prefix]));
        }
    }

    protected function accountId(string $code): int
    {
        $id = ChartOfAccount::postable()->where('account_code', $code)->value('id');
        if (! $id) {
            throw new \RuntimeException(__('ledger_periodend.chk_stock_accounts_todo', ['codes' => $code]));
        }

        return (int) $id;
    }
}
