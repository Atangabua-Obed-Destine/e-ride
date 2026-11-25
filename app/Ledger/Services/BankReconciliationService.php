<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\BankReconciliation;
use App\Ledger\Models\BankReconciliationItem;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bank reconciliation (rapprochement bancaire): does the bank statement agree
 * with the books? paxhitestbed App\Services\Accounting\BankReconciliationService.
 *
 * The accountant picks a bank, mobile money, card or cash account (class 5)
 * and enters the statement's date and closing balance. Every posted movement
 * on that account up to the statement date that an earlier reconciliation has
 * not already matched is listed, and the accountant ticks the ones on the
 * statement. What is left unticked is timing:
 *
 *   statement balance + deposits in transit − outstanding payments
 *     = the balance the books should show            (adjusted bank balance)
 *
 * When that equals the ledger balance at the statement date the difference is
 * nil and the reconciliation can be completed. Bank charges or interest on the
 * statement that the books do not have yet are posted from here (Dr charges /
 * Cr bank, or Dr bank / Cr interest), through the review dialog, and appear
 * ticked straight away. A completed reconciliation is where the next starts:
 * its ticked movements are never offered again, its unticked ones are.
 *
 * PosInnova differences:
 *  - each item keeps the ledger line it lists, and matching is by that line;
 *  - POS payments keep arriving while a reconciliation is open (LedgerSync), so
 *    every save brings in new movements up to the statement date and drops
 *    those whose entry is no longer posted;
 *  - an entry reversed by LedgerSync (a POS document edited) and its reversal,
 *    both before the statement date and neither ever ticked, cancel out and
 *    are not listed;
 *  - a charge or interest is posted to a chosen class 6 / 7 account (631 and
 *    771 by default): PosInnova has no income categories;
 *  - raw queries carry business_id (many businesses share the database).
 */
class BankReconciliationService
{
    /** Bank, mobile money, card and cash accounts that can be reconciled (class 5, not 58 transfers or 59 provisions). */
    public function accounts(): Collection
    {
        return ChartOfAccount::where('account_code', 'like', '5%')
            ->where('account_code', 'not like', '58%')
            ->where('account_code', 'not like', '59%')
            ->where('account_category', 'detail')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();
    }

    /** The account's ledger balance (debit − credit) at the end of $asOf. */
    public function bookBalance(int $accountId, string $asOf): float
    {
        $bid = Ledger::businessId();
        $account = ChartOfAccount::find($accountId);
        $opening = $account ? (float) $account->opening_balance * ($account->normal_balance === 'credit' ? -1 : 1) : 0.0;

        return round($opening + (float) DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.business_id', $bid)->where('e.business_id', $bid)
            ->where('l.account_id', $accountId)
            ->where('e.is_posted', 1)
            ->whereNull('e.deleted_at')
            ->where('e.entry_date', '<=', $asOf)
            ->sum(DB::raw('l.debit - l.credit')), 2);
    }

    public function lastCompleted(int $accountId): ?BankReconciliation
    {
        return BankReconciliation::where('chart_of_account_id', $accountId)
            ->whereIn('status', [BankReconciliation::STATUS_COMPLETED, BankReconciliation::STATUS_APPROVED])
            ->orderByDesc('statement_date')->orderByDesc('id')
            ->first();
    }

