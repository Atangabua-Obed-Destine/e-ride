<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\AccountingSetting;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\DefaultAccountMapping;
use App\Ledger\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Does every POS document still line up with its posting? (paxhitestbed's
 * App\Services\Accounting\LedgerHealth, for one business of PosInnova.)
 *
 * Three records describe the same money: the POS document (a sale, a
 * purchase, a payment...), the transaction_mappings row that says what it
 * posted, and the journal entry. LedgerSync keeps them in step; this asks the
 * questions an auditor would ask and reports what it finds, worst first.
 *
 * Read-only. The one repair it points to is "Sync now" (LedgerSync::sweep),
 * which does what the scheduler does every few minutes.
 *
 * Every query is limited to the current business (Ledger::businessId()): the
 * raw DB::table() queries here are not covered by BelongsToBusiness.
 */
class LedgerHealth
{
    public const OK = 'ok';

    public const WARNING = 'warning';

    public const CRITICAL = 'critical';

    public const GROUPS = ['mappings', 'entries', 'agreement', 'placement'];

    /** Rows listed under one finding (the count is always the full count). */
    private const MAX_ROWS = 200;

    private $bid;

    private $postFrom;

    public function __construct()
    {
        $this->bid = Ledger::businessId();
        $this->postFrom = AccountingSetting::getValue('post_from_date');
    }

    /** Every check, worst first. */
    public function checks(): array
    {
        $checks = array_merge(
            $this->mappingChecks(),
            $this->entryChecks(),
            $this->agreementChecks(),
            $this->placementChecks()
        );
        $rank = [self::CRITICAL => 0, self::WARNING => 1, self::OK => 2];
        $order = array_flip(self::GROUPS);
        usort($checks, fn ($a, $b) => [$rank[$a['state']], $order[$a['group']]] <=> [$rank[$b['state']], $order[$b['group']]]);

        return $checks;
    }

    public function worstState(array $checks): string
    {
        foreach ([self::CRITICAL, self::WARNING] as $state) {
            foreach ($checks as $c) {
                if ($c['state'] === $state) {
                    return $state;
                }
            }
        }

        return self::OK;
    }

    public function concerns(array $checks): int
    {
        return count(array_filter($checks, fn ($c) => $c['state'] !== self::OK));
    }

    /** The facts at the top of the screen: books start, last sync, opening entry, scale. */
    public function status(): array
    {
        $last = AccountingSetting::getValue('last_sweep_at');
        $openingId = AccountingSetting::getValue('opening_entry_id');
        $opening = $openingId ? JournalEntry::find((int) $openingId) : null;

        return [
            'post_from' => $this->postFrom,
            'last_sweep_at' => $last ? Carbon::parse($last) : null,
            'opening_id' => $openingId,
            'opening' => $opening,
            'mappings' => DB::table('transaction_mappings')->where('business_id', $this->bid)->where('status', 'posted')->count(),
            'entries' => DB::table('journal_entries')->where('business_id', $this->bid)->whereNull('deleted_at')->count(),
            'live' => DB::table('journal_entries')->where('business_id', $this->bid)->whereNull('deleted_at')
                ->where('is_posted', 1)->where('is_reversed', 0)->count(),
        ];
    }

    // ===================================================================== A. mappings

