<?php

namespace App\Ledger\Services;

use App\Ledger\Models\AccountingSetting;
use App\Ledger\Models\ChartOfAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusMoneyMove;
use Modules\BusManagement\Entities\BusPayment;
use Modules\BusManagement\Entities\BusStatement;

/**
 * What a bus record puts in the ledger. E-Ride's version of posinnova's
 * PostingRules (which does the same for POS sales and purchases): each rule
 * returns the entry a record should have right now, or null when it has none,
 * and LedgerSync compares that with what is posted and corrects the difference
 * (paxhitestbed's remap). The accounts come from Mapping settings and Cash
 * accounts, never from code.
 *
 * Ticket money is held for the trip until the bus leaves (paxhitestbed's advice:
 * 477 Produits constatés d'avance), then it is a sale:
 *
 *   ticket paid                      Dr money (method)      Cr 477 held   (the trip left already: Cr 706 sales)
 *   paid beyond the fare             Dr money               Cr 4198 refunds owed
 *   refund owed                      Dr 477 (or 709)        Cr 4198
 *   refund given at the counter      Dr 477 (or 709)        Cr money
 *   refund sent                      Dr 4198                Cr money the refunds go out of
 *   moved to another trip            Dr 477 old trip (709)  Cr 477 new trip (706)       no money moves
 *   app payment, Fapshi's share      Dr 631 fees            Cr 5523 Fapshi  (in the same entry)
 *   the trip left                    Dr 477                 Cr 706, per channel, what was held for it
 *   E-Ride's monthly statement       Dr 632 commission      Cr 401 E-Ride
 *   the statement paid               Dr 401 E-Ride          Cr money (or 1811 when the head office paid it)
 *
 * "Held or sale" is decided by time, not by the order things are synced: a
 * payment, refund or move made before the bus left touches 477; one made after
 * it left goes straight to sales (706) or to refunds after the trip (709). The
 * trip's own entry moves to 706 exactly what was held for it. So the same bus
 * records always give the same entries, whatever order they are posted in.
 *
 * Every line carries the bus cost centres: trip, bus, route, channel, and the
 * counter agent on a till line.
 */
class PostingRules
{
    // The kinds of bus records that post (transaction_mappings.transaction_type).
    const MOVE = 'bus_move';
    const DEPARTURE = 'bus_departure';
    const STATEMENT = 'bus_statement';
    const STATEMENT_PAID = 'bus_statement_paid';
    // paxhitestbed's Income & Expense and Payment Accounts (App\Ledger\Models; see forIncome() and below)
    const INCOME = 'income';
    const EXPENSE = 'expense';
    const SUPPLIER_BILL = 'supplier_bill';
    const SUPPLIER_PAYMENT = 'supplier_payment';
    const PAYMENT_TRANSFER = 'payment_transfer';
    const PAYMENT_ACCOUNT_TXN = 'payment_account_txn';
    const ACCOUNTS_TYPES = [self::INCOME, self::EXPENSE, self::SUPPLIER_BILL, self::SUPPLIER_PAYMENT, self::PAYMENT_TRANSFER, self::PAYMENT_ACCOUNT_TXN];
    // paxhitestbed's payroll and tax (App\Ledger\Payroll; see forPayroll() and below)
    const PAYROLL = 'payroll';
    const STAFF_LOAN = 'staff_loan';
    const TAX_REMITTANCE = 'tax_remittance';
    const PAYROLL_PAYMENT = 'payroll_payment'; // the payment of an accrued payroll (Dr 422 / Cr the money)
    const PAYROLL_TYPES = [self::PAYROLL, self::PAYROLL_PAYMENT, self::STAFF_LOAN, self::TAX_REMITTANCE];
    const TYPES = [self::MOVE, self::DEPARTURE, self::STATEMENT, self::STATEMENT_PAID,
        self::INCOME, self::EXPENSE, self::SUPPLIER_BILL, self::SUPPLIER_PAYMENT, self::PAYMENT_TRANSFER, self::PAYMENT_ACCOUNT_TXN,
        self::PAYROLL, self::STAFF_LOAN, self::TAX_REMITTANCE, self::PAYROLL_PAYMENT];

    private $codes;

    private $mapped = [];

    private $departures = [];

    public function __construct()
    {
        $this->codes = ChartOfAccount::pluck('id', 'account_code')->all();
    }

    // ---------------------------------------------------------------- money moves

