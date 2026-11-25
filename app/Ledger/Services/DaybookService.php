<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\AccountingPeriod;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The cash analysis book, itemised (paxhitestbed App\Services\DaybookService).
 *
 * paxhitestbed builds its book from the school's own records (fees, incomes,
 * expenses, payroll) and puts each amount under a budget line. PosInnova's
 * book is the money in and out of each cash box: every POSTED ledger line on
 * a class-5 account (cash, bank, mobile money; 59 provisions excluded), with
 * the document behind it (the payment and its sale, purchase or expense; a
 * Payment Accounts transfer or deposit; a journal entry typed by hand).
 * E-Ride: the documents are the bus records (ticket money by channel, E-Ride's
 * statement paid) and the Income & Expense / Payment Accounts records; see documents().
 *
 * The analysis column is the counter-account: the account on the other side
 * of the entry (411 customers, 401 suppliers, 4718, 701, ...). When the other
 * side has several lines, the cash line is shared out over them in proportion
 * (`splits`), so the summary's figures add up to the book's.
 *
 * Read from the ledger and nothing else, so the book always agrees with the
 * general ledger of the same accounts: the debits of a cash box here are its
 * debits there. Like paxhitestbed, a correction (an entry and its reversal) is
 * two real rows on their own days, never netted away silently.
 *
 * Money posted against the opening-balance suspense account (4711) is
 * "unanalysed": it is in the cash box, but nobody has yet said what it was.
 * It is counted and flagged, never dropped.
 *
 * Every raw query here carries the business (Ledger::businessId()).
 */
class DaybookService
{
    /** Where a row's money sits in the book. */
    public const IN = 'in';

    public const OUT = 'out';

    /** Section of the Monthly Summary each counter-account falls in. */
    public const SECTIONS = ['income', 'expenditure', 'capital', 'transfers'];

    /** @var array<int, ChartOfAccount> */
    private $accountCache = [];

    /* ===================================================================
     |  Cash boxes
     |===================================================================*/

    /**
     * The cash boxes: class-5 detail accounts (less 59, provisions on
     * treasury), each with the names of the Payment Accounts linked to it.
     * A retired account that still carries money stays in the list.
     */
    public function cashAccounts(): Collection
    {
        $bid = Ledger::businessId();
        $withMoney = DB::table('journal_entry_lines')->where('business_id', $bid)->distinct()->pluck('account_id')->flip();
        // E-Ride: the Payment Accounts are paxhitestbed's payment_accounts (title), not POS accounts (name)
        $links = DB::table('payment_accounts')->where('business_id', $bid)->whereNotNull('chart_of_account_id')
            ->orderBy('title')->get(['title as name', 'chart_of_account_id'])->groupBy('chart_of_account_id');

        return ChartOfAccount::where('account_code', 'like', '5%')->where('account_code', 'not like', '59%')
            ->where('account_category', 'detail')
            ->orderBy('account_code')->get()
            ->filter(fn ($a) => $a->is_active || isset($withMoney[$a->id]))
            ->map(function ($a) use ($links) {
                $a->payment_accounts = isset($links[$a->id]) ? $links[$a->id]->pluck('name')->all() : [];
                $a->label = $a->account_code.' '.Preview::accountName($a);

                return $a;
            })->values();
    }

    /** The ids of the cash boxes a request is about: one, or all of them. */
    public function cashIds(?int $accountId = null): array
    {
        $all = $this->cashAccounts()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($accountId && in_array($accountId, $all, true)) {
            return [$accountId];
        }

        return $all;
    }

    /**
     * Opening, money in, money out and closing of each cash box over a range
     * (FinancialStatements, so the figures are the general ledger's).
     *
     * @return array<int, array{account: ChartOfAccount, opening: float, in: float, out: float, closing: float}>
     */
    public function balances(string $from, string $to, array $ids): array
    {
        $boxes = $this->cashAccounts()->keyBy('id');
        $out = [];
        foreach (app(FinancialStatements::class)->accounts($from, $to) as $a) {
            if (! in_array((int) $a->id, $ids, true) || ! $boxes->has($a->id)) {
                continue;
            }
            $out[(int) $a->id] = [
                'account' => $boxes->get($a->id),
                'opening' => (float) $a->opening,
                'in' => (float) $a->debit,
                'out' => (float) $a->credit,
                'closing' => (float) $a->closing,
            ];
        }

        return $out;
    }

    /* ===================================================================
     |  The book
     |===================================================================*/

