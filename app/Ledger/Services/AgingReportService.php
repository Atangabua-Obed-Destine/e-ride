<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Receivables and payables aging: paxhitestbed's AgingReportService, same age
 * bands and the same report shape (`summary` per band, `details` per row,
 * `chart`), so the screens and exports read alike.
 *
 * Adapted to PosInnova, where the money owed is on POS documents:
 *
 *   receivables  what each customer owes on 411: final sales, customer opening
 *                balances, less their payments and returns, less discounts given
 *   payables     what is owed to each supplier on 401: received purchases,
 *                expenses with a supplier, supplier opening balances, less
 *                payments and returns, less discounts received
 *
 * Each document is aged from its DUE DATE (its date plus its pay term; a
 * document without terms is due on its own date). paxhitestbed's version aged
 * every ledger line by its own entry date, per account, so a payment made
 * yesterday on a 100-day-old invoice showed as a credit in "0-30 days" and the
 * debt it settled stayed in "91-120 days".
 *
 * What a document puts on 411 / 401 is asked of PostingRules, the same rules
 * that post it, but worked out from the documents themselves. The result is
 * then tied out to the ledger balance of the account (paxhitestbed's supplier
 * aging tie-out): money posted to 411 / 401 other than through a document (a
 * journal entry typed by hand, a document that failed to post) shows as the
 * difference, customer by customer.
 *
 * E-Ride: the documents are the supplier bills and E-Ride's statements (payables); nothing is sold
 * on account, so receivables has no documents and only ties out 411 (see openDocuments()).
 */
class AgingReportService
{
    /**
     * Age brackets in days (overdue)
     */
    protected $ageBrackets = [
        ['min' => 0, 'max' => 30, 'label' => '0-30 Days'],
        ['min' => 31, 'max' => 60, 'label' => '31-60 Days'],
        ['min' => 61, 'max' => 90, 'label' => '61-90 Days'],
        ['min' => 91, 'max' => 120, 'label' => '91-120 Days'],
        ['min' => 121, 'max' => null, 'label' => 'Over 120 Days'],
    ];

    const BANDS = ['current', 'days_31_60', 'days_61_90', 'days_91_120', 'over_120'];

    /** Document types that can put money on 411 (receivable) or 401 (payable). */
    private const TYPES = [
        'receivable' => ['sell', 'sell_return', 'opening_balance', 'ledger_discount'],
        'payable' => ['purchase', 'purchase_return', 'expense', 'opening_balance', 'ledger_discount'],
    ];

    /**
     * Get accounts receivable aging report
     */
    public function getReceivablesAging($asOfDate = null, array $filters = [])
    {
        return $this->generateAgingReport('receivable', $this->asOf($asOfDate), $filters);
    }

    /**
     * Get accounts payable aging report
     */
    public function getPayablesAging($asOfDate = null, array $filters = [])
    {
        return $this->generateAgingReport('payable', $this->asOf($asOfDate), $filters);
    }

    /**
     * Get aging summary with charts data
     *
     * @param  string  $type  'receivables' or 'payables'
     */
    public function getAgingSummaryWithCharts($type, $asOfDate = null)
    {
        switch ($type) {
            case 'receivables':
                $data = $this->getReceivablesAging($asOfDate);
                break;
            case 'payables':
                $data = $this->getPayablesAging($asOfDate);
                break;
            default:
                throw new Exception(__('ledger_reports.invalid_aging_report_type'));
        }

        $data['chart'] = [
            'labels' => array_map(fn ($b) => $this->bracketLabel($b), $this->ageBrackets),
            'data' => array_map(fn ($i) => $data['totals'][$this->getBracketKey($i)] ?? 0, array_keys($this->ageBrackets)),
            'colors' => ['#28a745', '#ffc107', '#fd7e14', '#dc3545', '#6c757d'],
        ];

        return $data;
    }

    private function asOf($asOfDate): Carbon
    {
        try {
            return $asOfDate ? Carbon::parse($asOfDate)->startOfDay() : Carbon::today();
        } catch (\Throwable $e) {
            return Carbon::today();
        }
    }