    /** @return array|null ['date', 'description', 'reference', 'journal_type', 'lines' => [...]] */
    public function forMove(BusMoneyMove $m): ?array
    {
        $amount = round((float) $m->amount, 2);
        if ($amount <= 0 || $m->skipped) {
            return null;
        }
        $at = Carbon::parse($m->at);
        $d = $this->departure($m->departure_id);
        $tags = $this->tags($d, $m->channel);
        $code = DB::table('bus_bookings')->where('id', $m->booking_id)->value('code');
        // money in or out: the method's account; counter cash in the agent's till
        $money = function (?string $method) use ($m) {
            $method = $method ?: (string) (AccountingSetting::getValue('refunds_paid_from') ?: 'momo');

            return ['account_id' => $this->mapping('payment_method', $method), 'agent_id' => $method === 'cash' ? $m->staff_user_id : null];
        };
        // held for the trip, or (the trip had left) sales / refunds after the trip
        $ticketSide = fn (?BusDeparture $trip, bool $refund) => $this->heldAt($trip, $at)
            ? $this->mapping('tickets_held') : ($refund ? $this->mapping('refunds_after_trip') : $this->mapping('ticket_sales'));
        $L = fn (string $k) => __('ledger.l_bus_'.$k);

        switch ($m->kind) {
            case BusMoneyMove::SALE:
                $lines = [
                    $money($m->method) + ['debit' => $amount, 'credit' => 0, 'description' => $L('ticket_paid')] + $tags,
                    ['account_id' => $ticketSide($d, false), 'debit' => 0, 'credit' => $amount, 'description' => $L('ticket_paid')] + $tags,
                ];
                if (in_array($m->method, ['fapshi', 'company'], true) && ($fee = $this->fee($m))) {
                    // Fapshi pays the ticket less its share: the share is a charge, so the account reconciles to Fapshi's statement
                    $lines[] = ['account_id' => $this->mapping('gateway_fees'), 'debit' => $fee, 'credit' => 0, 'description' => $L('gateway_fee')] + $tags;
                    $lines[] = $money($m->method) + ['debit' => 0, 'credit' => $fee, 'description' => $L('gateway_fee')] + $tags;
                }
                $journal = 'sales';
                $what = 'ticket_paid';
                break;
            case BusMoneyMove::EXTRA:
                $lines = [
                    $money($m->method) + ['debit' => $amount, 'credit' => 0, 'description' => $L('extra')] + $tags,
                    ['account_id' => $this->mapping('refunds_owed'), 'debit' => 0, 'credit' => $amount, 'description' => $L('extra')] + $tags,
                ];
                $journal = 'cash';
                $what = 'extra';
                break;
            case BusMoneyMove::REFUND_OWED:
                $lines = [
                    ['account_id' => $ticketSide($d, true), 'debit' => $amount, 'credit' => 0, 'description' => $L('refund_owed')] + $tags,
                    ['account_id' => $this->mapping('refunds_owed'), 'debit' => 0, 'credit' => $amount, 'description' => $L('refund_owed')] + $tags,
                ];
                $journal = 'sales';
                $what = 'refund_owed';
                break;
            case BusMoneyMove::CASH_REFUND:
                $lines = [
                    ['account_id' => $ticketSide($d, true), 'debit' => $amount, 'credit' => 0, 'description' => $L('cash_refund')] + $tags,
                    $money($m->method) + ['debit' => 0, 'credit' => $amount, 'description' => $L('cash_refund')] + $tags,
                ];
                $journal = 'cash';
                $what = 'cash_refund';
                break;
            case BusMoneyMove::REFUND_SENT:
                $lines = [
                    ['account_id' => $this->mapping('refunds_owed'), 'debit' => $amount, 'credit' => 0, 'description' => $L('refund_sent')] + $tags,
                    $money(null) + ['debit' => 0, 'credit' => $amount, 'description' => $L('refund_sent')] + $tags,
                ];
                $journal = 'cash';
                $what = 'refund_sent';
                break;
            case BusMoneyMove::REBOOK:
                $from = $this->departure($m->from_departure_id);
                $lines = [
                    ['account_id' => $ticketSide($from, true), 'debit' => $amount, 'credit' => 0, 'description' => $L('rebook')] + $this->tags($from, $m->channel),
                    ['account_id' => $ticketSide($d, false), 'debit' => 0, 'credit' => $amount, 'description' => $L('rebook')] + $tags,
                ];
                $journal = 'sales';
                $what = 'rebook';
                break;
            default:
                return null;
        }

        return [
            'date' => $at->toDateString(),
            'description' => $L($what).($code ? ' · '.$code : ''),
            'reference' => $code,
            'journal_type' => $journal,
            'lines' => $lines,
        ];
    }

    // ---------------------------------------------------------------- the trip left