    private function mappingChecks(): array
    {
        $b = $this->bid;
        $out = [];

        // Failed postings, with the reason LedgerSync recorded
        $failed = DB::table('transaction_mappings')->where('business_id', $b)->where('status', 'failed')
            ->orderByDesc('updated_at')->get(['id', 'transaction_type', 'transaction_id', 'error']);
        $docs = $this->documents($failed);
        $rows = $failed->take(self::MAX_ROWS)->map(function ($m) use ($docs) {
            $d = $docs[$m->transaction_type.':'.$m->transaction_id] ?? null;

            return [
                'document' => $this->docCell($m->transaction_type, $m->transaction_id, $d),
                'date' => $d ? $this->date($d->date) : '',
                'amount' => $d->amount ?? null,
                'reason' => $m->error,
            ];
        })->all();
        $out[] = $this->check('failed', 'mappings', $failed->count(),
            array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $rows)), $rows, self::CRITICAL);

        // Marked posted, but the entry is missing (or was deleted)
        $dangling = DB::table('transaction_mappings as m')->where('m.business_id', $b)->where('m.status', 'posted')
            ->leftJoin('journal_entries as j', function ($j) {
                $j->on('j.id', '=', 'm.journal_entry_id')->whereNull('j.deleted_at');
            })->whereNull('j.id')
            ->get(['m.transaction_type', 'm.transaction_id', 'm.transaction_date', 'm.amount']);
        $out[] = $this->check('mapping_no_entry', 'mappings', $dangling->count(), (float) $dangling->sum('amount'),
            $this->mappingRows($dangling), self::CRITICAL);

        // Marked posted, pointing at an entry that is reversed or no longer posted
        $atReversed = DB::table('transaction_mappings as m')->where('m.business_id', $b)->where('m.status', 'posted')
            ->join('journal_entries as j', 'j.id', '=', 'm.journal_entry_id')->whereNull('j.deleted_at')
            ->where(function ($w) {
                $w->where('j.is_reversed', 1)->orWhere('j.is_posted', 0);
            })
            ->get(['m.transaction_type', 'm.transaction_id', 'm.transaction_date', 'm.amount', 'j.id as entry_id', 'j.entry_number']);
        $out[] = $this->check('mapping_at_reversed', 'mappings', $atReversed->count(), (float) $atReversed->sum('amount'),
            $this->mappingRows($atReversed), self::CRITICAL);

        // The document is gone, its entry still counts
        $goneDocs = DB::table('transaction_mappings as m')->where('m.business_id', $b)->where('m.status', 'posted')
            ->whereNotIn('m.transaction_type', ['payment', 'account_transaction'])
            ->leftJoin('transactions as t', 't.id', '=', 'm.transaction_id')->whereNull('t.id')
            ->get(['m.transaction_type', 'm.transaction_id', 'm.transaction_date', 'm.amount', 'm.journal_entry_id as entry_id']);
        $gonePays = DB::table('transaction_mappings as m')->where('m.business_id', $b)->where('m.status', 'posted')
            ->where('m.transaction_type', 'payment')
            ->leftJoin('transaction_payments as p', 'p.id', '=', 'm.transaction_id')->whereNull('p.id')
            ->get(['m.transaction_type', 'm.transaction_id', 'm.transaction_date', 'm.amount', 'm.journal_entry_id as entry_id']);
        $goneMoves = DB::table('transaction_mappings as m')->where('m.business_id', $b)->where('m.status', 'posted')
            ->where('m.transaction_type', 'account_transaction')
            ->leftJoin('account_transactions as at', 'at.id', '=', 'm.transaction_id')->whereNull('at.id')
            ->get(['m.transaction_type', 'm.transaction_id', 'm.transaction_date', 'm.amount', 'm.journal_entry_id as entry_id']);
        $orphans = $goneDocs->concat($gonePays)->concat($goneMoves);
        $out[] = $this->check('mapping_orphan', 'mappings', $orphans->count(), (float) $orphans->sum('amount'),
            $this->mappingRows($orphans, false), self::CRITICAL);

        // Mappings the posting rules need and cannot find
        $missing = $this->missingMappings();
        $out[] = $this->check('missing_mapping', 'mappings', count($missing), 0.0, $missing, self::WARNING);

        return $out;
    }

    /** Each mapping the posting rules read, with no active row or no postable account; and the fixed accounts they use. */
    private function missingMappings(): array
    {
        $postable = ChartOfAccount::postable()->pluck('id')->flip();
        $rows = DefaultAccountMapping::where('status', 1)->get()->keyBy(fn ($m) => $m->mapping_type.'|'.$m->category_key);
        $need = [];
        foreach (['customers', 'sale_goods', 'sale_services', 'sale_other', 'vat_collected', 'customer_advances',
            'suppliers', 'purchases', 'purchase_costs', 'vat_deductible', 'expense_category', 'expense_payable', 'opening_balance'] as $type) {
            $need[] = [$type, ''];
        }
        foreach (array_keys(self::paymentMethods()) as $method) {
            $need[] = ['payment_method', $method];
        }
        $methods = self::paymentMethods();
        $out = [];
        foreach ($need as [$type, $key]) {
            $row = $rows->get($type.'|'.$key) ?: ($type === 'payment_method' ? null : $rows->get($type.'|'));
            $acc = $row ? ($row->debit_account_id ?: $row->credit_account_id) : null;
            if ($acc && isset($postable[$acc])) {
                continue;
            }
            $out[] = [
                'mapping' => __('ledger.map_'.$type).($type === 'payment_method' ? ': '.($methods[$key] ?? $key) : ''),
                'problem' => $row ? __('ledger_map.h_missing_not_postable') : __('ledger_map.h_missing_none'),
            ];
        }

        return $out;
    }

    // ===================================================================== B. entries

    private function entryChecks(): array
    {
        $b = $this->bid;
        $out = [];
        $base = fn () => DB::table('journal_entries as j')->where('j.business_id', $b)->whereNull('j.deleted_at');

        $noLines = $base()->whereNotExists(function ($q) {
            $q->select(DB::raw(1))->from('journal_entry_lines as l')->whereColumn('l.journal_entry_id', 'j.id');
        })->get(['j.id', 'j.entry_number', 'j.entry_date', 'j.total_debit']);
        $out[] = $this->check('entry_no_lines', 'entries', $noLines->count(), (float) $noLines->sum('total_debit'),
            $this->entryRows($noLines, ['total_debit' => 'amount']), self::CRITICAL);

        $unbalanced = $base()->where('j.is_posted', 1)
            ->leftJoin('journal_entry_lines as l', 'l.journal_entry_id', '=', 'j.id')
            ->groupBy('j.id', 'j.entry_number', 'j.entry_date')
            ->havingRaw('ABS(COALESCE(SUM(l.debit),0) - COALESCE(SUM(l.credit),0)) > 0.009')
            ->get(['j.id', 'j.entry_number', 'j.entry_date', DB::raw('COALESCE(SUM(l.debit),0) as debit'), DB::raw('COALESCE(SUM(l.credit),0) as credit')]);
        $out[] = $this->check('entry_unbalanced', 'entries', $unbalanced->count(), 0.0,
            $this->entryRows($unbalanced, ['debit' => 'debit', 'credit' => 'credit']), self::CRITICAL);

        $drift = $base()->leftJoin('journal_entry_lines as l', 'l.journal_entry_id', '=', 'j.id')
            ->groupBy('j.id', 'j.entry_number', 'j.entry_date', 'j.total_debit')
            ->havingRaw('ABS(COALESCE(SUM(l.debit),0) - j.total_debit) > 0.009')
            ->get(['j.id', 'j.entry_number', 'j.entry_date', 'j.total_debit', DB::raw('COALESCE(SUM(l.debit),0) as line_total')]);
        $out[] = $this->check('entry_header_drift', 'entries', $drift->count(), 0.0,
            $this->entryRows($drift, ['total_debit' => 'header_total', 'line_total' => 'line_total']), self::CRITICAL);

        $noReversal = $base()->where('j.is_reversed', 1)->whereNotExists(function ($q) {
            $q->select(DB::raw(1))->from('journal_entries as r')->whereColumn('r.reversed_entry_id', 'j.id')->whereNull('r.deleted_at');
        })->get(['j.id', 'j.entry_number', 'j.entry_date', 'j.total_debit']);
        $out[] = $this->check('reversed_without_reversal', 'entries', $noReversal->count(), (float) $noReversal->sum('total_debit'),
            $this->entryRows($noReversal, ['total_debit' => 'amount']), self::CRITICAL);

        // One document or payment, more than one live entry
        $dupes = DB::table('journal_entries')->where('business_id', $b)->whereNull('deleted_at')
            ->whereIn('reference_type', ['transaction', 'payment'])->where('is_posted', 1)->where('is_reversed', 0)
            ->groupBy('reference_type', 'reference_id')->havingRaw('COUNT(*) > 1')
            ->get(['reference_type', 'reference_id', DB::raw('COUNT(*) as n'), DB::raw('SUM(total_debit) as total')]);
        $dupRows = [];
        foreach ($dupes->take(self::MAX_ROWS) as $d) {
            $type = $d->reference_type === 'payment' ? 'payment' : (string) DB::table('transactions')->where('id', $d->reference_id)->value('type');
            $dupRows[] = ['document' => $this->docCell($type ?: 'sell', (int) $d->reference_id, null), 'entries' => (int) $d->n, 'amount' => (float) $d->total];
        }
        $out[] = $this->check('entry_duplicate', 'entries', $dupes->count(), (float) $dupes->sum('total'), $dupRows, self::CRITICAL);

        // Debits equal credits over every posted entry
        $tot = DB::table('journal_entry_lines as l')->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->where('j.business_id', $b)->whereNull('j.deleted_at')->where('j.is_posted', 1)
            ->selectRaw('COALESCE(SUM(l.debit),0) as d, COALESCE(SUM(l.credit),0) as c')->first();
        $off = abs((float) $tot->d - (float) $tot->c) > 0.009;
        $out[] = $this->check('books_unbalanced', 'entries', $off ? 1 : 0, $off ? abs((float) $tot->d - (float) $tot->c) : 0.0,
            $off ? [['debit' => (float) $tot->d, 'credit' => (float) $tot->c, 'difference' => round((float) $tot->d - (float) $tot->c, 2)]] : [], self::CRITICAL);

        // The running balance kept on each account vs its posted lines
        $sums = DB::table('journal_entry_lines as l')->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->where('j.business_id', $b)->whereNull('j.deleted_at')->where('j.is_posted', 1)
            ->groupBy('l.account_id')->selectRaw('l.account_id, SUM(l.debit) - SUM(l.credit) as net')->pluck('net', 'account_id');
        $balRows = [];
        $balValue = 0.0;
        foreach (ChartOfAccount::orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_name_fr', 'normal_balance', 'current_balance']) as $a) {
            $net = (float) ($sums[$a->id] ?? 0);
            $lines = round($a->normal_balance === 'credit' ? -$net : $net, 2);
            $diff = round((float) $a->current_balance - $lines, 2);
            if (abs($diff) > 0.009) {
                $balValue += abs($diff);
                $balRows[] = ['account' => $a->account_code.' '.Preview::accountName($a), 'stored_balance' => (float) $a->current_balance,
                    'lines_balance' => $lines, 'difference' => $diff];
            }
        }
        $out[] = $this->check('balance_drift', 'entries', count($balRows), $balValue, array_slice($balRows, 0, self::MAX_ROWS), self::WARNING);

        return $out;
    }

    // ===================================================================== C. agreement

    private function agreementChecks(): array
    {
        $b = $this->bid;
        $out = [];

        // Documents and payments since the books started that were never posted
        $unposted = collect();
        if ($this->postFrom) {
            $docs = DB::table('transactions as t')->where('t.business_id', $b)->whereIn('t.type', PostingRules::TYPES)
                ->where('t.transaction_date', '>=', $this->postFrom)
                ->where(function ($w) {
                    $w->where(fn ($q) => $q->where('t.type', 'sell')->where('t.status', 'final'))
                        ->orWhere(fn ($q) => $q->where('t.type', 'purchase')->where('t.status', 'received'))
                        ->orWhereIn('t.type', ['sell_return', 'purchase_return', 'expense', 'opening_balance', 'ledger_discount']);
                })
                ->where('t.final_total', '!=', 0)
                ->whereNotExists(function ($q) use ($b) {
                    $q->select(DB::raw(1))->from('transaction_mappings as m')->where('m.business_id', $b)
                        ->whereColumn('m.transaction_type', 't.type')->whereColumn('m.transaction_id', 't.id');
                })->orderBy('t.transaction_date')
                ->get(['t.type', 't.id', 't.transaction_date as date', 't.final_total as amount', 't.invoice_no', 't.ref_no', 't.contact_id']);
            $pays = DB::table('transaction_payments as p')->where('p.business_id', $b)->where('p.paid_on', '>=', $this->postFrom)
                ->where('p.amount', '>', 0)
                ->whereNotExists(function ($q) use ($b) {
                    $q->select(DB::raw(1))->from('transaction_mappings as m')->where('m.business_id', $b)
                        ->where('m.transaction_type', 'payment')->whereColumn('m.transaction_id', 'p.id');
                })->orderBy('p.paid_on')
                ->get([DB::raw("'payment' as type"), 'p.id', 'p.paid_on as date', 'p.amount', DB::raw('p.payment_ref_no as invoice_no'), DB::raw('NULL as ref_no'), 'p.payment_for as contact_id']);
            $unposted = $docs->concat($pays);
        }
        $rows = $unposted->take(self::MAX_ROWS)->map(fn ($d) => [
            'document' => $this->docCell($d->type, (int) $d->id, $d), 'date' => $this->date($d->date), 'amount' => (float) $d->amount,
        ])->all();
        $out[] = $this->check('source_unposted', 'agreement', $unposted->count(), (float) $unposted->sum(fn ($d) => abs((float) $d->amount)), $rows, self::WARNING);

        // The entry says a different amount than the document
        $amountDrift = DB::table('transaction_mappings as m')->where('m.business_id', $b)->where('m.status', 'posted')
            ->where('m.transaction_type', '!=', 'payment')
            ->join('journal_entries as j', 'j.id', '=', 'm.journal_entry_id')->where('j.is_reversed', 0)->whereNull('j.deleted_at')
            ->join('transactions as t', function ($j) {
                $j->on('t.id', '=', 'm.transaction_id')->on('t.type', '=', 'm.transaction_type');
            })
            ->whereRaw('ABS(j.total_debit - ABS(t.final_total)) > 0.009')
            ->get(['m.transaction_type', 'm.transaction_id', 'j.id as entry_id', 'j.entry_number', 'j.total_debit', 't.final_total as doc_amount']);
        $payDrift = DB::table('transaction_mappings as m')->where('m.business_id', $b)->where('m.status', 'posted')
            ->where('m.transaction_type', 'payment')
            ->join('journal_entries as j', 'j.id', '=', 'm.journal_entry_id')->where('j.is_reversed', 0)->whereNull('j.deleted_at')
            ->join('transaction_payments as p', 'p.id', '=', 'm.transaction_id')
            ->whereRaw('ABS(j.total_debit - p.amount) > 0.009')
            ->get(['m.transaction_type', 'm.transaction_id', 'j.id as entry_id', 'j.entry_number', 'j.total_debit', 'p.amount as doc_amount']);
        $drift = $amountDrift->concat($payDrift);
        $rows = $drift->take(self::MAX_ROWS)->map(fn ($r) => [
            'document' => $this->docCell($r->transaction_type, (int) $r->transaction_id, null),
            'entry' => $this->entryCell($r->entry_id, $r->entry_number),
            'document_amount' => (float) $r->doc_amount, 'entry_amount' => (float) $r->total_debit,
        ])->all();
        $out[] = $this->check('amount_drift', 'agreement', $drift->count(),
            (float) $drift->sum(fn ($r) => abs(abs((float) $r->doc_amount) - (float) $r->total_debit)), $rows, self::WARNING);

        // The entry is dated differently from its document although the document's month is open
        $openMonth = function ($q, string $dateCol) use ($b) {
            $q->select(DB::raw(1))->from('accounting_periods as ap')->join('fiscal_years as fy', 'fy.id', '=', 'ap.fiscal_year_id')
                ->where('ap.business_id', $b)->where('ap.is_closed', 0)->where('fy.is_closed', 0)
                ->whereRaw("DATE($dateCol) BETWEEN ap.start_date AND ap.end_date");
        };
        $dateDocs = DB::table('transaction_mappings as m')->where('m.business_id', $b)->where('m.status', 'posted')
            ->where('m.transaction_type', '!=', 'payment')
            ->join('journal_entries as j', 'j.id', '=', 'm.journal_entry_id')->where('j.is_reversed', 0)->whereNull('j.deleted_at')
            ->join('transactions as t', function ($j) {
                $j->on('t.id', '=', 'm.transaction_id')->on('t.type', '=', 'm.transaction_type');
            })
            ->whereRaw('DATE(t.transaction_date) <> j.entry_date')
            ->whereExists(fn ($q) => $openMonth($q, 't.transaction_date'))
            ->get(['m.transaction_type', 'm.transaction_id', 'j.id as entry_id', 'j.entry_number', 'j.entry_date', 't.transaction_date as doc_date']);
        $datePays = DB::table('transaction_mappings as m')->where('m.business_id', $b)->where('m.status', 'posted')
            ->where('m.transaction_type', 'payment')
            ->join('journal_entries as j', 'j.id', '=', 'm.journal_entry_id')->where('j.is_reversed', 0)->whereNull('j.deleted_at')
            ->join('transaction_payments as p', 'p.id', '=', 'm.transaction_id')
            ->whereRaw('DATE(p.paid_on) <> j.entry_date')
            ->whereExists(fn ($q) => $openMonth($q, 'p.paid_on'))
            ->get(['m.transaction_type', 'm.transaction_id', 'j.id as entry_id', 'j.entry_number', 'j.entry_date', 'p.paid_on as doc_date']);
        $dates = $dateDocs->concat($datePays);
        $rows = $dates->take(self::MAX_ROWS)->map(fn ($r) => [
            'document' => $this->docCell($r->transaction_type, (int) $r->transaction_id, null),
            'entry' => $this->entryCell($r->entry_id, $r->entry_number),
            'document_date' => $this->date($r->doc_date), 'entry_date' => $this->date($r->entry_date),
        ])->all();
        $out[] = $this->check('date_drift', 'agreement', $dates->count(), 0.0, $rows, self::CRITICAL);

        // Customers (411) and suppliers (401) by contact, beside PosInnova's own figures
        foreach (['customers', 'suppliers'] as $side) {
            $rows = $this->tieOut($side);
            $out[] = $this->check($side.'_tie_out', 'agreement', count($rows),
                array_sum(array_map(fn ($r) => abs($r['difference']), $rows)), array_slice($rows, 0, self::MAX_ROWS), self::WARNING);
        }

        // The opening entry of the books
        $out[] = $this->openingCheck();

        // When the sync last ran
        $last = AccountingSetting::getValue('last_sweep_at');
        $stale = ! $last || Carbon::parse($last)->lt(Carbon::now()->subHour());
        $out[] = $this->check('sync_stale', 'agreement', $stale ? 1 : 0, 0.0, [], self::WARNING,
            $last ? __('ledger_map.h_sync_stale_detail_at', ['at' => Carbon::parse($last)->format('d/m/Y H:i')]) : __('ledger_map.h_sync_stale_detail_never'));

        return $out;
    }

    private function openingCheck(): array
    {
        if (! $this->postFrom) {
            return $this->check('opening_entry', 'agreement', 0, 0.0, [], self::WARNING);
        }
        $id = AccountingSetting::getValue('opening_entry_id');
        if ($id === null) {
            return $this->check('opening_entry', 'agreement', 1, 0.0, [], self::WARNING,
                __('ledger_map.h_opening_entry_missing', ['date' => $this->date($this->postFrom)]));
        }
        $skipped = (int) AccountingSetting::getValue('opening_skipped_count', 0);
        if ($skipped > 0) {
            // documents before the start date the rules could not read: left out of the opening balances
            $rows = [];
            foreach ((array) AccountingSetting::getValue('opening_skipped', []) as [$kind, $docId, $why]) {
                $rows[] = ['entry' => __('ledger.doc_'.($kind === 'payment' ? 'payment' : 'sell')).' #'.$docId, 'date' => '', 'amount' => 0.0, 'why' => $why];
            }

            return $this->check('opening_entry', 'agreement', $skipped, 0.0, $rows, self::WARNING,
                __('ledger.opening_skipped', ['n' => $skipped]));
        }
        if ((int) $id > 0) {
            $e = JournalEntry::find((int) $id);
            if (! $e || ! $e->is_posted || $e->is_reversed) {
                return $this->check('opening_entry', 'agreement', 1, $e ? (float) $e->total_debit : 0.0,
                    $e ? [['entry' => $this->entryCell($e->id, $e->entry_number), 'date' => $e->entry_date->format('d/m/Y'), 'amount' => (float) $e->total_debit]] : [],
                    self::WARNING, __('ledger_map.h_opening_entry_not_live'));
            }
        }

        return $this->check('opening_entry', 'agreement', 0, 0.0, [], self::WARNING);
    }

    /**
     * What each customer owes (or each supplier is owed) by PosInnova's documents and the
     * payments on them (the "due" of the contact screens), beside the contact's balance on 411
     * (401). Sales count once final and purchases once received, as the ledger posts them.
     * Advances stay out of both sides: the ledger keeps them on 4191 / 409, and PosInnova's
     * contacts.balance is not a reliable advance figure (imported businesses carry their old
     * dues there, see MigrateMqscs).
     */
    private function tieOut(string $side): array
    {
        $b = $this->bid;
        $pay = 'COALESCE((SELECT SUM(IF(p.is_return = 1, -p.amount, p.amount)) FROM transaction_payments p WHERE p.transaction_id = t.id), 0)';
        if ($side === 'customers') {
            $accounts = array_filter([DefaultAccount::id('customers')]);
            $sql = "SUM(CASE WHEN t.type = 'sell' AND t.status = 'final' THEN t.final_total ELSE 0 END)
                - SUM(CASE WHEN t.type = 'sell' THEN $pay ELSE 0 END)
                + SUM(CASE WHEN t.type = 'opening_balance' AND c.type <> 'supplier' THEN t.final_total - $pay ELSE 0 END)
                - SUM(CASE WHEN t.type = 'sell_return' THEN t.final_total - $pay ELSE 0 END)
                - SUM(CASE WHEN t.type = 'ledger_discount' AND c.type <> 'supplier' AND COALESCE(t.sub_type, '') <> 'purchase_discount' THEN t.final_total ELSE 0 END)";
            $types = ['sell', 'sell_return', 'opening_balance', 'ledger_discount'];
            $ledgerSign = 1;
        } else {
            $accounts = array_filter([DefaultAccount::id('suppliers')]);
            $sql = "SUM(CASE WHEN t.type = 'purchase' AND t.status = 'received' THEN t.final_total ELSE 0 END)
                - SUM(CASE WHEN t.type = 'purchase' THEN $pay ELSE 0 END)
                + SUM(CASE WHEN t.type = 'opening_balance' AND c.type = 'supplier' THEN t.final_total - $pay ELSE 0 END)
                + SUM(CASE WHEN t.type = 'expense' THEN t.final_total - $pay ELSE 0 END)
                - SUM(CASE WHEN t.type = 'purchase_return' THEN t.final_total - $pay ELSE 0 END)
                - SUM(CASE WHEN t.type = 'ledger_discount' AND (c.type = 'supplier' OR t.sub_type = 'purchase_discount') THEN t.final_total ELSE 0 END)";
            $types = ['purchase', 'purchase_return', 'opening_balance', 'expense', 'ledger_discount'];
            $ledgerSign = -1;
        }
        $books = DB::table('transactions as t')->join('contacts as c', 'c.id', '=', 't.contact_id')
            ->where('t.business_id', $b)->where('c.business_id', $b)->whereIn('t.type', $types)
            ->groupBy('t.contact_id')->selectRaw("t.contact_id, $sql as due")->pluck('due', 'contact_id');
        $ledger = empty($accounts) ? collect() : DB::table('journal_entry_lines as l')->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->where('j.business_id', $b)->whereNull('j.deleted_at')->where('j.is_posted', 1)
            ->whereIn('l.account_id', $accounts)->whereNotNull('l.contact_id')
            ->groupBy('l.contact_id')->selectRaw('l.contact_id, SUM(l.debit) - SUM(l.credit) as net')->pluck('net', 'contact_id');

        $ids = collect(array_keys($books->all()))->merge(array_keys($ledger->all()))->unique();
        $rows = [];
        foreach ($ids as $id) {
            $pos = round((float) ($books[$id] ?? 0), 2);
            $led = round($ledgerSign * (float) ($ledger[$id] ?? 0), 2);
            $diff = round($led - $pos, 2);
            if (abs($diff) >= 1) {
                $rows[$id] = ['contact' => null, 'posinnova' => $pos, 'ledger' => $led, 'difference' => $diff];
            }
        }
        if ($rows) {
            $names = DB::table('contacts')->where('business_id', $b)->whereIn('id', array_keys($rows))
                ->get(['id', 'name', 'supplier_business_name'])->keyBy('id');
            foreach ($rows as $id => &$r) {
                $c = $names[$id] ?? null;
                $r['contact'] = ['text' => $c ? ($c->supplier_business_name ?: $c->name) : '#'.$id, 'url' => url('/contacts/'.$id)];
            }
            unset($r);
            uasort($rows, fn ($x, $y) => abs($y['difference']) <=> abs($x['difference']));
        }

        return array_values($rows);
    }

    // ===================================================================== D. placement

    private function placementChecks(): array
    {
        $b = $this->bid;
        $out = [];
        $base = fn () => DB::table('journal_entries as j')->where('j.business_id', $b)->whereNull('j.deleted_at');

        $wrongYear = $base()->whereNotNull('j.fiscal_year_id')->whereNotExists(function ($q) {
            $q->select(DB::raw(1))->from('fiscal_years as f')->whereColumn('f.id', 'j.fiscal_year_id')
                ->whereColumn('f.start_date', '<=', 'j.entry_date')->whereColumn('f.end_date', '>=', 'j.entry_date');
        })->get(['j.id', 'j.entry_number', 'j.entry_date', 'j.total_debit']);
        $out[] = $this->check('wrong_exercice', 'placement', $wrongYear->count(), (float) $wrongYear->sum('total_debit'),
            $this->entryRows($wrongYear, ['total_debit' => 'amount']), self::CRITICAL);

        $wrongPeriod = $base()->whereNotNull('j.accounting_period_id')->whereNotExists(function ($q) {
            $q->select(DB::raw(1))->from('accounting_periods as p')->whereColumn('p.id', 'j.accounting_period_id')
                ->whereColumn('p.start_date', '<=', 'j.entry_date')->whereColumn('p.end_date', '>=', 'j.entry_date');
        })->get(['j.id', 'j.entry_number', 'j.entry_date', 'j.total_debit']);
        $out[] = $this->check('wrong_period', 'placement', $wrongPeriod->count(), 0.0,
            $this->entryRows($wrongPeriod, ['total_debit' => 'amount']), self::CRITICAL);

        $noYear = $base()->whereNull('j.fiscal_year_id')->get(['j.id', 'j.entry_number', 'j.entry_date', 'j.total_debit']);
        $out[] = $this->check('no_exercice', 'placement', $noYear->count(), (float) $noYear->sum('total_debit'),
            $this->entryRows($noYear, ['total_debit' => 'amount']), self::WARNING);

        $noPeriod = $base()->whereNull('j.accounting_period_id')->get(['j.id', 'j.entry_number', 'j.entry_date', 'j.total_debit']);
        $out[] = $this->check('no_period', 'placement', $noPeriod->count(), 0.0,
            $this->entryRows($noPeriod, ['total_debit' => 'amount']), self::WARNING);

        // Posted into a month after it was closed
        $closed = $base()->where('j.is_posted', 1)
            ->join('accounting_periods as p', function ($j) use ($b) {
                $j->on('j.entry_date', '>=', 'p.start_date')->on('j.entry_date', '<=', 'p.end_date')->where('p.business_id', $b);
            })
            ->where('p.is_closed', 1)->whereNotNull('p.closed_at')->whereColumn('j.posted_at', '>', 'p.closed_at')
            ->get(['j.id', 'j.entry_number', 'j.entry_date', 'j.total_debit']);
        $out[] = $this->check('posted_in_closed', 'placement', $closed->count(), (float) $closed->sum('total_debit'),
            $this->entryRows($closed, ['total_debit' => 'amount']), self::CRITICAL);

        // Drafts dated in a closed month: they can never be posted as they are
        $drafts = $base()->where('j.is_posted', 0)
            ->join('accounting_periods as p', function ($j) use ($b) {
                $j->on('j.entry_date', '>=', 'p.start_date')->on('j.entry_date', '<=', 'p.end_date')->where('p.business_id', $b);
            })
            ->where('p.is_closed', 1)
            ->get(['j.id', 'j.entry_number', 'j.entry_date', 'j.total_debit']);
        $out[] = $this->check('draft_in_closed', 'placement', $drafts->count(), (float) $drafts->sum('total_debit'),
            $this->entryRows($drafts, ['total_debit' => 'amount']), self::WARNING);

        return $out;
    }

    // ===================================================================== helpers

    /**
     * A check is OK when its count is zero; otherwise it carries the severity it was declared
     * with (paxhitestbed: severity is a property of the fault, not of how many there are).
     * Wording: ledger_map.h_<key>_label / _detail / _action.
     */
    private function check(string $key, string $group, int $count, float $value, array $rows, string $severity, ?string $detail = null): array
    {
        $action = 'ledger_map.h_'.$key.'_action';

        return [
            'key' => $key,
            'group' => $group,
            'label' => __('ledger_map.h_'.$key.'_label'),
            'state' => $count === 0 ? self::OK : $severity,
            'count' => $count,
            'value' => round($value, 2),
            'detail' => $count === 0 ? __('ledger_map.h_none') : ($detail ?: __('ledger_map.h_'.$key.'_detail')),
            'action' => $count === 0 || ! \Lang::has($action) ? null : __($action),
            'rows' => $count === 0 ? [] : $rows,
        ];
    }

    private function mappingRows($rows, bool $withEntry = true): array
    {
        return collect($rows)->take(self::MAX_ROWS)->map(function ($m) use ($withEntry) {
            $r = [
                'document' => $this->docCell($m->transaction_type, (int) $m->transaction_id, null),
                'date' => $m->transaction_date ? $this->date($m->transaction_date) : '',
                'amount' => (float) $m->amount,
            ];
            if (! empty($m->entry_id)) {
                $r['entry'] = $this->entryCell($m->entry_id, $m->entry_number ?? ('#'.$m->entry_id));
            }

            return $r;
        })->all();
    }

    /** @param  array  $money  column => label key of the money columns to carry */
    private function entryRows($rows, array $money): array
    {
        return collect($rows)->take(self::MAX_ROWS)->map(function ($e) use ($money) {
            $r = ['entry' => $this->entryCell($e->id, $e->entry_number), 'date' => $this->date($e->entry_date)];
            foreach ($money as $col => $label) {
                $r[$label] = (float) $e->$col;
            }

            return $r;
        })->all();
    }

    private function entryCell($id, $number): array
    {
        return ['text' => $number ?: '#'.$id, 'url' => route('ledger.journal-entries.show', $id)];
    }

    /** Document number, date and amount of transaction / payment mappings, in two queries. */
    private function documents($mappings): array
    {
        $out = [];
        $tx = collect($mappings)->whereNotIn('transaction_type', ['payment', 'account_transaction'])->pluck('transaction_id')->unique()->all();
        $pay = collect($mappings)->where('transaction_type', 'payment')->pluck('transaction_id')->unique()->all();
        if ($tx) {
            foreach (DB::table('transactions')->where('business_id', $this->bid)->whereIn('id', $tx)
                ->get(['id', 'type', 'invoice_no', 'ref_no', 'transaction_date as date', 'final_total as amount', 'contact_id']) as $t) {
                $out[$t->type.':'.$t->id] = $t;
            }
        }
        if ($pay) {
            foreach (DB::table('transaction_payments')->where('business_id', $this->bid)->whereIn('id', $pay)
                ->get(['id', 'payment_ref_no as invoice_no', DB::raw('NULL as ref_no'), 'paid_on as date', 'amount', 'payment_for as contact_id']) as $p) {
                $out['payment:'.$p->id] = $p;
            }
        }

        return $out;
    }

    private function docCell(string $type, int $id, $doc): array
    {
        $no = $doc ? ($doc->invoice_no ?: ($doc->ref_no ?? null)) : null;
        if ($type === 'account_transaction') {
            // a Payment Accounts movement: its own table, its own numbering
            $m = DB::table('account_transactions')->where('id', $id)->first(['sub_type', 'reff_no']);
            $kind = $m && $m->sub_type ? $m->sub_type : 'deposit';

            return ['text' => trim(__('ledger.doc_account_'.$kind).' '.(($m->reff_no ?? null) ?: '#'.$id)), 'url' => url('/account/account'), 'modal' => false];
        }
        if (! $doc) {
            $no = $type === 'payment'
                ? DB::table('transaction_payments')->where('id', $id)->value('payment_ref_no')
                : DB::table('transactions')->where('id', $id)->value(DB::raw('COALESCE(invoice_no, ref_no)'));
        }
        $link = self::documentLink($type, $id, $doc->contact_id ?? null);

        return ['text' => trim(__('ledger.doc_'.$type).' '.($no ?: '#'.$id)), 'url' => $link['url'] ?? null, 'modal' => $link['modal'] ?? false];
    }

    private function date($d): string
    {
        return $d ? Carbon::parse($d)->format('d/m/Y') : '';
    }

    // ===================================================================== shared with the screens

    /**
     * Where a POS document opens: sales, returns, purchases and payments in PosInnova's view
     * dialog (modal => true), an expense on its edit page, an opening balance or a discount on
     * its contact.
     */
    public static function documentLink(string $type, int $id, $contactId = null): ?array
    {
        switch ($type) {
            case 'sell': return ['url' => url('/sells/'.$id), 'modal' => true];
            case 'sell_return': return ['url' => url('/sell-return/'.$id), 'modal' => true];
            case 'purchase': return ['url' => url('/purchases/'.$id), 'modal' => true];
            case 'purchase_return': return ['url' => url('/purchase-return/'.$id), 'modal' => true];
            case 'payment': return ['url' => url('/payments/view-payment/'.$id), 'modal' => true];
            case 'expense': return ['url' => url('/expenses/'.$id.'/edit'), 'modal' => false];
            case 'opening_balance':
            case 'ledger_discount':
                return $contactId ? ['url' => url('/contacts/'.$contactId), 'modal' => false] : null;
        }

        return null;
    }

    /** The payment methods of the books (E-Ride: how bus tickets are paid, as the bus screens name them). */
    public static function paymentMethods(): array
    {
        return [
            'cash' => __('ledger.method_cash'),
            'momo' => __('ledger.method_momo'),
            'orange' => __('ledger.method_orange'),
            'fapshi' => __('ledger.method_fapshi'),
            'company' => __('ledger.method_company'),
        ];
    }
}