    /**
     * Every cash movement in a range, in date order.
     *
     * @return array<int, array{
     *     date: string, ref: string, entry_number: string, entry_id: int, description: string,
     *     direction: string, amount: float, line_id: ?int, splits: array<int, float>,
     *     cash_account_id: int, source: string, source_id: ?int, is_reversal: bool, link: ?array
     * }>
     */
    public function rows(?string $from, ?string $to, ?array $cashIds = null): array
    {
        $bid = Ledger::businessId();
        $cashIds = $cashIds ?? $this->cashIds();
        if (! $cashIds) {
            return [];
        }

        $lines = DB::table('journal_entry_lines as l')
            ->join('journal_entries as je', 'je.id', '=', 'l.journal_entry_id')
            ->where('l.business_id', $bid)->where('je.business_id', $bid)
            ->where('je.is_posted', 1)->whereNull('je.deleted_at')
            ->whereIn('l.account_id', $cashIds)
            ->when($from, fn ($q) => $q->where('je.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('je.entry_date', '<=', $to))
            ->orderBy('je.entry_date')->orderBy('je.id')->orderBy('l.line_number')->orderBy('l.id')
            ->get(['l.id', 'l.account_id', 'l.debit', 'l.credit', 'l.description as line_description',
                'je.id as entry_id', 'je.entry_number', 'je.entry_date', 'je.description', 'je.reference_type',
                'je.reference_id', 'je.reference_number', 'je.journal_type', 'je.is_system_generated', 'je.reversed_entry_id']);
        if ($lines->isEmpty()) {
            return [];
        }

        // The other side of each entry
        $entryIds = $lines->pluck('entry_id')->unique()->values();
        $others = [];
        foreach ($entryIds->chunk(1000) as $chunk) {
            DB::table('journal_entry_lines')->where('business_id', $bid)->whereIn('journal_entry_id', $chunk->all())
                ->get(['id', 'journal_entry_id', 'account_id', 'debit', 'credit'])
                ->each(function ($l) use (&$others) {
                    $others[$l->journal_entry_id][] = $l;
                });
        }

        $docs = $this->documents($lines);
        $suspense = DefaultAccount::id('opening_balance');

        $rows = [];
        foreach ($lines as $l) {
            $debit = round((float) $l->debit, 2);
            $credit = round((float) $l->credit, 2);
            $amount = round($debit - $credit, 2);
            if ($amount == 0.0) {
                continue;
            }
            $direction = $amount > 0 ? self::IN : self::OUT;
            $amount = abs($amount);

            // money in: the other side is the credit lines; money out: the debit lines
            $side = [];
            foreach ($others[$l->entry_id] ?? [] as $o) {
                if ((int) $o->id === (int) $l->id) {
                    continue;
                }
                $v = $direction === self::IN ? (float) $o->credit - (float) $o->debit : (float) $o->debit - (float) $o->credit;
                if ($v > 0.004) {
                    $side[(int) $o->account_id] = ($side[(int) $o->account_id] ?? 0) + $v;
                }
            }
            $splits = $this->share($amount, $side);
            arsort($side);
            $main = $side ? (int) array_key_first($side) : null;

            $doc = $docs[$l->entry_id] ?? null;
            $rows[] = [
                'date' => substr((string) $l->entry_date, 0, 10),
                'ref' => (string) (($doc['ref'] ?? null) ?: ($l->reference_number ?: $l->entry_number)),
                'entry_number' => (string) $l->entry_number,
                'entry_id' => (int) $l->entry_id,
                'description' => (string) (($doc['description'] ?? null) ?: ($l->description ?: $l->line_description)),
                'direction' => $direction,
                'amount' => $amount,
                'line_id' => $main,
                'splits' => $splits,
                'cash_account_id' => (int) $l->account_id,
                'source' => $doc['source'] ?? ((int) $l->is_system_generated ? 'other' : 'manual'),
                'source_id' => $doc['source_id'] ?? null,
                'is_reversal' => ! empty($l->reversed_entry_id),
                'is_transfer' => $main !== null && in_array($main, $cashIds, true),
                'unanalysed' => $main === null || ($suspense && $main === (int) $suspense),
                'link' => $doc['link'] ?? null,
            ];
        }

        usort($rows, fn ($a, $b) => [$a['date'], $a['entry_id']] <=> [$b['date'], $b['entry_id']]);

        return $rows;
    }

    /**
     * The cash line shared out over the accounts on the other side, in
     * proportion, the last one taking the rounding.
     *
     * @return array<int, float>
     */
    private function share(float $amount, array $side): array
    {
        $total = array_sum($side);
        if ($total <= 0) {
            return [];
        }
        $out = [];
        $left = $amount;
        $keys = array_keys($side);
        foreach ($keys as $i => $acc) {
            $v = $i === count($keys) - 1 ? round($left, 2) : round($amount * $side[$acc] / $total, 2);
            $out[$acc] = $v;
            $left -= $v;
        }

        return $out;
    }

    /**
     * The document behind each entry: what to call it, its reference and where
     * to open it. A reversal is described by the entry it reverses.
     *
     * @return array<int, array{source: string, source_id: ?int, ref: ?string, description: ?string, link: ?array}>
     */
    private function documents(Collection $lines): array
    {
        $bid = Ledger::businessId();
        $entries = $lines->unique('entry_id')->keyBy('entry_id');

        // a reversal points at its original: read the document from there
        $originalIds = $entries->pluck('reversed_entry_id')->filter()->unique()->values();
        $originals = $originalIds->isEmpty() ? collect() : DB::table('journal_entries')->where('business_id', $bid)
            ->whereIn('id', $originalIds->all())->get(['id', 'reference_type', 'reference_id', 'journal_type', 'is_system_generated'])->keyBy('id');
        $origin = function ($e) use ($originals) {
            return $e->reversed_entry_id && $originals->has($e->reversed_entry_id) ? $originals->get($e->reversed_entry_id) : $e;
        };

        // E-Ride: an automatic entry's reference is its mapping row (reference_type = the record kind,
        // reference_id = the mapping's id), and the records behind it are the bus money moves, E-Ride's
        // statement and paxhitestbed's Income & Expense / Payment Accounts, where posinnova read POS
        // payments, sales, purchases and account transactions. The book itself is unchanged.
        $mapIds = [];
        foreach ($entries as $e) {
            $o = $origin($e);
            if ($o->reference_id && in_array($o->reference_type, PostingRules::TYPES, true)) {
                $mapIds[] = (int) $o->reference_id;
            }
        }
        $maps = collect();
        foreach (array_chunk(array_unique($mapIds), 1000) as $chunk) {
            $maps = $maps->merge(DB::table('transaction_mappings')->where('business_id', $bid)->whereIn('id', $chunk)
                ->get(['id', 'transaction_type', 'transaction_id']));
        }
        $maps = $maps->keyBy('id');
        $ids = fn (string $kind) => $maps->where('transaction_type', $kind)->pluck('transaction_id')->unique()->values()->all();
        $load = fn (string $kind, callable $query) => ($list = $ids($kind)) ? $query($list)->keyBy('id') : collect();

        $moves = $load(PostingRules::MOVE, fn ($list) => DB::table('bus_money_moves as m')->leftJoin('bus_bookings as b', 'b.id', '=', 'm.booking_id')
            ->whereIn('m.id', $list)->get(['m.id', 'm.kind', 'm.channel', 'm.method', 'b.code']));
        $statements = collect();
        foreach ([PostingRules::STATEMENT, PostingRules::STATEMENT_PAID] as $k) {
            $statements = $statements->merge($load($k, fn ($list) => DB::table('bus_statements')->whereIn('id', $list)->get(['id', 'reference', 'month'])));
        }
        $statements = $statements->keyBy('id');
        $incomes = $load(PostingRules::INCOME, fn ($list) => DB::table('incomes as r')->leftJoin('income_categories as c', 'c.id', '=', 'r.category_id')
            ->where('r.business_id', $bid)->whereIn('r.id', $list)->get(['r.id', 'r.title', 'r.invoice_id', 'r.reference', 'c.name as category']));
        $expenses = $load(PostingRules::EXPENSE, fn ($list) => DB::table('expenses as r')->leftJoin('expense_categories as c', 'c.id', '=', 'r.category_id')
            ->where('r.business_id', $bid)->whereIn('r.id', $list)->get(['r.id', 'r.title', 'r.invoice_id', 'r.reference', 'c.name as category']));
        $bills = $load(PostingRules::SUPPLIER_BILL, fn ($list) => DB::table('supplier_bills as r')->leftJoin('item_suppliers as s', 's.id', '=', 'r.supplier_id')
            ->where('r.business_id', $bid)->whereIn('r.id', $list)->get(['r.id', 'r.number', 'r.supplier_reference as reference', 's.title as supplier']));
        $payments = $load(PostingRules::SUPPLIER_PAYMENT, fn ($list) => DB::table('supplier_payments as r')->leftJoin('item_suppliers as s', 's.id', '=', 'r.supplier_id')
            ->where('r.business_id', $bid)->whereIn('r.id', $list)->get(['r.id', 'r.number', 'r.reference', 's.title as supplier']));
        $transfers = $load(PostingRules::PAYMENT_TRANSFER, fn ($list) => DB::table('payment_account_transfers as r')
            ->leftJoin('payment_accounts as f', 'f.id', '=', 'r.from_account_id')->leftJoin('payment_accounts as t', 't.id', '=', 'r.to_account_id')
            ->where('r.business_id', $bid)->whereIn('r.id', $list)->get(['r.id', 'r.note', 'f.title as from_title', 't.title as to_title']));
        $accountTxns = $load(PostingRules::PAYMENT_ACCOUNT_TXN, fn ($list) => DB::table('payment_account_transactions as r')
            ->leftJoin('payment_accounts as a', 'a.id', '=', 'r.payment_account_id')
            ->where('r.business_id', $bid)->whereIn('r.id', $list)
            ->get(['r.id', 'r.transaction_type', 'r.reference_type', 'r.title', 'r.payment_reference', 'a.title as account_name']));

        $out = [];
        foreach ($entries as $entryId => $e) {
            $o = $origin($e);
            $map = in_array($o->reference_type, PostingRules::TYPES, true) ? $maps->get((int) $o->reference_id) : null;
            $doc = null;
            if ($map) {
                $kind = $map->transaction_type;
                $rid = $map->transaction_id;
                $dash = fn ($who, string $what) => trim(($who ? $who.' — ' : '').$what);
                switch ($kind) {
                    case PostingRules::MOVE:
                        // posinnova's sales -> ticket money, by channel (counter or app)
                        $mv = $moves->get((int) $rid);
                        $doc = ['source' => 'bus_move_'.(($mv->channel ?? null) === 'counter' ? 'counter' : 'app'), 'ref' => $mv->code ?? null, 'description' => null];
                        break;
                    case PostingRules::STATEMENT:
                    case PostingRules::STATEMENT_PAID:
                        $doc = ['source' => $kind, 'ref' => $statements->get($rid)->reference ?? null, 'description' => null];
                        break;
                    case PostingRules::INCOME:
                    case PostingRules::EXPENSE:
                        $r = ($kind === PostingRules::INCOME ? $incomes : $expenses)->get((int) $rid);
                        $doc = ['source' => $kind, 'ref' => $r ? ($r->invoice_id ?: $r->reference) : null,
                            'description' => $r ? $dash($r->category, (string) $r->title) : null];
                        break;
                    case PostingRules::SUPPLIER_BILL:
                    case PostingRules::SUPPLIER_PAYMENT:
                        $r = ($kind === PostingRules::SUPPLIER_BILL ? $bills : $payments)->get((int) $rid);
                        $doc = ['source' => $kind, 'ref' => $r ? ($r->reference ?: $r->number) : null,
                            'description' => $r ? $dash($r->supplier, trim(__('ledger.doc_'.$kind).' '.$r->number)) : null];
                        break;
                    case PostingRules::PAYMENT_TRANSFER:
                        $r = $transfers->get((int) $rid);
                        $doc = ['source' => 'account_fund_transfer', 'ref' => null,
                            'description' => $r ? trim(__('ledger.doc_account_fund_transfer').' — '.$r->from_title.' > '.$r->to_title.($r->note ? ' · '.mb_substr($r->note, 0, 80) : '')) : null];
                        break;
                    case PostingRules::PAYMENT_ACCOUNT_TXN:
                        $r = $accountTxns->get((int) $rid);
                        $sub = ! $r ? 'deposit' : ($r->reference_type === 'opening_balance' ? 'opening_balance' : ($r->transaction_type === 'debit' ? 'withdrawal' : 'deposit'));
                        $doc = ['source' => 'account_'.$sub, 'ref' => $r->payment_reference ?? null,
                            'description' => $r ? trim(__('ledger_daybook.src_account_'.$sub).' — '.$r->account_name.($r->title ? ' · '.mb_substr($r->title, 0, 80) : '')) : null];
                        break;
                    default: // a trip that left, E-Ride's statement: no money of their own, but named all the same
                        $doc = ['source' => $kind, 'ref' => null, 'description' => null];
                }
                $doc += ['source_id' => is_numeric($rid) ? (int) $rid : null, 'link' => LedgerHealth::documentLink($kind, $rid)];
            } elseif (! (int) $o->is_system_generated) {
                $doc = ['source' => 'manual', 'source_id' => (int) $entryId, 'ref' => null, 'description' => null, 'link' => null];
            }
            if ($doc) {
                $out[$entryId] = $doc;
            }
        }

        return $out;
    }

    /**
     * The same money, summed by counter-account: what came in and what went
     * out against each, from the splits (so it adds up to the book).
     *
     * @return array<int, array{in: float, out: float}>
     */
    public function totalsByAccount(array $rows, ?string $from = null, ?string $to = null): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (($from && $row['date'] < $from) || ($to && $row['date'] > $to)) {
                continue;
            }
            foreach ($row['splits'] as $acc => $v) {
                $out[$acc] = $out[$acc] ?? ['in' => 0.0, 'out' => 0.0];
                $out[$acc][$row['direction']] += $v;
            }
        }

        return $out;
    }