    /** A trip that left: what was held for it (477) becomes sales (706), per channel. Null while it has not left. */
    public function forDeparture(BusDeparture $d): ?array
    {
        $left = $this->leftAt($d);
        if (! $left) {
            return null;
        }
        $held = [];
        $moves = BusMoneyMove::where('agency_id', $d->agency_id)->whereNull('skipped')
            ->where(fn ($q) => $q->where('departure_id', $d->id)->orWhere('from_departure_id', $d->id))->get();
        foreach ($moves as $m) {
            if (Carbon::parse($m->at)->gte($left)) {
                continue; // after the trip left: it went straight to sales or refunds after the trip
            }
            $sign = match ($m->kind) {
                BusMoneyMove::SALE => 1,
                BusMoneyMove::REFUND_OWED, BusMoneyMove::CASH_REFUND => -1,
                BusMoneyMove::REBOOK => (string) $m->departure_id === (string) $m->from_departure_id ? 0
                    : ((string) $m->departure_id === (string) $d->id ? 1 : -1),
                default => 0,
            };
            $ch = $m->channel ?: 'app';
            $held[$ch] = ($held[$ch] ?? 0) + $sign * round((float) $m->amount, 2);
        }
        $lines = [];
        $desc = __('ledger.l_bus_trip_left');
        foreach ($held as $channel => $amount) {
            $amount = round($amount, 2);
            if (abs($amount) < 0.005) {
                continue;
            }
            $tags = $this->tags($d, $channel);
            $lines[] = ['account_id' => $this->mapping('tickets_held'), 'debit' => max(0, $amount), 'credit' => max(0, -$amount), 'description' => $desc] + $tags;
            $lines[] = ['account_id' => $this->mapping('ticket_sales'), 'debit' => max(0, -$amount), 'credit' => max(0, $amount), 'description' => $desc] + $tags;
        }
        if (! $lines) {
            return null;
        }
        $when = $left->gt(now()) ? now() : $left;

        return [
            'date' => $when->toDateString(),
            'description' => $desc.' · '.$d->leavesAt()->format('d/m H:i').' '.($d->destination?->city ?? ''),
            'reference' => null,
            'journal_type' => 'sales',
            'lines' => $lines,
        ];
    }

    // ---------------------------------------------------------------- E-Ride's statement

    /** E-Ride's commission for a month: owed when the statement is made (dated at the month's end). */
    public function forStatement(BusStatement $s): ?array
    {
        $total = round((float) $s->total, 2);
        if ($total <= 0) {
            return null;
        }
        $end = Carbon::parse($s->month)->endOfMonth();
        $desc = __('ledger.l_bus_commission', ['month' => Carbon::parse($s->month)->translatedFormat('F Y')]);

        return [
            'date' => ($end->gt(today()) ? today() : $end)->toDateString(),
            'description' => $desc,
            'reference' => $s->reference,
            'journal_type' => 'purchase',
            'lines' => [
                ['account_id' => $this->mapping('eride_commission'), 'debit' => $total, 'credit' => 0, 'description' => $desc],
                ['account_id' => $this->mapping('eride_payable'), 'debit' => 0, 'credit' => $total, 'description' => $desc],
            ],
        ];
    }

    /** The statement paid: from the account E-Ride is paid from, or by the head office (company bill). */
    public function forStatementPaid(BusStatement $s): ?array
    {
        $total = round((float) $s->total, 2);
        if ($total <= 0 || $s->status !== BusStatement::PAID || ! $s->paid_at) {
            return null;
        }
        $desc = __('ledger.l_bus_commission_paid', ['month' => Carbon::parse($s->month)->translatedFormat('F Y')]);
        $from = $s->company_statement_id ? $this->mapping('head_office')
            : $this->mapping('payment_method', (string) (AccountingSetting::getValue('eride_paid_from') ?: 'momo'));

        return [
            'date' => Carbon::parse($s->paid_at)->toDateString(),
            'description' => $desc,
            'reference' => $s->reference,
            'journal_type' => 'cash',
            'lines' => [
                ['account_id' => $this->mapping('eride_payable'), 'debit' => $total, 'credit' => 0, 'description' => $desc],
                ['account_id' => $from, 'debit' => 0, 'credit' => $total, 'description' => $desc],
            ],
        ];
    }

    // ---------------------------------------------------------------- Income & Expense, Payment Accounts (paxhitestbed)
    //
    // paxhitestbed posts these from its observers (TransactionAutoMapService with the category
    // mapping, CashAccounts for the money side) and from SupplierPayables; here they go through the
    // same sync as the bus records (fingerprint, reverse and re-post on a change, health):
    //
    //   income                     Dr the money's account          Cr income category (7xx)
    //   expense                    Dr expense category (6xx)       Cr the money's account
    //   supplier bill approved     Dr each line's 6xx (+ 4452 VAT) Cr 401 suppliers       (voided: reversed)
    //   supplier payment           Dr 401 suppliers                Cr the bank / cash it left (voided: reversed)
    //   fund transfer              Dr receiving account's 5xx      Cr sending account's 5xx
    //   deposit / opening balance  Dr the account's 5xx            Cr the other side named on it
    //   withdrawal                 Dr the other side named on it   Cr the account's 5xx
    //
    // "The money's account": the payment account's own ledger account when one is named
    // (payment_accounts.chart_of_account_id), otherwise the payment method's (Mapping settings).

