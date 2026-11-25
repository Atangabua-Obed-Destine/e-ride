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
 * Does every record still line up with its posting? (paxhitestbed's
 * App\Services\Accounting\LedgerHealth, as ported to posinnova; E-Ride: the
 * records are the bus records and the Income & Expense / Payment Accounts ones,
 * and the agreement checks are the bus tie-outs paxhitestbed asked for: ticket
 * money held per trip, each agent's till, and what is owed to suppliers and E-Ride.)
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

    /**
     * E-Ride: the checks that read only the ledger (postings that failed, the entries, where they
     * are filed). The others read posinnova's POS tables (transactions, transaction_payments...)
     * and wait for the bus version of the Ledger health screen; the year-end closing asks these
     * meanwhile (YearEndClosingService::checks).
     */
    public function ledgerChecks(): array
    {
        $failed = DB::table('transaction_mappings')->where('business_id', $this->bid)->where('status', 'failed')
            ->orderByDesc('updated_at')->get(['transaction_date', 'amount', 'error']);
        $rows = $failed->take(self::MAX_ROWS)->map(fn ($m) => ['date' => $this->date($m->transaction_date), 'amount' => (float) $m->amount, 'reason' => $m->error])->all();
        $checks = array_merge(
            [$this->check('failed', 'mappings', $failed->count(), (float) $failed->sum('amount'), $rows, self::CRITICAL)],
            $this->entryChecks(),
            $this->placementChecks()
        );
        $rank = [self::CRITICAL => 0, self::WARNING => 1, self::OK => 2];
        usort($checks, fn ($a, $b) => $rank[$a['state']] <=> $rank[$b['state']]);

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

        // The record is gone, its entry still counts (E-Ride: each kind's own table)
        $orphans = collect();
        foreach (self::SOURCES as $kind => [$table, $col]) {
            $orphans = $orphans->concat(DB::table('transaction_mappings as m')->where('m.business_id', $b)->where('m.status', 'posted')
                ->where('m.transaction_type', $kind)
                ->leftJoin($table.' as src', 'src.'.$col, '=', 'm.transaction_id')->whereNull('src.'.$col)
                ->get(['m.transaction_type', 'm.transaction_id', 'm.transaction_date', 'm.amount', 'm.journal_entry_id as entry_id']));
        }
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
        // E-Ride: what the bus records, tills and Income & Expense post to
        foreach (['tickets_held', 'ticket_sales', 'refunds_owed', 'refunds_after_trip', 'gateway_fees', 'eride_commission', 'eride_payable',
            'till_short', 'till_over', 'suppliers', 'vat_deductible', 'expense_category', 'opening_balance'] as $type) {
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

        // One record (its mapping), more than one live entry
        $dupes = DB::table('journal_entries')->where('business_id', $b)->whereNull('deleted_at')
            ->whereIn('reference_type', PostingRules::TYPES)->where('is_posted', 1)->where('is_reversed', 0)
            ->groupBy('reference_type', 'reference_id')->havingRaw('COUNT(*) > 1')
            ->get(['reference_type', 'reference_id', DB::raw('COUNT(*) as n'), DB::raw('SUM(total_debit) as total')]);
        $dupRows = [];
        foreach ($dupes->take(self::MAX_ROWS) as $d) {
            $map = DB::table('transaction_mappings')->where('id', $d->reference_id)->first(['transaction_type', 'transaction_id']);
            $dupRows[] = ['document' => $this->docCell($map->transaction_type ?? $d->reference_type, $map->transaction_id ?? $d->reference_id, null),
                'entries' => (int) $d->n, 'amount' => (float) $d->total];
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
        $agency = $this->agency();

        // Bus records since the books started that were never posted (money moves, trips that left)
        $unposted = collect();
        if ($this->postFrom && $agency) {
            $moves = DB::table('bus_money_moves as mv')->where('mv.agency_id', $agency)->whereNull('mv.skipped')
                ->where('mv.at', '>=', $this->postFrom)->where('mv.amount', '>', 0)
                ->whereNotExists(function ($q) use ($b) {
                    $q->select(DB::raw(1))->from('transaction_mappings as m')->where('m.business_id', $b)
                        ->where('m.transaction_type', PostingRules::MOVE)->whereColumn('m.transaction_id', 'mv.id');
                })->orderBy('mv.at')
                ->get([DB::raw("'".PostingRules::MOVE."' as type"), 'mv.id', 'mv.at as date', 'mv.amount']);
            $trips = DB::table('bus_departures as d')->where('d.agency_id', $agency)->where('d.status', \Modules\BusManagement\Entities\BusDeparture::DEPARTED)
                ->where('d.date', '>=', $this->postFrom)
                ->whereNotExists(function ($q) use ($b) {
                    $q->select(DB::raw(1))->from('transaction_mappings as m')->where('m.business_id', $b)
                        ->where('m.transaction_type', PostingRules::DEPARTURE)->whereColumn('m.transaction_id', 'd.id');
                })->orderBy('d.date')
                ->get([DB::raw("'".PostingRules::DEPARTURE."' as type"), 'd.id', 'd.date as date', DB::raw('0 as amount')]);
            $unposted = $moves->concat($trips);
        }
        $rows = $unposted->take(self::MAX_ROWS)->map(fn ($d) => [
            'document' => $this->docCell($d->type, $d->id, null), 'date' => $this->date($d->date), 'amount' => (float) $d->amount,
        ])->all();
        $out[] = $this->check('source_unposted', 'agreement', $unposted->count(), (float) $unposted->sum(fn ($d) => abs((float) $d->amount)), $rows, self::WARNING);

        // A money move's entry is dated differently from the move although its month is open
        $openMonth = function ($q, string $dateCol) use ($b) {
            $q->select(DB::raw(1))->from('accounting_periods as ap')->join('fiscal_years as fy', 'fy.id', '=', 'ap.fiscal_year_id')
                ->where('ap.business_id', $b)->where('ap.is_closed', 0)->where('fy.is_closed', 0)
                ->whereRaw("DATE($dateCol) BETWEEN ap.start_date AND ap.end_date");
        };
        $dates = collect();
        if ($agency) {
            $dates = DB::table('transaction_mappings as m')->where('m.business_id', $b)->where('m.status', 'posted')
                ->where('m.transaction_type', PostingRules::MOVE)
                ->join('journal_entries as j', 'j.id', '=', 'm.journal_entry_id')->where('j.is_reversed', 0)->whereNull('j.deleted_at')
                ->join('bus_money_moves as mv', 'mv.id', '=', 'm.transaction_id')
                ->whereRaw('DATE(mv.at) <> j.entry_date')
                ->whereExists(fn ($q) => $openMonth($q, 'mv.at'))
                ->get(['m.transaction_type', 'm.transaction_id', 'j.id as entry_id', 'j.entry_number', 'j.entry_date', 'mv.at as doc_date']);
        }
        $rows = $dates->take(self::MAX_ROWS)->map(fn ($r) => [
            'document' => $this->docCell($r->transaction_type, $r->transaction_id, null),
            'entry' => $this->entryCell($r->entry_id, $r->entry_number),
            'document_date' => $this->date($r->doc_date), 'entry_date' => $this->date($r->entry_date),
        ])->all();
        $out[] = $this->check('date_drift', 'agreement', $dates->count(), 0.0, $rows, self::CRITICAL);

        // The bus tie-outs: ticket money held per trip, each agent's till, what is owed to suppliers and E-Ride
        foreach (['tickets_held', 'tills', 'suppliers'] as $side) {
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
                $rows[] = ['entry' => __('ledger.doc_'.PostingRules::MOVE).' #'.$docId, 'date' => '', 'amount' => 0.0, 'why' => $why];
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
     * E-Ride's tie-outs, each comparing the ledger with an independent source (paxhitestbed's advice):
     *  - tickets_held: per trip, what 477 holds against the bus records (tickets paid less refunds
     *    and moves, for a trip that has not left; nothing once it has left);
     *  - tills: per counter agent, the till (5712 tagged with the agent) against their cash sales less
     *    cash refunds less what their counts took off, since the books started;
     *  - suppliers: 401 against the approved supplier bills still unpaid and E-Ride's statements not
     *    paid yet.
     * A difference of less than 1 FCFA is ignored. Rows: [contact, records, ledger, difference].
     */
    private function tieOut(string $side): array
    {
        $b = $this->bid;
        $agency = $this->agency();
        $ledgerBy = function (?int $account, string $col, ?callable $where = null) use ($b) {
            if (! $account) {
                return collect();
            }
            $q = DB::table('journal_entry_lines as l')->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
                ->where('j.business_id', $b)->whereNull('j.deleted_at')->where('j.is_posted', 1)->where('l.account_id', $account);
            if ($where) {
                $where($q);
            }

            return $q->groupBy('l.'.$col)->selectRaw('l.'.$col.' as k, SUM(l.debit) - SUM(l.credit) as net')->pluck('net', 'k');
        };
        $rows = [];
        $add = function ($key, $cell, float $records, float $ledger) use (&$rows) {
            $diff = round($ledger - $records, 2);
            if (abs($diff) >= 1) {
                $rows[$key] = ['contact' => $cell, 'records' => round($records, 2), 'ledger' => round($ledger, 2), 'difference' => $diff];
            }
        };

        if ($side === 'tickets_held' && $agency) {
            $held = DefaultAccount::id('tickets_held');
            $ledger = $ledgerBy($held, 'departure_id', fn ($q) => $q->whereNotNull('l.departure_id'));
            $rules = new PostingRules();
            $records = [];
            $moves = DB::table('bus_money_moves')->where('agency_id', $agency)->whereNull('skipped')
                ->whereIn('kind', ['sale', 'refund_owed', 'cash_refund', 'rebook'])->get(['kind', 'amount', 'departure_id', 'from_departure_id']);
            foreach ($moves as $m) {
                if ($m->kind === 'rebook') {
                    if ($m->departure_id === $m->from_departure_id) {
                        continue;
                    }
                    $records[(string) $m->departure_id] = ($records[(string) $m->departure_id] ?? 0) + (float) $m->amount;
                    $records[(string) $m->from_departure_id] = ($records[(string) $m->from_departure_id] ?? 0) - (float) $m->amount;
                } elseif ($m->departure_id) {
                    $records[(string) $m->departure_id] = ($records[(string) $m->departure_id] ?? 0) + ($m->kind === 'sale' ? 1 : -1) * (float) $m->amount;
                }
            }
            $ids = collect(array_keys($records))->merge($ledger->keys())->filter()->unique();
            $trips = \Modules\BusManagement\Entities\BusDeparture::with('destination')->whereIn('id', $ids)->get()->keyBy(fn ($d) => (string) $d->id);
            $start = $this->postFrom ? Carbon::parse($this->postFrom)->startOfDay() : null;
            foreach ($ids as $id) {
                $d = $trips[(string) $id] ?? null;
                $left = $rules->leftAt($d);
                if ($left && $start && $left->lt($start)) {
                    continue; // left before the books started: history
                }
                $expected = $left ? 0.0 : (float) ($records[(string) $id] ?? 0);
                $cell = ['text' => $d ? __('ledger.doc_'.PostingRules::DEPARTURE).' '.$d->leavesAt()->format('d/m H:i').' '.($d->destination?->city ?? '') : '#'.$id, 'url' => null];
                $add('t'.$id, $cell, $expected, -1 * (float) ($ledger[(string) $id] ?? 0));
            }
        } elseif ($side === 'tills' && $agency) {
            $till = DefaultAccount::id('payment_method', 'cash');
            $ledger = $ledgerBy($till, 'agent_id', fn ($q) => $q->whereNotNull('l.agent_id'));
            $from = $this->postFrom ?: '1970-01-01';
            $records = DB::table('bus_money_moves')->where('agency_id', $agency)->whereNull('skipped')->where('method', 'cash')
                ->whereNotNull('staff_user_id')->where('at', '>=', $from)
                ->groupBy('staff_user_id')->selectRaw("staff_user_id as k, SUM(CASE WHEN kind IN ('sale', 'extra') THEN amount WHEN kind = 'cash_refund' THEN -amount ELSE 0 END) as net")
                ->pluck('net', 'k')->all();
            if (\Illuminate\Support\Facades\Schema::hasTable('till_counts')) {
                foreach (DB::table('till_counts')->where('business_id', $b)->where('status', 'posted')->groupBy('agent_id')
                    ->selectRaw('agent_id as k, SUM(expected) as e')->pluck('e', 'k') as $agent => $cleared) {
                    $records[$agent] = ($records[$agent] ?? 0) - (float) $cleared;
                }
            }
            $ids = collect(array_keys($records))->merge($ledger->keys())->filter()->unique();
            $names = \App\Ledger\ERide\Tills::names($ids->all());
            foreach ($ids as $id) {
                $add('a'.$id, ['text' => $names[(string) $id] ?? (string) $id, 'url' => route('ledger.tills.index')],
                    (float) ($records[(string) $id] ?? 0), (float) ($ledger[(string) $id] ?? 0));
            }
        } elseif ($side === 'suppliers') {
            $accounts = array_values(array_unique(array_filter([DefaultAccount::id('suppliers'), DefaultAccount::id('eride_payable')])));
            $ledger = 0.0;
            foreach ($accounts as $acc) {
                $ledger += -1 * (float) $ledgerBy($acc, 'account_id')->sum();
            }
            $records = 0.0;
            if (class_exists(SupplierPayables::class) && \Illuminate\Support\Facades\Schema::hasTable('supplier_bills')) {
                try {
                    $records += (float) (new SupplierPayables())->owedQuery()->get()->sum(fn ($r) => (float) ($r->owed ?? $r->balance ?? 0));
                } catch (\Throwable $e) {
                    // the bills' own screens show their state
                }
            }
            if ($agency && in_array(DefaultAccount::id('eride_payable'), $accounts, true)) {
                $from = $this->postFrom ? Carbon::parse($this->postFrom)->startOfMonth()->toDateString() : '1970-01-01';
                $records += (float) DB::table('bus_statements')->where('agency_id', $agency)->where('total', '>', 0)
                    ->where('month', '>=', $from)->where('status', '!=', \Modules\BusManagement\Entities\BusStatement::PAID)->sum('total');
            }
            $add('s', ['text' => __('ledger_map.h_suppliers_all'), 'url' => \Route::has('ledger.supplier-bill.aging') ? route('ledger.supplier-bill.aging') : null], $records, $ledger);
        }
        uasort($rows, fn ($x, $y) => abs($y['difference']) <=> abs($x['difference']));

        return array_values($rows);
    }

    /** The bus branch whose books are open (null for a head office's own books). */
    private function agency(): ?string
    {
        $row = DB::table('ledger_businesses')->where('id', $this->bid)->first();

        return $row && $row->owner_type === \App\Ledger\ERide\Books::AGENCY ? (string) $row->owner_id : null;
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
                'document' => $this->docCell($m->transaction_type, $m->transaction_id, null),
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

    /** E-Ride: the table and key of each kind of record that posts (a mapping whose record is gone is an orphan). */
    private const SOURCES = [
        'bus_move' => ['bus_money_moves', 'id'],
        'bus_departure' => ['bus_departures', 'id'],
        'bus_statement' => ['bus_statements', 'id'],
        'bus_statement_paid' => ['bus_statements', 'id'],
        'income' => ['incomes', 'id'],
        'expense' => ['expenses', 'id'],
        'supplier_bill' => ['supplier_bills', 'id'],
        'supplier_payment' => ['supplier_payments', 'id'],
        'payment_transfer' => ['payment_account_transfers', 'id'],
        'payment_account_txn' => ['payment_account_transactions', 'id'],
        'payroll' => ['payrolls', 'id'],
        'staff_loan' => ['staff_loans', 'id'],
        'tax_remittance' => ['tax_remittances', 'id'],
    ];

    /** Date and amount of the mappings' records (E-Ride: from the mappings themselves). */
    private function documents($mappings): array
    {
        $out = [];
        $ids = collect($mappings)->map(fn ($m) => [$m->transaction_type, $m->transaction_id]);
        if ($ids->isEmpty()) {
            return $out;
        }
        foreach (DB::table('transaction_mappings')->where('business_id', $this->bid)
            ->whereIn('transaction_id', $ids->pluck(1)->unique()->all())
            ->get(['transaction_type', 'transaction_id', 'transaction_date as date', 'amount']) as $m) {
            $out[$m->transaction_type.':'.$m->transaction_id] = (object) ['date' => $m->date, 'amount' => $m->amount, 'invoice_no' => null, 'ref_no' => null, 'contact_id' => null];
        }

        return $out;
    }

    private function docCell(string $type, $id, $doc): array
    {
        $no = null;
        if ($type === PostingRules::MOVE) {
            $no = DB::table('bus_money_moves as mv')->leftJoin('bus_bookings as bk', 'bk.id', '=', 'mv.booking_id')->where('mv.id', $id)->value('bk.code');
        }
        $label = \Lang::has('ledger.doc_'.$type) ? __('ledger.doc_'.$type) : (class_exists(PaymentBook::class) ? PaymentBook::docLabel($type) : $type);
        $link = self::documentLink($type, $id);

        return ['text' => trim($label.' '.($no ?: '#'.$id)), 'url' => $link['url'] ?? null, 'modal' => $link['modal'] ?? false];
    }

    private function date($d): string
    {
        return $d ? Carbon::parse($d)->format('d/m/Y') : '';
    }

    // ===================================================================== shared with the screens

    /** Where a record opens (E-Ride: the bus records' and Income & Expense screens). */
    public static function documentLink(string $type, $id, $contactId = null): ?array
    {
        $show = ['income' => 'ledger.income.show', 'expense' => 'ledger.expense.show',
            'supplier_bill' => 'ledger.supplier-bill.show', 'supplier_payment' => 'ledger.supplier-payment.show'][$type] ?? null;
        if ($show && \Route::has($show)) {
            return ['url' => route($show, $id), 'modal' => false];
        }
        if ($type === 'payment_transfer' && \Route::has('ledger.payment-account-transfer.index')) {
            return ['url' => route('ledger.payment-account-transfer.index'), 'modal' => false];
        }
        if ($type === PostingRules::DEPARTURE && \Route::has('bus-partner.departures')) {
            return ['url' => route('bus-partner.departures'), 'modal' => false];
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