    /* ===================================================================
     |  The Monthly Summary
     |===================================================================*/

    /**
     * One month of the book, summarised the way paxhitestbed's workbook does
     * it: Monthly · BB Forward · Cumulative, one row per counter-account.
     *
     * paxhitestbed's Budget and Balance columns are by budget line, not by
     * counter-account: they come with the month against the Income &
     * Expenditure sheet (BudgetActualsService::monthAgainstBudget), shown
     * under this table. BB Forward is everything since the fiscal year opened
     * up to the end of the previous month, so the first month opens at zero.
     *
     * Sections: a counter-account in class 2 is capital expenditure, one in
     * class 5 a transfer between cash boxes; the others are income when, over
     * the whole year, more came in against them than went out, expenditure
     * otherwise (decided on the year so an account does not change section
     * from one month to the next). A row's figure is what its section says:
     * money in less money out for income and transfers, money out less money
     * in for expenditure, so a refund reduces the line it refunds.
     *
     * Headings are the two-digit SYSCOHADA accounts (41 Customers, 60
     * Purchases, ...), each carrying the sum of the accounts filed under it.
     */
    public function monthlySummary(FiscalYear $year, AccountingPeriod $period, array $cashIds): array
    {
        $yearStart = $year->start_date->toDateString();
        $yearEnd = $year->end_date->toDateString();
        $periodStart = $period->start_date->toDateString();
        $periodEnd = $period->end_date->toDateString();
        $priorEnd = Carbon::parse($periodStart)->subDay()->toDateString();

        $rows = $this->rows($yearStart, $yearEnd, $cashIds);
        $year_ = $this->totalsByAccount($rows);
        $monthly = $this->totalsByAccount($rows, $periodStart, $periodEnd);
        $brought = $priorEnd >= $yearStart ? $this->totalsByAccount($rows, $yearStart, $priorEnd) : [];

        $accounts = $this->accounts(array_keys($year_));
        $section = function (int $id) use ($accounts, $year_) {
            $code = (string) ($accounts[$id]->account_code ?? '');
            if ($code !== '' && $code[0] === '2') {
                return 'capital';
            }
            if ($code !== '' && $code[0] === '5') {
                return 'transfers';
            }

            return ($year_[$id]['in'] ?? 0) >= ($year_[$id]['out'] ?? 0) ? 'income' : 'expenditure';
        };
        $figure = function (array $t, string $sec) {
            return in_array($sec, ['income', 'transfers']) ? $t['in'] - $t['out'] : $t['out'] - $t['in'];
        };

        $totals = [];
        foreach (self::SECTIONS as $s) {
            $totals[$s] = ['monthly' => 0.0, 'brought' => 0.0, 'cumulative' => 0.0];
        }

        // the accounts that moved this year, grouped under their two-digit heading
        $groups = [];
        foreach (array_keys($year_) as $id) {
            $a = $accounts[$id] ?? null;
            $sec = $section($id);
            $m = round($figure($monthly[$id] ?? ['in' => 0, 'out' => 0], $sec), 2);
            $b = round($figure($brought[$id] ?? ['in' => 0, 'out' => 0], $sec), 2);
            $code = $a ? (string) $a->account_code : '#'.$id;
            $head = strlen($code) > 2 ? substr($code, 0, 2) : $code;
            $groups[$sec][$head][$code.'|'.$id] = [
                'id' => $id,
                'code' => $code,
                'name' => $a ? Preview::accountName($a) : '#'.$id,
                'is_header' => false,
                'monthly' => $m,
                'brought' => $b,
                'cumulative' => round($m + $b, 2),
            ];
            $totals[$sec]['monthly'] += $m;
            $totals[$sec]['brought'] += $b;
            $totals[$sec]['cumulative'] += $m + $b;
        }

        $headings = ChartOfAccount::whereIn('account_code', collect($groups)->flatMap(fn ($g) => array_keys($g))->unique()->values()->all())
            ->get()->keyBy('account_code');

        // Flattened in the order the screen draws it: heading, its lines, its subtotal
        $lines = [];
        foreach (self::SECTIONS as $sec) {
            $lines[$sec] = [];
            $bySection = $groups[$sec] ?? [];
            ksort($bySection, SORT_STRING);
            foreach ($bySection as $head => $children) {
                ksort($children, SORT_STRING);
                $children = array_values($children);
                // a heading earns its place when it gathers more than one line
                if (count($children) > 1) {
                    $h = $headings->get((string) $head);
                    $sum = fn ($k) => round(array_sum(array_column($children, $k)), 2);
                    $heading = [
                        'id' => null, 'code' => (string) $head,
                        'name' => $h ? Preview::accountName($h) : (string) $head,
                        'is_header' => true,
                        'monthly' => $sum('monthly'), 'brought' => $sum('brought'), 'cumulative' => $sum('cumulative'),
                    ];
                    $lines[$sec][] = $heading + ['kind' => 'header'];
                    foreach ($children as $c) {
                        $lines[$sec][] = $c + ['kind' => 'child'];
                    }
                    $lines[$sec][] = $heading + ['kind' => 'subtotal'];
                } else {
                    $lines[$sec][] = $children[0] + ['kind' => 'line'];
                }
            }
        }
        foreach ($totals as $s => $t) {
            $totals[$s] = array_map(fn ($v) => round($v, 2), $t);
        }

        // the same month by kind of document, and by cash box
        $byDocument = [];
        foreach ($rows as $row) {
            if ($row['date'] < $periodStart || $row['date'] > $periodEnd) {
                continue;
            }
            $k = $row['source'];
            $byDocument[$k] = $byDocument[$k] ?? ['in' => 0.0, 'out' => 0.0, 'count' => 0];
            $byDocument[$k][$row['direction']] += $row['amount'];
            $byDocument[$k]['count']++;
        }
        uasort($byDocument, fn ($a, $b) => ($b['in'] + $b['out']) <=> ($a['in'] + $a['out']));

        return [
            'period' => $period,
            'lines' => $lines,
            'totals' => $totals,
            'net' => [
                'monthly' => round($totals['income']['monthly'] - $totals['expenditure']['monthly'] - $totals['capital']['monthly'] + $totals['transfers']['monthly'], 2),
                'brought' => round($totals['income']['brought'] - $totals['expenditure']['brought'] - $totals['capital']['brought'] + $totals['transfers']['brought'], 2),
                'cumulative' => round($totals['income']['cumulative'] - $totals['expenditure']['cumulative'] - $totals['capital']['cumulative'] + $totals['transfers']['cumulative'], 2),
            ],
            'by_document' => $byDocument,
            'boxes' => $this->balances($periodStart, $periodEnd, $cashIds),
        ];
    }