    /** @return array|null the entry (same shape as forMove()) */
    public function forIncome(\App\Ledger\Models\Income $i): ?array
    {
        $amount = round((float) $i->amount, 2);
        if ($amount <= 0 || ! $i->status) {
            return null;
        }
        $cat = $i->category;
        $desc = trim(($cat->name ?? __('ledger_accounts.doc_income')).' - '.$i->title, ' -');

        return [
            'date' => $i->date->toDateString(),
            'description' => $desc,
            'reference' => $i->invoice_id ?: $i->reference,
            'journal_type' => 'cash',
            'lines' => [
                ['account_id' => $this->moneyAccount($i->payment_account_id, $i->payment_method), 'debit' => $amount, 'credit' => 0, 'description' => $desc],
                ['account_id' => $this->categoryAccount('income_category', $i->category_id, $cat->name ?? null), 'debit' => 0, 'credit' => $amount, 'description' => $desc],
            ],
        ];
    }

    public function forExpense(\App\Ledger\Models\Expense $e): ?array
    {
        $amount = round((float) $e->amount, 2);
        if ($amount <= 0 || ! $e->status) {
            return null;
        }
        $cat = $e->category;
        $desc = trim(($cat->name ?? __('ledger_accounts.doc_expense')).' - '.$e->title, ' -');

        return [
            'date' => $e->date->toDateString(),
            'description' => $desc,
            'reference' => $e->invoice_id ?: $e->reference,
            'journal_type' => 'cash',
            'lines' => [
                ['account_id' => $this->categoryAccount('expense_category', $e->category_id, $cat->name ?? null), 'debit' => $amount, 'credit' => 0, 'description' => $desc]
                    + ($e->vehicle_id ? ['vehicle_id' => (string) $e->vehicle_id] : []), // E-Ride: the bus it was spent on
                ['account_id' => $this->moneyAccount($e->payment_account_id, $e->payment_method), 'debit' => 0, 'credit' => $amount, 'description' => $desc],
            ],
        ];
    }

    /** An approved bill: the cost (and recoverable VAT) and the debt, on the bill date. A draft or a voided bill has none. */
    public function forSupplierBill(\App\Ledger\Models\SupplierBill $b): ?array
    {
        if (! $b->isApproved() || round((float) $b->total, 2) <= 0) {
            return null;
        }
        $supplier = $b->supplier->title ?? '';
        $desc = trim(__('ledger_accounts.doc_supplier_bill').' '.$b->number.' - '.$supplier.($b->supplier_reference ? ' ('.$b->supplier_reference.')' : ''));
        $lines = [];
        foreach ($b->lines()->with('category')->get() as $l) {
            $account = $l->account_id ?: ($b->isOpeningBalance() ? null : $this->categoryAccount('expense_category', $l->expense_category_id, $l->category->name ?? null));
            if (! $account) {
                throw new \RuntimeException(__('ledger_accounts.err_opening_account'));
            }
            $lines[] = ['account_id' => (int) $account, 'debit' => round((float) $l->amount, 2), 'credit' => 0,
                'description' => trim(($l->description ?: ($l->category->name ?? __('ledger_accounts.opening_balance'))).' - '.$supplier, ' -')]
                + ($l->vehicle_id ? ['vehicle_id' => (string) $l->vehicle_id] : []); // E-Ride: the bus the line is for
        }
        if (round((float) $b->vat_amount, 2) > 0) {
            $lines[] = ['account_id' => $this->mapping('vat_deductible'), 'debit' => round((float) $b->vat_amount, 2), 'credit' => 0,
                'description' => __('ledger_accounts.vat_recoverable').' - '.$supplier];
        }
        $lines[] = ['account_id' => (int) ($b->payable_account_id ?: $this->mapping('suppliers')), 'debit' => 0, 'credit' => round((float) $b->total, 2),
            'description' => __('ledger_accounts.owed_to').' '.$supplier.' - '.$b->number];

        return [
            'date' => $b->bill_date->toDateString(),
            'description' => $desc,
            'reference' => $b->supplier_reference ?: $b->number,
            'journal_type' => 'purchase',
            'lines' => $lines,
        ];
    }