    public function bracketLabel(array $bracket): string
    {
        return $bracket['max'] === null
            ? __('ledger_reports.over_n_days', ['n' => $bracket['min'] - 1])
            : __('ledger_reports.n_to_m_days', ['n' => $bracket['min'] === 0 ? 0 : $bracket['min'], 'm' => $bracket['max']]);
    }

    /**
     * @param  string  $type  'receivable' or 'payable'
     */
    protected function generateAgingReport(string $type, Carbon $asOf, array $filters): array
    {
        $D = $asOf->toDateString();
        $receivable = $type === 'receivable';
        $sign = $receivable ? 1 : -1; // receivables are debits, payables credits

        $control = DefaultAccount::id($receivable ? 'customers' : 'suppliers');
        $ledgerAccounts = ChartOfAccount::withTrashed()->where('account_code', 'like', $receivable ? '411%' : '401%')->pluck('id')->all();
        if ($control) {
            $ledgerAccounts[] = $control;
        }
        $ledgerAccounts = array_values(array_unique(array_map('intval', $ledgerAccounts)));

        $report = [
            'as_of_date' => $D,
            'type' => $type,
            'brackets' => $this->ageBrackets,
            'accounts' => [],
            'totals' => $this->initializeBracketTotals(),
            'grand_total' => 0,
            'documents' => [],
            'problems' => [],
            'ledger_accounts' => ChartOfAccount::withTrashed()->whereIn('id', $ledgerAccounts)->orderBy('account_code')->get()
                ->map(fn ($a) => $a->account_code.' '.Preview::accountName($a))->all(),
        ];

        $rows = $control ? $this->openDocuments($type, $D, (int) $control, $sign, $report['problems']) : [];

        // one row per contact, with its documents
        $contacts = [];
        foreach ($rows as $doc) {
            $cid = (int) $doc['contact_id'];
            if (! isset($contacts[$cid])) {
                $contacts[$cid] = [
                    'contact_id' => $cid,
                    'name' => $doc['contact_name'],
                    'brackets' => $this->initializeBracketTotals(),
                    'total' => 0.0,
                    'documents' => [],
                ];
            }
            $key = $this->getBracketKey($doc['bracket']);
            $contacts[$cid]['brackets'][$key] += $doc['owed'];
            $contacts[$cid]['total'] += $doc['owed'];
            $contacts[$cid]['documents'][] = $doc;
            $report['documents'][] = $doc;
        }

        foreach ($contacts as $c) {
            if (abs($c['total']) < 0.005) {
                continue;
            }
            $report['accounts'][] = $c;
            foreach (array_keys($this->ageBrackets) as $i) {
                $k = $this->getBracketKey($i);
                $report['totals'][$k] += $c['brackets'][$k];
            }
            $report['grand_total'] += $c['total'];
        }
        usort($report['accounts'], fn ($a, $b) => $b['total'] <=> $a['total']);

        // Calculate percentages
        if ($report['grand_total'] != 0) {
            foreach (array_keys($this->ageBrackets) as $i) {
                $k = $this->getBracketKey($i);
                $report['totals'][$k.'_percent'] = round(($report['totals'][$k] / $report['grand_total']) * 100, 2);
            }
        }

        // the tie-out: the same money as the ledger says, customer by customer
        $byContact = $this->ledgerByContact($ledgerAccounts, $D, $sign);
        $stored = (float) ChartOfAccount::withTrashed()->whereIn('id', $ledgerAccounts)->get()
            ->sum(fn ($a) => (float) $a->opening_balance * ($a->normal_balance === 'debit' ? 1 : -1) * $sign);
        $report['ledger_balance'] = round(array_sum($byContact) + $stored, 2);
        $report['difference'] = round($report['ledger_balance'] - round($report['grand_total'], 2), 2);

        $docsBy = [];
        foreach ($contacts as $cid => $c) {
            $docsBy[$cid] = round($c['total'], 2);
        }
        $names = array_column($contacts, 'name', 'contact_id');
        $missing = array_diff(array_keys($byContact), array_keys($names));
        $missing = array_filter($missing, fn ($id) => $id > 0 || $id === self::ERIDE); // E-Ride: and E-Ride's own row
        if ($missing) {
            foreach ($this->contactNames($missing) as $id => $n) {
                $names[$id] = $n;
            }
        }
        $differ = [];
        // the opening balance stored on the account itself belongs to no one: with the lines without a contact
        $ids = array_unique(array_merge(array_keys($docsBy), array_keys($byContact), abs($stored) > 0.005 ? [0] : []));
        foreach ($ids as $cid) {
            $docs = $docsBy[$cid] ?? 0.0;
            $led = round(($byContact[$cid] ?? 0.0) + ($cid === 0 ? $stored : 0), 2);
            if (abs($docs - $led) > 0.005) {
                $differ[] = [
                    'contact_id' => $cid,
                    'name' => $cid ? ($names[$cid] ?? '#'.$cid) : __('ledger_reports.no_contact_on_line'),
                    'documents' => $docs,
                    'ledger' => $led,
                    'difference' => round($led - $docs, 2),
                ];
            }
        }
        usort($differ, fn ($a, $b) => abs($b['difference']) <=> abs($a['difference']));
        $report['contacts_differing'] = $differ;

        return $this->presentable($report, $report['accounts'], fn ($a) => [
            'customer_name' => $a['name'],
            'vendor_name' => $a['name'],
            'supplier_name' => $a['name'],
            // E-Ride: a supplier opens on its statement (posinnova: the POS contact page)
            'link' => ! $receivable && $a['contact_id'] > 0 && \Route::has('ledger.supplier-bill.statement')
                ? route('ledger.supplier-bill.statement', ['supplier_id' => $a['contact_id']]) : null,
        ]);
    }