    /**
     * The accounting periods of a fiscal year, in order.
     */
    public function periodsFor(FiscalYear $year): Collection
    {
        return AccountingPeriod::where('fiscal_year_id', $year->id)->orderBy('start_date')->get();
    }

    /* ===================================================================
     |  Analysis
     |===================================================================*/

    /**
     * The whole year read as a shape rather than a list (paxhitestbed's
     * analysis, over a fiscal year instead of a budget year).
     *
     * Every row is fetched once and bucketed by month.
     *
     * Transfers between the cash boxes being looked at are left out of it:
     * moving money from the till to the bank brings nothing in and spends
     * nothing, and counting both halves would inflate both sides.
     */
    public function analysis(FiscalYear $year, array $cashIds): array
    {
        $periods = $this->periodsFor($year);
        $from = $year->start_date->toDateString();
        $to = $year->end_date->toDateString();

        $rows = array_values(array_filter($this->rows($from, $to, $cashIds), fn ($r) => ! $r['is_transfer']));

        $months = [];
        foreach ($periods as $period) {
            $months[] = [
                'period' => $period,
                'label' => $this->shortMonth($period->start_date),
                'start' => $period->start_date->toDateString(),
                'end' => $period->end_date->toDateString(),
                'in' => 0.0,
                'out' => 0.0,
            ];
        }
        foreach ($rows as $row) {
            foreach ($months as $i => $month) {
                if ($row['date'] >= $month['start'] && $row['date'] <= $month['end']) {
                    $months[$i][$row['direction']] += $row['amount'];
                    break;
                }
            }
        }

        $cumIn = 0.0;
        $cumOut = 0.0;
        foreach ($months as $i => $month) {
            $cumIn += $month['in'];
            $cumOut += $month['out'];
            $months[$i]['net'] = $month['in'] - $month['out'];
            $months[$i]['cum_in'] = $cumIn;
            $months[$i]['cum_out'] = $cumOut;
        }

        // The opening balance of the cash boxes on the first day of the year
        $opening = 0.0;
        foreach ($this->balances($from, $from, $cashIds) as $b) {
            $opening += $b['opening'];
        }

        // Pacing against the Income & Expenditure sheet (the institutional
        // budget) that covers this year, as paxhitestbed paces against its
        // budget: what went out over the budget's days against what it allows
        // for expenditure and capital, the time gone measured on its dates.
        $budgetOut = 0.0;
        $budgetIn = 0.0;
        $spentInBudget = $cumOut;
        $budget = \App\Ledger\Models\Budget::where('is_institutional', true)->where('status', '!=', 'cancelled')
            ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)
            ->orderByRaw('(start_date <= ? AND end_date >= ?) DESC', [now()->toDateString(), now()->toDateString()])
            ->orderByDesc('start_date')->first();
        if ($budget) {
            $bySection = \App\Ledger\Models\BudgetAllocation::where('budget_allocations.budget_id', $budget->id)
                ->join('budget_lines as bl', 'bl.id', '=', 'budget_allocations.budget_line_id')
                ->where('bl.is_header', false)->groupBy('bl.section')
                ->selectRaw('bl.section, SUM(budget_allocations.allocated_amount) AS total')->pluck('total', 'section');
            $budgetOut = (float) ($bySection['expenditure'] ?? 0) + (float) ($bySection['capital'] ?? 0);
            $budgetIn = (float) ($bySection['income'] ?? 0);
            $bs = $budget->start_date->toDateString();
            $be = $budget->end_date->toDateString();
            $spentInBudget = array_sum(array_map(fn ($r) => $r['direction'] === self::OUT && $r['date'] >= $bs && $r['date'] <= $be ? $r['amount'] : 0, $rows));
        }