    /** A payment: Dr the supplier account(s) of the bills it pays, Cr the bank or cash it left. A voided one has none. */
    public function forSupplierPayment(\App\Ledger\Models\SupplierPayment $p): ?array
    {
        $amount = round((float) $p->amount, 2);
        if ($p->isVoid() || $amount <= 0) {
            return null;
        }
        $supplier = $p->supplier->title ?? '';
        $byPayable = [];
        $numbers = [];
        foreach ($p->allocations()->with('bill')->get() as $a) {
            $acc = (int) ($a->bill->payable_account_id ?? 0) ?: $this->mapping('suppliers');
            $byPayable[$acc] = round(($byPayable[$acc] ?? 0) + (float) $a->amount, 2);
            $numbers[] = $a->bill->number ?? '';
        }
        if (! $byPayable) {
            $byPayable[$this->mapping('suppliers')] = $amount;
        }
        $desc = trim(__('ledger_accounts.doc_supplier_payment').' '.$p->number.' - '.$supplier.($numbers ? ' ('.implode(', ', array_filter($numbers)).')' : ''));
        $lines = [];
        foreach ($byPayable as $acc => $part) {
            $lines[] = ['account_id' => $acc, 'debit' => $part, 'credit' => 0, 'description' => __('ledger_accounts.paid_to').' '.$supplier];
        }
        $lines[] = ['account_id' => (int) $p->cash_account_id, 'debit' => 0, 'credit' => $amount, 'description' => $desc];
        $code = (string) ChartOfAccount::whereKey($p->cash_account_id)->value('account_code');

        return [
            'date' => $p->payment_date->toDateString(),
            'description' => $desc,
            'reference' => $p->reference ?: $p->number,
            'journal_type' => str_starts_with($code, '52') ? 'bank' : 'cash',
            'lines' => $lines,
        ];
    }

    /** A fund transfer (paxhitestbed CashAccounts::postTransfer). Nothing when both accounts use the same ledger account. */
    public function forPaymentAccountTransfer(\App\Ledger\Models\PaymentAccountTransfer $t): ?array
    {
        $amount = round((float) $t->amount, 2);
        if ($amount <= 0) {
            return null;
        }
        $from = $this->paymentAccountLedger($t->from_account_id);
        $to = $this->paymentAccountLedger($t->to_account_id);
        if ($from === $to) {
            return null;
        }
        $desc = __('ledger_accounts.transfer').': '.($t->fromAccount->title ?? '').' → '.($t->toAccount->title ?? '');

        return [
            'date' => $t->transfer_date->toDateString(),
            'description' => $desc,
            'reference' => null,
            'journal_type' => 'cash',
            'lines' => [
                ['account_id' => $to, 'debit' => $amount, 'credit' => 0, 'description' => __('ledger_accounts.transfer_in')],
                ['account_id' => $from, 'debit' => 0, 'credit' => $amount, 'description' => __('ledger_accounts.transfer_out')],
            ],
        ];
    }

    /**
     * A deposit, a withdrawal or an opening balance typed on a payment account, when the other
     * side of its entry is named (contra_account_id). paxhitestbed posts none of these; without
     * the other side E-Ride does the same, and Cash accounts shows the book beside the ledger.
     */
    public function forPaymentAccountTransaction(\App\Ledger\Models\PaymentAccountTransaction $tx): ?array
    {
        $amount = round((float) $tx->amount, 2);
        if (! $tx->isManual() || ! $tx->contra_account_id || $amount <= 0) {
            return null;
        }
        $cash = $this->paymentAccountLedger($tx->payment_account_id);
        $desc = trim(($tx->title ?: __('ledger_accounts.doc_payment_account_txn')).' - '.($tx->paymentAccount->title ?? ''), ' -');
        $in = $tx->transaction_type === 'credit';

        return [
            'date' => $tx->transaction_date->toDateString(),
            'description' => $desc,
            'reference' => $tx->payment_reference,
            'journal_type' => $tx->reference_type === 'opening_balance' ? 'opening' : 'cash',
            'lines' => [
                ['account_id' => $in ? $cash : (int) $tx->contra_account_id, 'debit' => $amount, 'credit' => 0, 'description' => $desc],
                ['account_id' => $in ? (int) $tx->contra_account_id : $cash, 'debit' => 0, 'credit' => $amount, 'description' => $desc],
            ],
        ];
    }

    /** The account money went through: the payment account's ledger account, or the payment method's. */
    public function moneyAccount($paymentAccountId, ?string $method): int
    {
        if ($paymentAccountId) {
            return $this->paymentAccountLedger($paymentAccountId);
        }
        if (! $method) {
            throw new \RuntimeException(__('ledger_accounts.err_no_money_side'));
        }
        // E-Ride: cash paid or received at the office is the office's (571), not a counter agent's till (5712)
        if ($method === 'cash' && ($office = DefaultAccount::id('office_cash'))) {
            return (int) $office;
        }

        return $this->mapping('payment_method', $method);
    }