    /**
     * Start a reconciliation.
     *
     * @throws \RuntimeException
     */
    public function create(array $data, ?string $userId): BankReconciliation
    {
        return DB::transaction(function () use ($data, $userId) {
            $accountId = (int) $data['chart_of_account_id'];
            $date = Carbon::parse($data['statement_date'])->toDateString();

            if (! $this->accounts()->contains('id', $accountId)) {
                throw new \RuntimeException(__('ledger_periodend.br_choose_account'));
            }

            $open = BankReconciliation::where('chart_of_account_id', $accountId)
                ->whereIn('status', [BankReconciliation::STATUS_DRAFT, BankReconciliation::STATUS_IN_PROGRESS])
                ->lockForUpdate()->first();
            if ($open) {
                throw new \RuntimeException(__('ledger_periodend.br_already_open', ['date' => $open->statement_date->format('d/m/Y')]));
            }

            $last = $this->lastCompleted($accountId);
            if ($last && $date <= $last->statement_date->toDateString()) {
                throw new \RuntimeException(__('ledger_periodend.br_already_reconciled', ['date' => $last->statement_date->format('d/m/Y')]));
            }

            $opening = $data['statement_opening_balance'] ?? null;
            $rec = BankReconciliation::create([
                'chart_of_account_id' => $accountId,
                'statement_date' => $date,
                'reconciliation_date' => now()->toDateString(),
                'statement_reference' => $data['statement_reference'] ?? null,
                'statement_opening_balance' => ($opening !== null && $opening !== '') ? round((float) $opening, 2) : ($last ? (float) $last->statement_closing_balance : 0),
                'statement_closing_balance' => round((float) $data['statement_closing_balance'], 2),
                'book_balance' => $this->bookBalance($accountId, $date),
                'status' => BankReconciliation::STATUS_IN_PROGRESS,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            return $this->recalculate($rec);
        });
    }

    /**
     * Posted lines on the account up to $date that no completed reconciliation
     * has ticked, and that $rec (when given) does not already list.
     */
    public function unmatchedLines(int $accountId, string $date, ?BankReconciliation $rec = null): Collection
    {
        $bid = Ledger::businessId();
        $taken = DB::table('bank_reconciliation_items as i')
            ->join('bank_reconciliations as r', 'r.id', '=', 'i.bank_reconciliation_id')
            ->where('i.business_id', $bid)->where('r.business_id', $bid)
            ->where('r.chart_of_account_id', $accountId)
            ->whereIn('r.status', [BankReconciliation::STATUS_COMPLETED, BankReconciliation::STATUS_APPROVED])
            ->whereNull('r.deleted_at')
            ->where('i.is_cleared', 1)
            ->whereNotNull('i.journal_entry_line_id')
            ->pluck('i.journal_entry_line_id')->map(fn ($v) => (int) $v)->all();
        $listed = $rec ? DB::table('bank_reconciliation_items')->where('business_id', $bid)
            ->where('bank_reconciliation_id', $rec->id)->whereNotNull('journal_entry_line_id')
            ->pluck('journal_entry_line_id')->map(fn ($v) => (int) $v)->all() : [];
        $taken = array_flip(array_merge($taken, $listed));

        $lines = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.business_id', $bid)->where('e.business_id', $bid)
            ->where('l.account_id', $accountId)
            ->where('e.is_posted', 1)
            ->whereNull('e.deleted_at')
            ->where('e.entry_date', '<=', $date)
            ->orderBy('e.entry_date')->orderBy('l.id')
            ->get(['l.id', 'l.journal_entry_id', 'l.debit', 'l.credit', 'l.description',
                'e.entry_number', 'e.entry_date', 'e.description as entry_description', 'e.reversed_entry_id']);

        // An entry and its reversal, both up to the date and neither ticked or listed: they cancel out.
        $byEntry = $lines->groupBy('journal_entry_id');
        $skip = [];
        foreach ($lines->whereNotNull('reversed_entry_id') as $r) {
            if (! isset($byEntry[$r->reversed_entry_id])) {
                continue;
            }
            $ids = $byEntry[$r->reversed_entry_id]->pluck('id')->merge($byEntry[$r->journal_entry_id]->pluck('id'));
            if ($ids->every(fn ($id) => ! isset($taken[(int) $id]))) {
                $skip[$r->reversed_entry_id] = true;
                $skip[$r->journal_entry_id] = true;
            }
        }

        return $lines->filter(fn ($l) => ! isset($taken[(int) $l->id]) && ! isset($skip[$l->journal_entry_id])
            && ((float) $l->debit + (float) $l->credit) > 0)->values();
    }

    /** Tick or untick movements that appear on the statement. */
    public function setCleared(BankReconciliation $rec, array $itemIds, bool $cleared): BankReconciliation
    {
        $this->assertEditable($rec);

        $rec->items()->whereIn('id', $itemIds)->update([
            'is_cleared' => $cleared,
            'cleared_date' => $cleared ? $rec->statement_date->toDateString() : null,
        ]);

        return $this->recalculate($rec);
    }

    /** Set exactly which movements are ticked. */
    public function saveTicks(BankReconciliation $rec, array $clearedIds): BankReconciliation
    {
        $this->assertEditable($rec);

        DB::transaction(function () use ($rec, $clearedIds) {
            $rec->items()->update(['is_cleared' => false, 'cleared_date' => null]);
            $rec->items()->whereIn('id', $clearedIds)->update(['is_cleared' => true, 'cleared_date' => $rec->statement_date->toDateString()]);
        });

        return $this->recalculate($rec);
    }

    public function updateStatement(BankReconciliation $rec, float $closing, ?string $reference = null): BankReconciliation
    {
        $this->assertEditable($rec);
        $rec->statement_closing_balance = round($closing, 2);
        if ($reference !== null) {
            $rec->statement_reference = $reference;
        }
        $rec->save();

        return $this->recalculate($rec);
    }

    /** The reconciliation's figures. */
    public function position(BankReconciliation $rec): array
    {
        $items = $rec->items()->get();
        $inTransit = round((float) $items->where('is_cleared', false)->where('effect', 'add')->sum('amount'), 2);
        $outstanding = round((float) $items->where('is_cleared', false)->where('effect', 'subtract')->sum('amount'), 2);
        $book = (float) $rec->book_balance;
        $statement = (float) $rec->statement_closing_balance;
        $adjusted = round($statement + $inTransit - $outstanding, 2);

        return [
            'book' => $book,
            'statement' => $statement,
            'in_transit' => $inTransit,
            'outstanding' => $outstanding,
            'adjusted' => $adjusted,
            'difference' => round($book - $adjusted, 2),
            'ticked' => $items->where('is_cleared', true)->count(),
            'unticked' => $items->where('is_cleared', false)->count(),
        ];
    }

    /** Bring the movements up to date (while editable) and recompute the figures. */
    public function recalculate(BankReconciliation $rec): BankReconciliation
    {
        if ($rec->isEditable()) {
            $this->syncMovements($rec);
        }
        $rec->book_balance = $this->bookBalance((int) $rec->chart_of_account_id, $rec->statement_date->toDateString());
        $rec->save();

        $p = $this->position($rec);
        $rec->forceFill([
            'outstanding_deposits' => $p['in_transit'],
            'outstanding_checks' => $p['outstanding'],
            'bank_charges' => round((float) $rec->items()->where('item_type', 'bank_charge')->sum('amount'), 2),
            'bank_interest' => round((float) $rec->items()->where('item_type', 'bank_interest')->sum('amount'), 2),
            'adjusted_book_balance' => $p['adjusted'],
            'difference' => $p['difference'],
        ])->save();

        return $rec->fresh();
    }

    /**
     * List the movements posted since the reconciliation was started, and drop
     * the ones whose entry is no longer posted (un-posted, deleted).
     */
    protected function syncMovements(BankReconciliation $rec): void
    {
        $bid = Ledger::businessId();
        $date = $rec->statement_date->toDateString();
        $gone = DB::table('bank_reconciliation_items as i')
            ->where('i.business_id', $bid)->where('i.bank_reconciliation_id', $rec->id)
            ->whereNotNull('i.journal_entry_line_id')
            ->whereNotExists(function ($q) use ($bid, $date) {
                $q->select(DB::raw(1))->from('journal_entry_lines as l')
                    ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
                    ->whereColumn('l.id', 'i.journal_entry_line_id')
                    ->where('e.business_id', $bid)->where('e.is_posted', 1)->whereNull('e.deleted_at')
                    ->where('e.entry_date', '<=', $date);
            })
            ->pluck('i.id');
        if ($gone->isNotEmpty()) {
            $rec->items()->whereIn('id', $gone)->delete();
        }

        foreach ($this->unmatchedLines((int) $rec->chart_of_account_id, $rec->statement_date->toDateString(), $rec) as $line) {
            $in = (float) $line->debit > 0;
            BankReconciliationItem::create([
                'bank_reconciliation_id' => $rec->id,
                'item_type' => $in ? BankReconciliationItem::TYPE_DEPOSIT_IN_TRANSIT : BankReconciliationItem::TYPE_OUTSTANDING_CHECK,
                'reference_number' => $line->entry_number,
                'transaction_date' => $line->entry_date,
                'description' => mb_substr((string) ($line->description ?: $line->entry_description), 0, 190),
                'amount' => round($in ? (float) $line->debit : (float) $line->credit, 2),
                'effect' => $in ? BankReconciliationItem::EFFECT_ADD : BankReconciliationItem::EFFECT_SUBTRACT,
                'is_cleared' => false,
                'journal_entry_id' => $line->journal_entry_id,
                'journal_entry_line_id' => $line->id,
            ]);
        }
    }

    /**
     * The accounts a charge (class 6) or interest (class 7) can go to, with
     * the default first: 631 bank charges, 771 interest received.
     *
     * @return array{bank_charge: Collection, bank_interest: Collection}
     */
    public function adjustmentAccounts(): array
    {
        $of = fn (string $class) => ChartOfAccount::postable()->where('account_code', 'like', $class.'%')->orderBy('account_code')->get();

        return ['bank_charge' => $of('6'), 'bank_interest' => $of('7')];
    }

    public static function defaultAdjustmentCode(string $kind): string
    {
        return $kind === 'bank_charge' ? '631' : '771';
    }

    /**
     * Post a bank charge or interest shown on the statement but not in the books.
     *
     * @param  array{kind:string, amount:float, date:string, account_id:int, description?:?string}  $data
     *
     * @throws \RuntimeException
     */
    public function addAdjustment(BankReconciliation $rec, array $data, ?string $userId): BankReconciliationItem
    {
        return DB::transaction(function () use ($rec, $data, $userId) {
            $this->assertEditable($rec);

            $kind = $data['kind'];
            $amount = round((float) $data['amount'], 2);
            $date = Carbon::parse($data['date'] ?? $rec->statement_date)->toDateString();
            $other = ChartOfAccount::find((int) ($data['account_id'] ?? 0));

            if (! in_array($kind, ['bank_charge', 'bank_interest'], true)) {
                throw new \RuntimeException(__('ledger_periodend.br_choose_kind'));
            }
            if ($amount <= 0) {
                throw new \RuntimeException(__('ledger_periodend.br_amount_positive'));
            }
            if ($date > $rec->statement_date->toDateString()) {
                throw new \RuntimeException(__('ledger_periodend.br_date_after_statement'));
            }
            if (! $other || $other->account_category !== 'detail' || ! $other->is_active) {
                throw new \RuntimeException(__('ledger_periodend.br_choose_other_account'));
            }
            if ($kind === 'bank_charge' && ! str_starts_with($other->account_code, '6')) {
                throw new \RuntimeException(__('ledger_periodend.br_charges_class6'));
            }
            if ($kind === 'bank_interest' && ! str_starts_with($other->account_code, '7')) {
                throw new \RuntimeException(__('ledger_periodend.br_interest_class7'));
            }

            $bank = (int) $rec->chart_of_account_id;
            $description = trim(($kind === 'bank_charge' ? __('ledger_periodend.bank_charges') : __('ledger_periodend.bank_interest'))
                .' — '.__('ledger_periodend.br_statement_of', ['date' => $rec->statement_date->format('d/m/Y')])
                .(! empty($data['description']) ? ' — '.$data['description'] : ''));

            [$debit, $credit] = $kind === 'bank_charge' ? [$other->id, $bank] : [$bank, $other->id];
            $entry = LedgerPoster::post([
                'entry_date' => $date,
                'journal_type' => 'bank',
                'description' => $description,
                'reference_type' => 'bank_reconciliation',
                'reference_id' => $rec->id,
                'reference_number' => $rec->statement_reference,
                'is_system_generated' => true,
            ], [
                ['account_id' => $debit, 'debit' => $amount, 'credit' => 0, 'description' => $description],
                ['account_id' => $credit, 'debit' => 0, 'credit' => $amount, 'description' => $description],
            ], $userId);
            $bankLine = $entry->lines->firstWhere('account_id', $bank);

            // It is on the statement, so it is matched from the start.
            $item = BankReconciliationItem::create([
                'bank_reconciliation_id' => $rec->id,
                'item_type' => $kind,
                'account_id' => $other->id,
                'reference_number' => $entry->entry_number,
                'transaction_date' => $date,
                'description' => mb_substr($description, 0, 190),
                'amount' => $amount,
                'effect' => $kind === 'bank_charge' ? BankReconciliationItem::EFFECT_SUBTRACT : BankReconciliationItem::EFFECT_ADD,
                'is_cleared' => true,
                'cleared_date' => $rec->statement_date->toDateString(),
                'journal_entry_id' => $entry->id,
                'journal_entry_line_id' => optional($bankLine)->id,
            ]);

            $this->recalculate($rec);

            return $item;
        });
    }

    /** @throws \RuntimeException unless the difference is nil */
    public function complete(BankReconciliation $rec, ?string $userId): BankReconciliation
    {
        return DB::transaction(function () use ($rec, $userId) {
            $this->assertEditable($rec);
            $rec = $this->recalculate($rec);

            if (abs((float) $rec->difference) >= 0.01) {
                throw new \RuntimeException(__('ledger_periodend.br_differ', ['amount' => Ledger::money($rec->difference)]));
            }

            $rec->forceFill([
                'status' => BankReconciliation::STATUS_COMPLETED,
                'reconciled_by' => $userId,
                'reconciled_at' => now(),
            ])->save();

            return $rec;
        });
    }

    /** Only one still in progress can be deleted; a completed one is history. */
    public function delete(BankReconciliation $rec): void
    {
        $this->assertEditable($rec);

        DB::transaction(function () use ($rec) {
            $rec->items()->delete();
            $rec->delete();
        });
    }

    /* ---- previews (the review dialog) --------------------------------- */

    public function previewAdjustment(BankReconciliation $rec, array $data, ?string $userId): array
    {
        $p = Preview::blank();
        $before = $this->position($rec);
        $after = null;

        Preview::dryRun($p, function () use ($rec, $data, $userId, &$after) {
            $this->addAdjustment($rec, $data, $userId);
            $after = $this->position($rec->fresh());
        });

        if ($p['ok'] && $after) {
            foreach ($p['entries'] as $i => $e) {
                $p['entries'][$i]['title'] = __('ledger_periodend.br_entry_to_post');
            }
            $p['effects'][] = [
                'title' => __('ledger_periodend.br_effect'),
                'columns' => ['', __('ledger_periodend.before'), __('ledger_periodend.after')],
                'numeric' => [1, 2],
                'rows' => [
                    [__('ledger_periodend.balance_in_books'), Ledger::money($before['book']), Ledger::money($after['book'])],
                    [__('ledger_periodend.adjusted_bank_balance'), Ledger::money($before['adjusted']), Ledger::money($after['adjusted'])],
                    [__('ledger_periodend.difference'), Ledger::money($before['difference']), Ledger::money($after['difference'])],
                ],
            ];
        }

        return $p;
    }

    public function previewComplete(BankReconciliation $rec, ?string $userId): array
    {
        $p = Preview::blank();
        $pos = $this->position($rec);
        $account = ChartOfAccount::find($rec->chart_of_account_id);

        $p['summary'] = [
            [__('ledger_periodend.account'), $account ? $account->account_code.' '.Preview::accountName($account) : '#'.$rec->chart_of_account_id],
            [__('ledger_periodend.statement_date'), $rec->statement_date->format('d/m/Y')],
            [__('ledger_periodend.statement_balance'), Ledger::money($pos['statement'])],
            [__('ledger_periodend.plus_in_transit'), Ledger::money($pos['in_transit'])],
            [__('ledger_periodend.minus_outstanding'), Ledger::money($pos['outstanding'])],
            [__('ledger_periodend.eq_adjusted'), Ledger::money($pos['adjusted'])],
            [__('ledger_periodend.balance_in_books'), Ledger::money($pos['book'])],
            [__('ledger_periodend.difference'), Ledger::money($pos['difference'])],
        ];

        Preview::dryRun($p, fn () => $this->complete($rec, $userId));

        if ($pos['unticked'] > 0) {
            $p['notes'][] = trans_choice('ledger_periodend.br_carried', $pos['unticked'], ['count' => $pos['unticked']]);
        }
        $p['notes'][] = __('ledger_periodend.br_completing_locks');

        return $p;
    }

    /* ---- for the year-end checklist ------------------------------------ */

    /**
     * Accounts that moved in [$from, $to] and are not reconciled to $to:
     * [code name, reconciled to (date or null)].
     */
    public function notReconciledTo(string $from, string $to): array
    {
        $bid = Ledger::businessId();
        $out = [];
        foreach ($this->accounts() as $a) {
            $moved = DB::table('journal_entry_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
                ->where('l.business_id', $bid)->where('e.business_id', $bid)->where('l.account_id', $a->id)
                ->where('e.is_posted', 1)->whereNull('e.deleted_at')
                ->whereBetween('e.entry_date', [$from, $to])->exists();
            if (! $moved) {
                continue;
            }
            $last = $this->lastCompleted($a->id);
            if (! $last || $last->statement_date->toDateString() < $to) {
                $out[] = [$a->account_code.' '.Preview::accountName($a), $last ? $last->statement_date->format('d/m/Y') : null];
            }
        }

        return $out;
    }

    /* ---- helpers -------------------------------------------------------- */

    protected function assertEditable(BankReconciliation $rec): void
    {
        if (! $rec->isEditable()) {
            throw new \RuntimeException(__('ledger_periodend.br_locked'));
        }
    }
}