    /**
     * Every document with something owed on it on $D: [id, type, number, date, due_date,
     * days_overdue, bracket, owed, contact_id, contact_name, journal_entry_id].
     *
     * E-Ride (posinnova read POS sales, purchases, expenses and their payments):
     *   payables     the supplier bills still owed on $D (SupplierPayables, the same figures as the
     *                bills' own aging), and E-Ride's monthly statements posted and not yet paid on $D
     *                (they are on 401 too), each due on its own due date;
     *   receivables  none: bus tickets are paid when they are sold and the Income module records
     *                money received, so nothing is sold on account. Whatever is on 411 was typed
     *                by hand and shows as the difference with the ledger.
     */
    private function openDocuments(string $type, string $D, int $control, int $sign, array &$problems): array
    {
        if ($type !== 'payable') {
            return [];
        }
        $asOf = Carbon::parse($D);
        $row = function (array $doc) use ($asOf) {
            $days = (int) Carbon::parse($doc['due_date'])->diffInDays($asOf, false);

            return $doc + ['days_overdue' => max(0, $days), 'bracket' => $this->findBracketIndex(max(0, $days))];
        };
        $out = [];
        foreach (app(SupplierPayables::class)->openBills(null, $D) as $b) {
            $out[] = $row([
                'id' => (int) $b->id,
                'type' => PostingRules::SUPPLIER_BILL,
                'number' => $b->number ?: ($b->supplier_reference ?: '#'.$b->id),
                'date' => substr((string) $b->bill_date, 0, 10),
                'due_date' => substr((string) ($b->due_date ?: $b->bill_date), 0, 10),
                'owed' => round((float) $b->owed, 2),
                'contact_id' => (int) $b->supplier_id,
                'contact_name' => $b->supplier_name ?: '#'.$b->supplier_id,
                'journal_entry_id' => $b->journal_entry_id ? (int) $b->journal_entry_id : null,
            ]);
        }
        foreach (self::erideStatementsOwed($D) as $s) {
            $out[] = $row($s + ['type' => PostingRules::STATEMENT, 'contact_id' => self::ERIDE, 'contact_name' => 'E-Ride']);
        }

        return $out;
    }