    /** A payment account's own ledger account, or a clear refusal. */
    public function paymentAccountLedger($paymentAccountId): int
    {
        $a = \App\Ledger\Models\PaymentAccount::find($paymentAccountId);
        if (! $a || ! $a->chart_of_account_id) {
            throw new \RuntimeException(__('ledger_accounts.err_account_unlinked', ['name' => $a->title ?? '#'.$paymentAccountId]));
        }

        return (int) $a->chart_of_account_id;
    }

    /** A category's account (its own mapping, else the type's '' row), or a refusal naming the category. */
    private function categoryAccount(string $type, $categoryId, ?string $name): int
    {
        $id = DefaultAccount::id($type, (string) $categoryId);
        if (! $id) {
            throw new \RuntimeException(__('ledger.err_no_mapping', ['type' => __('ledger_accounts.map_'.$type), 'key' => $name ? '('.$name.')' : '']));
        }

        return (int) $id;
    }

    // ---------------------------------------------------------------- payroll and tax (paxhitestbed)
    //
    // paxhitestbed's PayrollAccountingService, StaffLoanAccounting and TaxRemittanceService, on the sync:
    //
    //   payroll approved           Dr 661 salaries (gross + loan repayments)   Cr each tax's liability (431 CNPS, 447 State)
    //   (or paid unapproved)       Dr 664 employer charges (employer side)     Cr 421 staff advances (loan repayments)
    //                                                                          Cr 422 salaries payable (net)
    //                              (unapproved: reversed; dated the approval day, or the pay date when paid unapproved)
    //   payroll paid               Dr 422 salaries payable (net)               Cr the payment account it was paid from
    //                              (unpaid: reversed)
    //   loan or advance given      Dr 421 staff advances                       Cr the payment account it was paid from (cancelled: reversed)
    //   tax remittance             Dr the liability (431 / 447)                Cr the payment account it was paid from (voided: reversed)
    //
    // The accounts come from Mapping settings, group "Payroll and tax" (App\Ledger\Payroll\PayrollAccounts).

    /**
     * The payroll's accrual (the user's rule, 2026-11-25: a payroll posts when it is approved): the
     * cost, what is withheld and the net pay owed (422). Approved payrolls have it from the approval
     * day; a payroll paid without approval (approval not required) has it on its pay date.
     */
    public function forPayroll(\App\Ledger\Models\Payroll $p): ?array
    {
        $on = $p->approved_at ? \Carbon\Carbon::parse($p->approved_at) : ($p->isPaid() && $p->pay_date ? $p->pay_date : null);
        if (! $on) {
            return null;
        }
        $p->loadMissing('staff', 'details');
        $accounts = \App\Ledger\Payroll\PayrollAccounts::class;
        $name = $p->staff ? $p->staff->name : '#'.$p->staff_id;
        $month = $p->salary_month->translatedFormat('F Y');
        $desc = __('ledger_payroll.l_salary_payment', ['name' => $name, 'month' => $month]);
        $loans = $p->loanRepayments();
        $lines = [['account_id' => $accounts::need('payroll'), 'debit' => round((float) $p->gross_salary + $loans, 2), 'credit' => 0,
            'description' => __('ledger_payroll.l_gross', ['name' => $name])]];
        if ((float) $p->employer_tax > 0) {
            $lines[] = ['account_id' => $accounts::need('payroll_employer'), 'debit' => round((float) $p->employer_tax, 2), 'credit' => 0,
                'description' => __('ledger_payroll.l_employer', ['name' => $name])];
        }
        foreach ($this->withholdings($p) as $accountId => $credit) {
            $lines[] = ['account_id' => $accountId, 'debit' => 0, 'credit' => $credit['amount'], 'description' => $credit['label'].' - '.$name];
        }
        if ($loans > 0) {
            $lines[] = ['account_id' => $accounts::need('staff_advances'), 'debit' => 0, 'credit' => $loans, 'description' => __('ledger_payroll.l_loan_repaid', ['name' => $name])];
        }
        $lines[] = ['account_id' => $accounts::need('salaries_payable'), 'debit' => 0, 'credit' => round((float) $p->net_salary, 2),
            'description' => __('ledger_payroll.l_net_owed', ['name' => $name])];

        return [
            'date' => $on->toDateString(),
            'description' => $desc,
            'reference' => $p->staff->staff_no ?? null,
            'journal_type' => 'payroll',
            'lines' => $lines,
        ];
    }

