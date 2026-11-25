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
        $missing = array_filter($missing, fn ($id) => $id > 0);
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
        ]);
    }

    /**
     * Every document with something owed on it on $D, as PostingRules puts it on
     * the control account: [id, type, number, date, due_date, days_overdue,
     * bracket, owed, contact_id, contact_name, journal_entry_id].
     */
    private function openDocuments(string $type, string $D, int $control, int $sign, array &$problems): array
    {
        $bid = Ledger::businessId();
        $types = self::TYPES[$type];
        $next = Carbon::parse($D)->addDay()->toDateString();

        // Only documents that can still be open on $D: not settled now, or settled
        // by a payment made after $D. Discounts have no payments, so always count.
        $docs = DB::table('transactions as t')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->where('t.business_id', $bid)
            ->whereIn('t.type', $types)
            ->whereNotNull('t.contact_id')
            ->where('t.transaction_date', '<', $next)
            ->where(function ($w) use ($bid, $next) {
                $w->whereNull('t.payment_status')
                    ->orWhere('t.payment_status', '!=', 'paid')
                    ->orWhere('t.type', 'ledger_discount')
                    ->orWhereExists(function ($q) use ($bid, $next) {
                        $q->select(DB::raw(1))->from('transaction_payments as p')
                            ->whereColumn('p.transaction_id', 't.id')->where('p.business_id', $bid)
                            ->where('p.paid_on', '>=', $next);
                    });
            })
            ->orderBy('t.transaction_date')->orderBy('t.id')
            ->select('t.*', DB::raw("COALESCE(NULLIF(c.supplier_business_name, ''), c.name) as aging_contact_name"))
            ->get();

        if ($docs->isEmpty()) {
            return [];
        }

        $payments = DB::table('transaction_payments')->where('business_id', $bid)
            ->whereIn('transaction_id', $docs->pluck('id')->all())
            ->where('paid_on', '<', $next)
            ->orderBy('paid_on')->orderBy('id')
            ->get()->groupBy('transaction_id');

        $rules = new PostingRules();
        $onControl = function (?array $entry) use ($control, $sign): float {
            $sum = 0.0;
            foreach ($entry['lines'] ?? [] as $l) {
                if ((int) $l['account_id'] === $control) {
                    $sum += $sign * ((float) $l['debit'] - (float) $l['credit']);
                }
            }

            return $sum;
        };
        $try = function (callable $fn) use (&$problems) {
            try {
                return $fn();
            } catch (\RuntimeException $e) {
                $problems[$e->getMessage()] = ($problems[$e->getMessage()] ?? 0) + 1;

                return null;
            }
        };

        // amount on the control account of each document, returns folded into
        // the document they were made from
        $owed = [];
        $byId = $docs->keyBy('id');
        foreach ($docs as $t) {
            $amount = $onControl($try(fn () => $rules->forTransaction($t)));
            foreach ($payments->get($t->id, []) as $p) {
                $amount += $onControl($try(fn () => $rules->forPayment($p, $t)));
            }
            if (abs($amount) < 0.005) {
                continue;
            }
            $isReturn = in_array($t->type, ['sell_return', 'purchase_return']) && $t->return_parent_id;
            $key = $isReturn ? (int) $t->return_parent_id : (int) $t->id;
            $owed[$key] = ($owed[$key] ?? 0.0) + $amount;
        }

        // the documents returns were made from, when they are not open themselves
        $parents = array_diff(array_keys($owed), $byId->keys()->all());
        if ($parents) {
            $more = DB::table('transactions as t')->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
                ->where('t.business_id', $bid)->whereIn('t.id', $parents)
                ->select('t.*', DB::raw("COALESCE(NULLIF(c.supplier_business_name, ''), c.name) as aging_contact_name"))
                ->get();
            foreach ($more as $m) {
                $byId[$m->id] = $m;
            }
        }

        $journal = DB::table('transaction_mappings')->where('business_id', $bid)
            ->whereNotIn('transaction_type', ['payment', 'account_transaction'])->whereIn('transaction_id', array_keys($owed))
            ->where('status', 'posted')->pluck('journal_entry_id', 'transaction_id');

        $asOf = Carbon::parse($D);
        $out = [];
        foreach ($owed as $id => $amount) {
            $t = $byId[$id] ?? null;
            if (! $t) {
                continue;
            }
            $due = $this->dueDate($t);
            $days = (int) $due->diffInDays($asOf, false);
            $out[] = [
                'id' => (int) $t->id,
                'type' => $t->type,
                'number' => $t->invoice_no ?: ($t->ref_no ?: '#'.$t->id),
                'date' => substr((string) $t->transaction_date, 0, 10),
                'due_date' => $due->toDateString(),
                'days_overdue' => max(0, $days),
                'bracket' => $this->findBracketIndex(max(0, $days)),
                'owed' => round($amount, 2),
                'contact_id' => (int) $t->contact_id,
                'contact_name' => $t->aging_contact_name ?: '#'.$t->contact_id,
                'journal_entry_id' => isset($journal[$id]) ? (int) $journal[$id] : null,
            ];
        }

        return $out;
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
        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->where('l.business_id', $bid)->where('j.business_id', $bid)
            ->whereIn('l.account_id', $accounts)
            ->where('j.is_posted', 1)->whereNull('j.deleted_at')
            ->where('j.entry_date', '<=', $D)
            ->groupBy('l.contact_id')
            ->select('l.contact_id', DB::raw('SUM(l.debit - l.credit) as net'))
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->contact_id] = round($sign * (float) $r->net, 2);
        }

        return $out;
    }

    private function contactNames(array $ids): array
    {
        return DB::table('contacts')->where('business_id', Ledger::businessId())->whereIn('id', $ids)
            ->pluck(DB::raw("COALESCE(NULLIF(supplier_business_name, ''), name)"), 'id')->all();
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