    /** E-Ride: the contact of E-Ride's own statements in the aging (suppliers are item_suppliers ids, always positive). */
    public const ERIDE = -1;

    /**
     * E-Ride's statements of the open books' branch that are on 401 on $D (their entry posted by then)
     * and not paid by then: [id, number, date, due_date, owed, journal_entry_id].
     */
    public static function erideStatementsOwed(string $D): array
    {
        $bid = Ledger::businessId();
        $agency = DB::table('ledger_businesses')->where('id', $bid)->where('owner_type', \App\Ledger\ERide\Books::AGENCY)->value('owner_id');
        if (! $agency) {
            return [];
        }
        $paid = DB::table('transaction_mappings')->where('business_id', $bid)->where('transaction_type', PostingRules::STATEMENT_PAID)
            ->where('status', 'posted')->whereDate('transaction_date', '<=', $D)->pluck('transaction_id')->all();

        return DB::table('transaction_mappings as tm')
            ->join('bus_statements as s', 's.id', '=', 'tm.transaction_id')
            ->where('tm.business_id', $bid)->where('tm.transaction_type', PostingRules::STATEMENT)->where('tm.status', 'posted')
            ->whereDate('tm.transaction_date', '<=', $D)->where('s.agency_id', (string) $agency)
            ->whereNotIn('tm.transaction_id', $paid ?: [''])
            ->orderBy('s.month')
            ->get(['s.id', 's.reference', 's.month', 's.due_on', 's.total', 'tm.transaction_date', 'tm.journal_entry_id'])
            ->map(fn ($s) => [
                'id' => (string) $s->id,
                'number' => $s->reference ?: Carbon::parse($s->month)->translatedFormat('F Y'),
                'date' => substr((string) $s->transaction_date, 0, 10),
                'due_date' => substr((string) ($s->due_on ?: $s->transaction_date), 0, 10),
                'owed' => round((float) $s->total, 2),
                'journal_entry_id' => $s->journal_entry_id ? (int) $s->journal_entry_id : null,
            ])->all();
    }

    /** A document's due date: its date plus its pay term; no term, due on its date. */
    public function dueDate(object $t): Carbon
    {
        $date = Carbon::parse(substr((string) $t->transaction_date, 0, 10));
        $n = (int) ($t->pay_term_number ?? 0);
        if ($n > 0 && in_array($t->type, ['sell', 'purchase', 'expense', 'opening_balance'])) {
            return ($t->pay_term_type ?? 'days') === 'months' ? $date->copy()->addMonthsNoOverflow($n) : $date->copy()->addDays($n);
        }

        return $date;
    }