    /** A paid payroll's payment: Dr 422 the net owed / Cr the payment account, on its pay date. An unpaid one has none. */
    public function forPayrollPayment(\App\Ledger\Models\Payroll $p): ?array
    {
        $net = round((float) $p->net_salary, 2);
        if (! $p->isPaid() || ! $p->pay_date || $net <= 0) {
            return null;
        }
        $p->loadMissing('staff');
        $name = $p->staff ? $p->staff->name : '#'.$p->staff_id;
        $desc = __('ledger_payroll.l_salary_paid', ['name' => $name, 'month' => $p->salary_month->translatedFormat('F Y')]);

        return [
            'date' => $p->pay_date->toDateString(),
            'description' => $desc,
            'reference' => $p->staff->staff_no ?? null,
            'journal_type' => 'payroll',
            'lines' => [
                ['account_id' => \App\Ledger\Payroll\PayrollAccounts::need('salaries_payable'), 'debit' => $net, 'credit' => 0, 'description' => __('ledger_payroll.l_net_owed', ['name' => $name])],
                ['account_id' => $this->moneyAccount($p->payment_account_id, $p->payment_method), 'debit' => 0, 'credit' => $net, 'description' => __('ledger_payroll.l_net', ['name' => $name])],
            ],
        ];
    }

    /**
     * The withholdings by the account each is owed to (paxhitestbed withholdingsByAccount): from the
     * payroll's itemised tax lines; a line naming no account goes to payroll_tax (the employee side)
     * or payroll_social (the employer side). The rounding difference to the payroll's totals goes on
     * the largest balance of its side. Without lines: employee tax to payroll_tax, employer to payroll_social.
     */
    private function withholdings(\App\Ledger\Models\Payroll $p): array
    {
        $accounts = \App\Ledger\Payroll\PayrollAccounts::class;
        $by = [];
        $add = function ($accountId, string $side, float $amount) use (&$by) {
            $by[$accountId] ??= ['employee' => 0.0, 'employer' => 0.0];
            $by[$accountId][$side] += $amount;
        };
        $lines = \App\Ledger\Models\PayrollTaxLine::where('payroll_id', $p->id)->get();
        if ($lines->isEmpty()) {
            if ((float) $p->tax > 0) {
                $add($accounts::need('payroll_tax'), 'employee', (float) $p->tax);
            }
            if ((float) $p->employer_tax > 0) {
                $add($accounts::need('payroll_social'), 'employer', (float) $p->employer_tax);
            }
        } else {
            foreach ($lines as $l) {
                if ((float) $l->employee_amount != 0.0) {
                    $add($l->liability_account_id ?: $accounts::need('payroll_tax'), 'employee', (float) $l->employee_amount);
                }
                if ((float) $l->employer_amount != 0.0) {
                    $add($l->liability_account_id ?: $accounts::need('payroll_social'), 'employer', (float) $l->employer_amount);
                }
            }
            foreach (['employee' => (float) $p->tax, 'employer' => (float) $p->employer_tax] as $side => $target) {
                $posted = round(array_sum(array_column($by, $side)), 2);
                if (abs($posted - $target) <= 0.001) {
                    continue;
                }
                $largest = null;
                foreach ($by as $id => $c) {
                    if ($c[$side] > 0 && ($largest === null || $c[$side] > $by[$largest][$side])) {
                        $largest = $id;
                    }
                }
                if ($largest === null) {
                    $largest = $accounts::need($side === 'employee' ? 'payroll_tax' : 'payroll_social');
                    $by[$largest] ??= ['employee' => 0.0, 'employer' => 0.0];
                }
                $by[$largest][$side] += $target - $posted;
            }
        }
        $out = [];
        foreach ($by as $id => $c) {
            $amount = round($c['employee'] + $c['employer'], 2);
            if ($amount == 0.0) {
                continue;
            }
            $acc = ChartOfAccount::find($id);
            $out[$id] = ['label' => __('ledger_payroll.l_withheld', ['account' => $acc ? Preview::accountName($acc) : '#'.$id]), 'amount' => $amount];
        }

        return $out;
    }

    /** A loan or salary advance given: Dr staff advances / Cr the payment account, on the day it was given. Cancelled: none. */
    public function forStaffLoan(\App\Ledger\Models\StaffLoan $loan): ?array
    {
        $amount = round((float) $loan->amount, 2);
        if ($loan->status !== 'active' || $amount <= 0) {
            return null;
        }
        $name = $loan->staff->name ?? '#'.$loan->staff_id;
        $desc = __('ledger_payroll.l_loan_given', ['title' => $loan->title, 'name' => $name]);

        return [
            'date' => $loan->given_on->toDateString(),
            'description' => $desc,
            'reference' => $loan->staff->staff_no ?? null,
            'journal_type' => 'payroll',
            'lines' => [
                ['account_id' => \App\Ledger\Payroll\PayrollAccounts::need('staff_advances'), 'debit' => $amount, 'credit' => 0, 'description' => $loan->title.' - '.$name],
                ['account_id' => $this->moneyAccount($loan->payment_account_id, null), 'debit' => 0, 'credit' => $amount, 'description' => __('ledger_payroll.l_loan_paid_out', ['title' => $loan->title, 'name' => $name])],
            ],
        ];
    }