        return [
            'months' => $months,
            'pacing' => $this->pacing($year, $spentInBudget, $budgetOut, $budget),
            'budget_sheet' => $budget,
            'top_expenditure' => $this->topLines($rows, 10, self::OUT),
            'top_income' => $this->topLines($rows, 10, self::IN),
            'concentration' => [
                'income' => $this->concentration($rows, self::IN),
                'expenditure' => $this->concentration($rows, self::OUT),
            ],
            'cash_position' => $this->cashPosition($months, $opening),
            'largest_movements' => $this->largestMovements($rows),
            'movers' => $this->movers($rows, $months),
            'unallocated' => $this->unallocatedSummary($rows),
            'budget' => ['income' => $budgetIn, 'expenditure' => $budgetOut],
            'totals' => ['in' => $cumIn, 'out' => $cumOut, 'net' => $cumIn - $cumOut],
            'opening_balance' => $opening,
            'has_data' => $rows !== [],
            'rows' => $rows,
        ];
    }

    /**
     * Are we spending faster than the year is passing? (paxhitestbed.) The
     * time gone is measured on the budget's own dates when there is one; with
     * no budget, the status is 'none'.
     */
    protected function pacing(FiscalYear $year, float $spent, float $budgeted, $budget = null): array
    {
        $start = ($budget ? $budget->start_date : $year->start_date)->copy()->startOfDay()->getTimestamp();
        $end = ($budget ? $budget->end_date : $year->end_date)->copy()->endOfDay()->getTimestamp();
        $now = Carbon::now()->getTimestamp();

        $elapsed = $end > $start ? max(0.0, min(1.0, ($now - $start) / ($end - $start))) * 100 : 0.0;
        $consumed = $budgeted > 0 ? ($spent / $budgeted) * 100 : 0.0;
        $gap = $consumed - $elapsed;
        $projected = $elapsed > 0 ? $spent / ($elapsed / 100) : 0.0;

        if ($budgeted <= 0) {
            $status = 'none';
        } elseif ($gap > 15) {
            $status = 'critical';
        } elseif ($gap > 5) {
            $status = 'warning';
        } else {
            $status = 'good';
        }

        return [
            'elapsed' => $elapsed, 'consumed' => $consumed, 'gap' => $gap, 'status' => $status,
            'projected' => $projected, 'budgeted' => $budgeted, 'spent' => $spent,
        ];
    }

    /**
     * Where the money went — or where it came from — largest first, by
     * counter-account, each with its share of its own side's total.
     */
    protected function topLines(array $rows, int $limit, string $direction): array
    {
        $totals = [];
        $sideTotal = 0.0;
        foreach ($rows as $row) {
            if ($row['direction'] !== $direction) {
                continue;
            }
            $sideTotal += (float) $row['amount'];
            foreach ($row['splits'] as $acc => $v) {
                $totals[$acc] = ($totals[$acc] ?? 0) + $v;
            }
        }
        arsort($totals);
        $totals = array_slice($totals, 0, $limit, true);
        $accounts = $this->accounts(array_keys($totals));

        $out = [];
        foreach ($totals as $id => $amount) {
            $a = $accounts[$id] ?? null;
            $out[] = [
                'code' => $a->account_code ?? '',
                'name' => $a ? Preview::accountName($a) : '#'.$id,
                'amount' => (float) $amount,
                'share' => $sideTotal > 0 ? ((float) $amount / $sideTotal) * 100 : 0.0,
            ];
        }

        return $out;
    }

    /** How much of one side rests on its few biggest counter-accounts. */
    protected function concentration(array $rows, string $direction, int $top = 5): array
    {
        $totals = [];
        $sideTotal = 0.0;
        foreach ($rows as $row) {
            if ($row['direction'] !== $direction) {
                continue;
            }
            $sideTotal += (float) $row['amount'];
            foreach ($row['splits'] as $acc => $v) {
                $totals[$acc] = ($totals[$acc] ?? 0) + $v;
            }
        }
        arsort($totals);
        $topSum = array_sum(array_slice($totals, 0, $top, true));

        return [
            'top' => (float) $topSum,
            'total' => $sideTotal,
            'share' => $sideTotal > 0 ? ($topSum / $sideTotal) * 100 : 0.0,
            'count' => min($top, count($totals)),
        ];
    }

    /** The cash curve: where the balance stood at the end of each month. */
    protected function cashPosition(array $months, float $openingBalance): array
    {
        $balance = $openingBalance;
        $curve = [];
        foreach ($months as $month) {
            $balance += $month['net'];
            $curve[] = ['label' => $month['label'], 'end' => $month['end'], 'net' => (float) $month['net'], 'balance' => $balance];
        }

        return $curve;
    }

    /** The biggest single movements, either way. */
    protected function largestMovements(array $rows, int $limit = 10): array
    {
        usort($rows, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        $top = array_slice($rows, 0, $limit);
        $accounts = $this->accounts(array_filter(array_column($top, 'line_id')));

        $out = [];
        foreach ($top as $row) {
            $a = $row['line_id'] ? ($accounts[$row['line_id']] ?? null) : null;
            $out[] = [
                'date' => $row['date'],
                'ref' => $row['ref'],
                'description' => $row['description'],
                'direction' => $row['direction'],
                'amount' => (float) $row['amount'],
                'line' => $a && ! $row['unanalysed'] ? trim($a->account_code.' '.Preview::accountName($a)) : null,
            ];
        }

        return $out;
    }

    /**
     * What changed between the last two months with activity, by
     * counter-account (net of both directions), largest movement either way.
     */
    protected function movers(array $rows, array $months, int $limit = 8): array
    {
        $active = array_values(array_filter($months, fn ($m) => $m['in'] > 0 || $m['out'] > 0));
        if (count($active) < 2) {
            return [];
        }
        $current = $active[count($active) - 1];
        $previous = $active[count($active) - 2];

        $byLine = function (array $month) use ($rows) {
            $totals = [];
            foreach ($rows as $row) {
                if ($row['date'] >= $month['start'] && $row['date'] <= $month['end']) {
                    foreach ($row['splits'] as $acc => $v) {
                        $totals[$acc] = ($totals[$acc] ?? 0) + $v;
                    }
                }
            }

            return $totals;
        };
        $now = $byLine($current);
        $then = $byLine($previous);

        $changes = [];
        foreach (array_unique(array_merge(array_keys($now), array_keys($then))) as $id) {
            $changes[$id] = ($now[$id] ?? 0) - ($then[$id] ?? 0);
        }
        uasort($changes, fn ($a, $b) => abs($b) <=> abs($a));
        $changes = array_slice($changes, 0, $limit, true);
        $accounts = $this->accounts(array_keys($changes));

        $out = [];
        foreach ($changes as $id => $change) {
            $a = $accounts[$id] ?? null;
            $out[] = [
                'line' => $a ? trim($a->account_code.' '.Preview::accountName($a)) : '#'.$id,
                'previous' => (float) ($then[$id] ?? 0),
                'current' => (float) ($now[$id] ?? 0),
                'change' => (float) $change,
                'direction' => $change >= 0 ? 'up' : 'down',
            ];
        }

        return ['from' => $previous['label'], 'to' => $current['label'], 'rows' => $out];
    }

    /** The money on the suspense account, itemised enough to act on. */
    public function unallocatedSummary(array $rows): array
    {
        $amount = 0.0;
        $total = 0.0;
        $items = [];
        foreach ($rows as $row) {
            $total += (float) $row['amount'];
            if (! $row['unanalysed']) {
                continue;
            }
            $amount += (float) $row['amount'];
            $items[] = [
                'date' => $row['date'], 'ref' => $row['ref'], 'description' => $row['description'],
                'direction' => $row['direction'], 'amount' => (float) $row['amount'],
            ];
        }
        usort($items, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return [
            'amount' => $amount,
            'count' => count($items),
            'share' => $total > 0 ? ($amount / $total) * 100 : 0.0,
            'rows' => array_slice($items, 0, 15),
        ];
    }

    /** "January 2026" -> "Jan" (in the user's language), which is all a chart axis has room for. */
    protected function shortMonth($date): string
    {
        return rtrim(Carbon::parse($date)->locale(app()->getLocale())->isoFormat('MMM'), '.');
    }

    /* ===================================================================
     |  Helpers
     |===================================================================*/

    /** Chart accounts by id (retired ones included: their money is still money). */
    public function accounts(array $ids): array
    {
        $missing = array_values(array_diff(array_map('intval', $ids), array_keys($this->accountCache)));
        if ($missing) {
            foreach (ChartOfAccount::withTrashed()->whereIn('id', $missing)->get() as $a) {
                $this->accountCache[(int) $a->id] = $a;
            }
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($this->accountCache[(int) $id])) {
                $out[(int) $id] = $this->accountCache[(int) $id];
            }
        }

        return $out;
    }
}