    /** Ledger balance of the accounts on $D per contact (0 = lines with no contact). */
    private function ledgerByContact(array $accounts, string $D, int $sign): array
    {
        if (! $accounts) {
            return [];
        }
        $bid = Ledger::businessId();
        // E-Ride: the lines carry no contact; each is given to the supplier of the bill or payment
        // behind its entry (through its mapping row; a reversal points at the same row), or to E-Ride
        // for its statement. Lines typed by hand keep their own contact, if any (0: none).
        $kinds = [PostingRules::SUPPLIER_BILL, PostingRules::SUPPLIER_PAYMENT, PostingRules::STATEMENT, PostingRules::STATEMENT_PAID];
        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->leftJoin('transaction_mappings as tm', function ($q) use ($bid, $kinds) {
                $q->on('tm.id', '=', 'j.reference_id')->where('tm.business_id', $bid)->whereIn('tm.transaction_type', $kinds)
                    ->where(fn ($w) => $w->whereColumn('j.reference_type', 'tm.transaction_type')->orWhere('j.reference_type', 'transaction_mapping'));
            })
            ->leftJoin('supplier_bills as sb', fn ($q) => $q->where('tm.transaction_type', PostingRules::SUPPLIER_BILL)
                ->on(DB::raw('CAST(sb.id AS CHAR)'), '=', 'tm.transaction_id'))
            ->leftJoin('supplier_payments as sp', fn ($q) => $q->where('tm.transaction_type', PostingRules::SUPPLIER_PAYMENT)
                ->on(DB::raw('CAST(sp.id AS CHAR)'), '=', 'tm.transaction_id'))
            ->where('l.business_id', $bid)->where('j.business_id', $bid)
            ->whereIn('l.account_id', $accounts)
            ->where('j.is_posted', 1)->whereNull('j.deleted_at')
            ->where('j.entry_date', '<=', $D)
            ->groupBy('cid')
            ->select(DB::raw('COALESCE(sb.supplier_id, sp.supplier_id, CASE WHEN tm.transaction_type IN (\''.PostingRules::STATEMENT.'\', \''.PostingRules::STATEMENT_PAID.'\') THEN '.self::ERIDE.' END, l.contact_id, 0) as cid'),
                DB::raw('SUM(l.debit - l.credit) as net'))
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->cid] = round(($out[(int) $r->cid] ?? 0) + $sign * (float) $r->net, 2);
        }

        return $out;
    }

    /** E-Ride: the suppliers of the Income & Expense module (item_suppliers), and E-Ride itself (posinnova: POS contacts). */
    private function contactNames(array $ids): array
    {
        $names = DB::table('item_suppliers')->where('business_id', Ledger::businessId())->whereIn('id', array_filter($ids, fn ($id) => $id > 0))
            ->pluck('title', 'id')->all();
        if (in_array(self::ERIDE, $ids, true)) {
            $names[self::ERIDE] = 'E-Ride';
        }

        return $names;
    }

    /**
     * The shape the aging screens and PDFs read: `summary` with one figure per
     * age band and `details` with one row per contact, keyed by band
     * (current, days_31_60 ... over_120). (paxhitestbed)
     */
    protected function presentable(array $report, array $rows, callable $names): array
    {
        $band = function (array $brackets) {
            $out = [];
            foreach (self::BANDS as $i => $key) {
                $out[$key] = round((float) ($brackets[$this->getBracketKey($i)] ?? 0), 2);
            }

            return $out;
        };

        $report['summary'] = $band($report['totals']) + ['total' => round((float) $report['grand_total'], 2)];
        $report['details'] = array_map(
            fn ($r) => $names($r) + $band($r['brackets']) + ['total' => round((float) $r['total'], 2)] + $r,
            $rows
        );
        $report['filters'] = ($report['filters'] ?? []) + ['as_of_date' => $report['as_of_date']];
        $report['problems'] = array_map(fn ($msg, $n) => ['message' => $msg, 'count' => $n], array_keys($report['problems']), $report['problems']);

        return $report;
    }

    protected function initializeBracketTotals()
    {
        $totals = [];
        foreach ($this->ageBrackets as $index => $bracket) {
            $totals[$this->getBracketKey($index)] = 0;
        }

        return $totals;
    }

    protected function getBracketKey(int $index)
    {
        return 'bracket_'.$index;
    }

    protected function findBracketIndex(int $days)
    {
        foreach ($this->ageBrackets as $index => $bracket) {
            if ($bracket['max'] === null) {
                return $index;
            }
            if ($days >= $bracket['min'] && $days <= $bracket['max']) {
                return $index;
            }
        }

        return count($this->ageBrackets) - 1;
    }

    public function setAgeBrackets(array $brackets)
    {
        $this->ageBrackets = $brackets;

        return $this;
    }

    public function getDefaultAgeBrackets()
    {
        return [
            ['min' => 0, 'max' => 30, 'label' => '0-30 Days'],
            ['min' => 31, 'max' => 60, 'label' => '31-60 Days'],
            ['min' => 61, 'max' => 90, 'label' => '61-90 Days'],
            ['min' => 91, 'max' => 120, 'label' => '91-120 Days'],
            ['min' => 121, 'max' => null, 'label' => 'Over 120 Days'],
        ];
    }
}