    /** Withheld tax paid over: Dr the liability / Cr the payment account, on the payment date. Voided: none. */
    public function forTaxRemittance(\App\Ledger\Models\TaxRemittance $r): ?array
    {
        $amount = round((float) $r->amount, 2);
        if ($r->isVoided() || $amount <= 0) {
            return null;
        }
        $liability = ChartOfAccount::find($r->liability_account_id);
        $desc = __('ledger_payroll.tax_remittance').' - '.($liability ? Preview::accountName($liability) : '').' ('.$r->salary_month->translatedFormat('F Y').')';
        $credit = $r->payment_account_id ? $this->paymentAccountLedger($r->payment_account_id) : (int) $r->source_account_id;

        return [
            'date' => $r->payment_date->toDateString(),
            'description' => $desc,
            'reference' => $r->reference,
            'journal_type' => 'remittance',
            'lines' => [
                ['account_id' => (int) $r->liability_account_id, 'debit' => $amount, 'credit' => 0, 'description' => __('ledger_payroll.l_tax_paid_over').' - '.$desc],
                ['account_id' => $credit, 'debit' => 0, 'credit' => $amount, 'description' => __('ledger_payroll.l_payment').' - '.$desc],
            ],
        ];
    }

    // ---------------------------------------------------------------- helpers

    /** When a trip counts as gone: it departed (at the departure time recorded), or it was cancelled and its time has passed. */
    public function leftAt(?BusDeparture $d): ?Carbon
    {
        if (! $d) {
            return null;
        }
        if ($d->status === BusDeparture::DEPARTED) {
            return Carbon::parse($d->departed_at ?? $d->leavesAt());
        }
        if ($d->status === BusDeparture::CANCELLED && $d->leavesAt()->lte(now())) {
            return Carbon::parse($d->leavesAt());
        }

        return null;
    }

    /** Was money for this trip at $at still held for it (the trip had not left)? A move with no trip is a sale. */
    public function heldAt(?BusDeparture $d, Carbon $at): bool
    {
        if (! $d) {
            return false;
        }
        $left = $this->leftAt($d);

        return ! $left || $at->lt($left);
    }

    /** The bus cost centres of a line. */
    public function tags(?BusDeparture $d, ?string $channel): array
    {
        return array_filter([
            'departure_id' => $d ? (string) $d->id : null,
            'vehicle_id' => $d && $d->vehicle_id ? (string) $d->vehicle_id : null,
            'destination_id' => $d && $d->destination_id ? (string) $d->destination_id : null,
            'channel' => $channel,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Fapshi's share of an app payment: the payment's amount less what Fapshi says the agency
     * receives (payment-status revenue). Null when Fapshi gave no figure.
     */
    public function fee(BusMoneyMove $m): ?float
    {
        $payments = BusPayment::where('booking_id', $m->booking_id)->where('status', BusPayment::SUCCESSFUL)->orderBy('id')->get();
        $moves = BusMoneyMove::where('booking_id', $m->booking_id)->whereIn('kind', [BusMoneyMove::SALE, BusMoneyMove::EXTRA])
            ->whereIn('method', ['fapshi', 'company'])->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $i = array_search((int) $m->id, $moves, true);
        $p = $i === false ? null : ($payments[$i] ?? null);
        if (! $p) {
            return null;
        }
        $revenue = $p->raw_response['payment-status']['revenue'] ?? null;
        $amount = $p->raw_response['payment-status']['amount'] ?? $p->amount;
        if (! is_numeric($revenue) || ! is_numeric($amount)) {
            return null;
        }
        $fee = round((float) $amount - (float) $revenue, 2);

        return $fee > 0 && $fee < (float) $amount ? $fee : null;
    }

    private function departure($id): ?BusDeparture
    {
        if (! $id) {
            return null;
        }

        return $this->departures[(string) $id] ??= BusDeparture::with('destination')->find($id);
    }

    /** The account a mapping points at (Mapping settings / Cash accounts), or a clear refusal. */
    private function mapping(string $type, string $key = ''): int
    {
        $k = $type.'|'.$key;
        if (! array_key_exists($k, $this->mapped)) {
            $this->mapped[$k] = DefaultAccount::id($type, $key);
        }
        if (! $this->mapped[$k]) {
            throw new \RuntimeException(__('ledger.err_no_mapping', ['type' => __('ledger.map_'.$type), 'key' => $key]));
        }

        return (int) $this->mapped[$k];
    }
}
