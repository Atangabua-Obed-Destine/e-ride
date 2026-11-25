<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\DefaultAccountMapping;
use App\Ledger\Models\ExpenseCategory;
use App\Ledger\Models\ItemSupplier;
use App\Ledger\Models\JournalEntry;
use App\Ledger\Models\PaymentAccount;
use App\Ledger\Models\SupplierBill;
use App\Ledger\Models\SupplierBillLine;
use App\Ledger\Models\SupplierPayment;
use App\Ledger\Models\SupplierPaymentAllocation;
use App\Ledger\Models\TransactionMapping;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Supplier bills and payments: every rule, in one place (paxhitestbed
 * App\Services\Accounting\SupplierPayables, ported to the E-Ride books).
 *
 * The ledger (PostingRules::forSupplierBill / forSupplierPayment, through LedgerSync)
 *   approve a bill   Dr each line's expense account (+ 4452 recoverable VAT), Cr 401 (the debt)
 *   void a bill      the same entry reversed
 *   pay              Dr 401, Cr the bank or cash account
 *   void a payment   the same entry reversed
 *
 * Approving and paying post in the same database transaction as the record: if
 * the entry is refused (no mapping, a closed month) the bill stays a draft and
 * the payment is not recorded, as in paxhitestbed.
 *
 * What is owed: one definition, owedQuery(): a bill's total less its
 * allocations from payments that have not been voided. No stored "paid" flag.
 */
class SupplierPayables
{
    public const MAPPING_TYPE = 'expense_category';

    private const CENT = 0.005;

    /* ------------------------------------------------------------ settings */

    /** The liability account supplier debts are owed on: the 'suppliers' mapping (401 as installed). */
    public function payableAccountId(): ?int
    {
        return DefaultAccount::id('suppliers');
    }

    public function setPayableAccount(int $accountId, ?string $userId): void
    {
        $account = ChartOfAccount::find($accountId);
        if (! $account || (int) $account->class_number !== 4 || $account->account_category !== 'detail') {
            throw new \RuntimeException(__('ledger_accounts.err_payable_class4'));
        }
        $row = DefaultAccountMapping::where('mapping_type', 'suppliers')->where('category_key', '')->first()
            ?: new DefaultAccountMapping(['mapping_type' => 'suppliers', 'category_key' => '', 'created_by' => $userId]);
        $row->fill(['debit_account_id' => $accountId, 'credit_account_id' => $accountId, 'status' => 1, 'updated_by' => $userId])->save();
    }

    public function payableAccounts()
    {
        return ChartOfAccount::postable()->where('class_number', 4)->orderBy('account_code')->get();
    }

    /** Accounts an opening balance may be set against (equity, class 1). */
    public function openingBalanceAccounts()
    {
        return ChartOfAccount::postable()->where('class_number', 1)->orderBy('account_code')->get();
    }

    /** Bank and cash accounts a supplier may be paid from. */
    public function cashAccounts()
    {
        return PaymentBook::cashLedgerAccounts();
    }

    /* ------------------------------------------------------------ what is owed */

    /** Bills with what has been paid on them (column paid). With $asOf: as the ledger stood that day. */
    public function owedQuery(?string $asOf = null): Builder
    {
        $bid = Ledger::businessId();
        $paid = DB::table('supplier_payment_allocations as a')
            ->join('supplier_payments as p', 'p.id', '=', 'a.supplier_payment_id')
            ->whereColumn('a.supplier_bill_id', 'b.id');
        if ($asOf) {
            $paid->whereDate('p.payment_date', '<=', $asOf)
                ->where(fn ($q) => $q->whereNull('p.voided_at')->orWhereDate('p.voided_at', '>', $asOf));
        } else {
            $paid->whereNull('p.voided_at');
        }
        $paid->selectRaw('COALESCE(SUM(a.amount), 0)');

        $query = DB::table('supplier_bills as b')->where('b.business_id', $bid)
            ->leftJoin('item_suppliers as s', 's.id', '=', 'b.supplier_id')
            ->select('b.*', 's.title as supplier_name')
            ->selectSub($paid, 'paid');
        if ($asOf) {
            $query->whereDate('b.bill_date', '<=', $asOf)->whereNotNull('b.approved_at')->whereDate('b.approved_at', '<=', $asOf)
                ->where(fn ($q) => $q->where('b.status', SupplierBill::APPROVED)
                    ->orWhere(fn ($q) => $q->where('b.status', SupplierBill::VOID)->whereDate('b.voided_at', '>', $asOf)));
        } else {
            $query->where('b.status', SupplierBill::APPROVED);
        }

        return $query;
    }

    /** The bills with something still owed, oldest due first. */
    public function openBills(?int $supplierId = null, ?string $asOf = null)
    {
        return $this->owedQuery($asOf)
            ->when($supplierId, fn ($q) => $q->where('b.supplier_id', $supplierId))
            ->orderBy('b.due_date')->orderBy('b.bill_date')->orderBy('b.id')
            ->get()
            ->map(function ($bill) {
                $bill->paid = round((float) $bill->paid, 2);
                $bill->owed = round((float) $bill->total - $bill->paid, 2);

                return $bill;
            })
            ->filter(fn ($bill) => abs($bill->owed) > self::CENT)
            ->values();
    }

    public function owedOn(SupplierBill $bill): float
    {
        if (! $bill->isApproved()) {
            return 0.0;
        }
        $paid = (float) SupplierPaymentAllocation::where('supplier_bill_id', $bill->id)
            ->whereHas('payment', fn ($q) => $q->whereNull('voided_at'))->sum('amount');

        return round((float) $bill->total - $paid, 2);
    }

    /** 'draft', 'unpaid', 'part_paid', 'paid' or 'void', worked out from the payments. */
    public function paymentState(SupplierBill $bill, ?float $owed = null): string
    {
        if ($bill->isDraft()) {
            return 'draft';
        }
        if ($bill->isVoid()) {
            return 'void';
        }
        $owed ??= $this->owedOn($bill);
        if ($owed <= self::CENT) {
            return 'paid';
        }

        return $owed < (float) $bill->total - self::CENT ? 'part_paid' : 'unpaid';
    }

    /* ------------------------------------------------------------ bills */

    /**
     * Create or update a draft bill. $data: supplier_id, kind, supplier_reference, bill_date,
     * due_date (empty: from the supplier's terms), description, vat_amount, attach,
     * opening_account_id (opening balance only), lines [[description, expense_category_id, amount]].
     */
    public function saveDraft(array $data, ?string $userId, ?SupplierBill $bill = null): SupplierBill
    {
        if ($bill && ! $bill->isDraft()) {
            throw new \RuntimeException(__('ledger_accounts.err_only_draft_change'));
        }
        $supplier = ItemSupplier::find($data['supplier_id'] ?? null);
        if (! $supplier) {
            throw new \RuntimeException(__('ledger_accounts.err_choose_supplier'));
        }
        $kind = ($data['kind'] ?? SupplierBill::KIND_BILL) === SupplierBill::KIND_OPENING ? SupplierBill::KIND_OPENING : SupplierBill::KIND_BILL;
        $billDate = Carbon::parse($data['bill_date'])->toDateString();
        $dueDate = ! empty($data['due_date']) ? Carbon::parse($data['due_date'])->toDateString()
            : Carbon::parse($billDate)->addDays((int) ($supplier->payment_terms_days ?? 0))->toDateString();
        if ($dueDate < $billDate) {
            throw new \RuntimeException(__('ledger_accounts.err_due_before_bill'));
        }
        $lines = $this->cleanLines($data, $kind);
        $vat = $kind === SupplierBill::KIND_OPENING ? 0.0 : round(max(0, (float) ($data['vat_amount'] ?? 0)), 2);

        return DB::transaction(function () use ($data, $userId, $bill, $supplier, $kind, $billDate, $dueDate, $lines, $vat) {
            $total = round(array_sum(array_column($lines, 'amount')) + $vat, 2);
            $bill ??= new SupplierBill([
                'number' => $this->nextNumber('SB', 'supplier_bills', $billDate),
                'status' => SupplierBill::DRAFT,
                'created_by' => $userId,
            ]);
            $bill->fill([
                'supplier_id' => $supplier->id,
                'kind' => $kind,
                'supplier_reference' => trim((string) ($data['supplier_reference'] ?? '')) ?: null,
                'bill_date' => $billDate,
                'due_date' => $dueDate,
                'description' => $data['description'] ?? null,
                'vat_amount' => $vat,
                'total' => $total,
                'updated_by' => $userId,
            ]);
            if (! empty($data['attach'])) {
                $bill->attach = $data['attach'];
            }
            $bill->save();
            $bill->lines()->delete();
            foreach ($lines as $i => $line) {
                SupplierBillLine::create($line + ['supplier_bill_id' => $bill->id, 'line_number' => $i + 1]);
            }

            return $bill->fresh('lines');
        });
    }

    private function cleanLines(array $data, string $kind): array
    {
        if ($kind === SupplierBill::KIND_OPENING) {
            $amount = round((float) ($data['lines'][0]['amount'] ?? 0), 2);
            $account = ChartOfAccount::find($data['opening_account_id'] ?? null);
            if ($amount <= 0) {
                throw new \RuntimeException(__('ledger_accounts.err_opening_zero'));
            }
            if (! $account || (int) $account->class_number !== 1 || $account->account_category !== 'detail') {
                throw new \RuntimeException(__('ledger_accounts.err_opening_account'));
            }

            return [['description' => ($data['lines'][0]['description'] ?? null) ?: __('ledger_accounts.opening_balance'),
                'expense_category_id' => null, 'account_id' => $account->id, 'amount' => $amount]];
        }
        $lines = [];
        $categories = ExpenseCategory::where('status', 1)->pluck('id')->flip();
        foreach ($data['lines'] ?? [] as $line) {
            $amount = round((float) ($line['amount'] ?? 0), 2);
            $categoryId = $line['expense_category_id'] ?? null;
            $description = trim((string) ($line['description'] ?? ''));
            if ($amount == 0.0 && ! $categoryId && $description === '') {
                continue; // a row the form added and left empty
            }
            if ($amount <= 0) {
                throw new \RuntimeException(__('ledger_accounts.err_line_amount'));
            }
            if (! $categoryId || ! isset($categories[(int) $categoryId])) {
                throw new \RuntimeException(__('ledger_accounts.err_line_category'));
            }
            $lines[] = ['description' => $description ?: null, 'expense_category_id' => (int) $categoryId, 'account_id' => null, 'amount' => $amount];
        }
        if (! $lines) {
            throw new \RuntimeException(__('ledger_accounts.err_bill_no_lines'));
        }

        return $lines;
    }

    public function deleteDraft(SupplierBill $bill): void
    {
        if (! $bill->isDraft()) {
            throw new \RuntimeException(__('ledger_accounts.err_only_draft_delete'));
        }
        DB::transaction(function () use ($bill) {
            $bill->lines()->delete();
            $bill->delete();
        });
    }

    /** Another live bill from the same supplier under the same invoice number, if any. */
    public function duplicateOf(SupplierBill $bill): ?SupplierBill
    {
        if (! $bill->supplier_reference) {
            return null;
        }

        return SupplierBill::where('supplier_id', $bill->supplier_id)->where('id', '!=', $bill->id)
            ->where('status', '!=', SupplierBill::VOID)
            ->whereRaw('LOWER(TRIM(supplier_reference)) = ?', [mb_strtolower(trim($bill->supplier_reference))])->first();
    }

    /** Approve a draft: post the cost and the debt on the bill date. */
    public function approve(SupplierBill $bill, ?string $userId): SupplierBill
    {
        return DB::transaction(function () use ($bill, $userId) {
            $bill = SupplierBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if (! $bill->isDraft()) {
                throw new \RuntimeException(__('ledger_accounts.err_not_draft'));
            }
            $lines = $bill->lines()->with('category')->get();
            if ($lines->isEmpty() || (float) $bill->total <= 0) {
                throw new \RuntimeException(__('ledger_accounts.err_bill_no_lines'));
            }
            if (abs(round((float) $lines->sum('amount') + (float) $bill->vat_amount, 2) - (float) $bill->total) > self::CENT) {
                throw new \RuntimeException(__('ledger_accounts.err_lines_total'));
            }
            if ($duplicate = $this->duplicateOf($bill)) {
                throw new \RuntimeException(__('ledger_accounts.err_duplicate', ['ref' => $bill->supplier_reference, 'number' => $duplicate->number]));
            }
            $payable = $this->payableAccountId();
            if (! $payable) {
                throw new \RuntimeException(__('ledger_accounts.err_no_payable'));
            }
            // each line's account, decided now and kept
            foreach ($lines as $line) {
                if ($bill->isOpeningBalance()) {
                    continue;
                }
                $accountId = DefaultAccount::id(self::MAPPING_TYPE, (string) $line->expense_category_id);
                $account = $accountId ? ChartOfAccount::find($accountId) : null;
                if (! $account || in_array((int) $account->class_number, [4, 5], true)) {
                    throw new \RuntimeException(__('ledger_accounts.err_category_no_account', ['category' => $line->category->name ?? '#'.$line->expense_category_id]));
                }
                $line->forceFill(['account_id' => $account->id])->save();
            }
            $bill->forceFill(['status' => SupplierBill::APPROVED, 'payable_account_id' => $payable, 'approved_by' => $userId, 'approved_at' => now()])->save();
            $bill->forceFill(['journal_entry_id' => $this->post(PostingRules::SUPPLIER_BILL, $bill->id)])->save();

            return $bill;
        });
    }

    /** Void an approved bill: its entry is reversed. Refused while a live payment is allocated to it. */
    public function voidBill(SupplierBill $bill, ?string $userId, ?string $reason = null): SupplierBill
    {
        return DB::transaction(function () use ($bill, $userId, $reason) {
            $bill = SupplierBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if (! $bill->isApproved()) {
                throw new \RuntimeException(__('ledger_accounts.err_only_approved_void'));
            }
            $paid = SupplierPaymentAllocation::where('supplier_bill_id', $bill->id)->whereHas('payment', fn ($q) => $q->whereNull('voided_at'))->count();
            if ($paid > 0) {
                throw new \RuntimeException(__('ledger_accounts.err_void_payments_first'));
            }
            $bill->forceFill(['status' => SupplierBill::VOID, 'voided_by' => $userId, 'voided_at' => now(), 'void_reason' => $reason])->save();
            $this->post(PostingRules::SUPPLIER_BILL, $bill->id, true);
            $bill->forceFill(['void_journal_entry_id' => $this->reversalOf($bill->journal_entry_id)])->save();

            return $bill;
        });
    }

    /* ------------------------------------------------------------ payments */

    /**
     * Pay a supplier. $data: supplier_id, payment_date, amount, payment_method, cash_account_id
     * and/or payment_account_id, reference, note, allocations [bill_id => amount] (optional: without
     * it the oldest bills due are paid first).
     */
    public function pay(array $data, ?string $userId): SupplierPayment
    {
        $supplier = ItemSupplier::find($data['supplier_id'] ?? null);
        if (! $supplier) {
            throw new \RuntimeException(__('ledger_accounts.err_choose_supplier'));
        }
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \RuntimeException(__('ledger_accounts.err_payment_zero'));
        }
        $date = Carbon::parse($data['payment_date'])->toDateString();
        if ($date > now()->toDateString()) {
            throw new \RuntimeException(__('ledger_accounts.err_payment_future'));
        }
        [$cashAccountId, $paymentAccount] = $this->resolveCashSide($data, $amount);

        return DB::transaction(function () use ($data, $userId, $supplier, $amount, $date, $cashAccountId, $paymentAccount) {
            SupplierBill::where('supplier_id', $supplier->id)->where('status', SupplierBill::APPROVED)->lockForUpdate()->get(['id']);
            $open = $this->openBills($supplier->id)->keyBy('id');
            $allocations = $this->allocate($open, $amount, $data['allocations'] ?? null);
            foreach ($allocations as $billId => $part) {
                if ($open[$billId]->bill_date > $date) {
                    throw new \RuntimeException(__('ledger_accounts.err_bill_after_payment', ['number' => $open[$billId]->number]));
                }
            }
            $payment = SupplierPayment::create([
                'supplier_id' => $supplier->id,
                'number' => $this->nextNumber('SP', 'supplier_payments', $date),
                'payment_date' => $date,
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? null,
                'cash_account_id' => $cashAccountId,
                'payment_account_id' => $paymentAccount->id ?? null,
                'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);
            foreach ($allocations as $billId => $part) {
                SupplierPaymentAllocation::create(['supplier_payment_id' => $payment->id, 'supplier_bill_id' => $billId, 'amount' => $part]);
            }
            if ($paymentAccount) {
                $tx = PaymentBook::link('supplier_payment', $payment->id, $paymentAccount->id, 'debit', $amount, $date,
                    __('ledger_accounts.doc_supplier_payment').' - '.$supplier->title, $payment->number, $payment->payment_method, $payment->reference, $userId);
                $payment->payment_account_transaction_id = $tx->id;
            }
            $payment->journal_entry_id = $this->post(PostingRules::SUPPLIER_PAYMENT, $payment->id);
            $payment->save();

            return $payment->fresh('allocations');
        });
    }

    /** @return array<int, float> bill id => amount */
    private function allocate($open, float $amount, ?array $requested): array
    {
        $owedTotal = round($open->sum('owed'), 2);
        if ($requested !== null) {
            $out = [];
            foreach ($requested as $billId => $part) {
                $part = round((float) $part, 2);
                if ($part == 0.0) {
                    continue;
                }
                if ($part < 0) {
                    throw new \RuntimeException(__('ledger_accounts.err_alloc_negative'));
                }
                if (! isset($open[$billId])) {
                    throw new \RuntimeException(__('ledger_accounts.err_alloc_not_open'));
                }
                if ($part > $open[$billId]->owed + self::CENT) {
                    throw new \RuntimeException(__('ledger_accounts.err_alloc_too_much', ['number' => $open[$billId]->number, 'owed' => Ledger::money($open[$billId]->owed)]));
                }
                $out[(int) $billId] = $part;
            }
            if (! $out) {
                throw new \RuntimeException(__('ledger_accounts.err_alloc_none'));
            }
            if (abs(round(array_sum($out), 2) - $amount) > self::CENT) {
                throw new \RuntimeException(__('ledger_accounts.err_alloc_sum', ['allocated' => Ledger::money(array_sum($out)), 'amount' => Ledger::money($amount)]));
            }

            return $out;
        }
        if ($amount > $owedTotal + self::CENT) {
            throw new \RuntimeException(__('ledger_accounts.err_pay_more_than_owed', ['owed' => Ledger::money($owedTotal)]));
        }
        $out = [];
        $left = $amount;
        foreach ($open as $bill) {
            if ($left <= self::CENT) {
                break;
            }
            $part = round(min($left, $bill->owed), 2);
            $out[(int) $bill->id] = $part;
            $left = round($left - $part, 2);
        }

        return $out;
    }

    /** @return array{0:int, 1:?PaymentAccount} the ledger account the money leaves, and the payment account when one is used */
    private function resolveCashSide(array $data, float $amount): array
    {
        $paymentAccount = ! empty($data['payment_account_id']) ? PaymentAccount::find($data['payment_account_id']) : null;
        if (! empty($data['payment_account_id']) && ! $paymentAccount) {
            throw new \RuntimeException(__('ledger_accounts.err_account_gone'));
        }
        if ($paymentAccount) {
            $linked = PaymentBook::isCash($paymentAccount->chart_of_account_id) ? (int) $paymentAccount->chart_of_account_id : null;
            if (! $linked) {
                throw new \RuntimeException(__('ledger_accounts.err_account_unlinked', ['name' => $paymentAccount->title]));
            }
            if (! empty($data['cash_account_id']) && (int) $data['cash_account_id'] !== $linked) {
                throw new \RuntimeException(__('ledger_accounts.err_account_other_ledger', ['name' => $paymentAccount->title]));
            }
            if ((float) $paymentAccount->current_balance < $amount - self::CENT) {
                throw new \RuntimeException(__('ledger_accounts.insufficient_balance_in_account'));
            }

            return [$linked, $paymentAccount];
        }
        if (empty($data['cash_account_id']) || ! PaymentBook::isCash($data['cash_account_id'])) {
            throw new \RuntimeException(__('ledger_accounts.err_choose_cash_account'));
        }

        return [(int) $data['cash_account_id'], null];
    }

    /** Void a payment: its entry is reversed, the money goes back on its payment account, the bills are owed again. */
    public function voidPayment(SupplierPayment $payment, ?string $userId, ?string $reason = null): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $userId, $reason) {
            $payment = SupplierPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->isVoid()) {
                throw new \RuntimeException(__('ledger_accounts.err_payment_already_void'));
            }
            $payment->forceFill(['voided_by' => $userId, 'voided_at' => now(), 'void_reason' => $reason])->save();
            $this->post(PostingRules::SUPPLIER_PAYMENT, $payment->id, true);
            $reversal = $this->reversalOf($payment->journal_entry_id);
            if ($payment->payment_account_id && $payment->payment_account_transaction_id) {
                PaymentBook::link('supplier_payment_reversal', $payment->id, $payment->payment_account_id, 'credit', (float) $payment->amount,
                    $reversal ? JournalEntry::find($reversal)->entry_date->toDateString() : now()->toDateString(),
                    __('ledger_accounts.reversal').': '.__('ledger_accounts.doc_supplier_payment').' '.$payment->number, $reason,
                    $payment->payment_method, $payment->reference, $userId);
            }
            $payment->forceFill(['void_journal_entry_id' => $reversal])->save();

            return $payment;
        });
    }

    /* ------------------------------------------------------------ reports */

    public const BUCKETS = ['not_due', 'd1_30', 'd31_60', 'd61_90', 'over_90'];

    /** What is owed to each supplier on a date, by how overdue it is. */
    public function aging(?string $asOf = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf)->toDateString() : now()->toDateString();
        $bills = $this->openBills(null, $asOf);
        $suppliers = [];
        $totals = array_fill_keys(self::BUCKETS, 0.0) + ['total' => 0.0];
        foreach ($bills as $bill) {
            $days = (int) Carbon::parse($bill->due_date)->diffInDays(Carbon::parse($asOf), false);
            $bucket = match (true) {
                $days <= 0 => 'not_due',
                $days <= 30 => 'd1_30',
                $days <= 60 => 'd31_60',
                $days <= 90 => 'd61_90',
                default => 'over_90',
            };
            $bill->days_overdue = max(0, $days);
            $bill->bucket = $bucket;
            $id = (int) $bill->supplier_id;
            $suppliers[$id] ??= ['supplier_id' => $id, 'name' => $bill->supplier_name ?? '#'.$id, 'bills' => []]
                + array_fill_keys(self::BUCKETS, 0.0) + ['total' => 0.0];
            $suppliers[$id][$bucket] = round($suppliers[$id][$bucket] + $bill->owed, 2);
            $suppliers[$id]['total'] = round($suppliers[$id]['total'] + $bill->owed, 2);
            $suppliers[$id]['bills'][] = $bill;
            $totals[$bucket] = round($totals[$bucket] + $bill->owed, 2);
            $totals['total'] = round($totals['total'] + $bill->owed, 2);
        }
        uasort($suppliers, fn ($a, $b) => $b['total'] <=> $a['total']);
        $ledger = $this->ledgerBalance($asOf);

        return ['as_of' => $asOf, 'suppliers' => array_values($suppliers), 'totals' => $totals,
            'ledger_balance' => $ledger, 'difference' => round($ledger - $totals['total'], 2)];
    }

    /**
     * The balance of the supplier account(s) in the ledger on a date, from the entries of
     * supplier bills and payments and anything typed there by hand. E-Ride: 401 also holds
     * what is owed to E-Ride (its monthly statement), so the bus statement entries are left out.
     */
    public function ledgerBalance(?string $asOf = null): float
    {
        $accounts = array_values(array_unique(array_filter(array_merge([$this->payableAccountId()],
            SupplierBill::whereNotNull('payable_account_id')->distinct()->pluck('payable_account_id')->all()))));
        if (! $accounts) {
            return 0.0;
        }

        return round((float) DB::table('journal_entry_lines as l')
            ->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->where('j.business_id', Ledger::businessId())
            ->whereIn('l.account_id', $accounts)
            ->where('j.is_posted', 1)->whereNull('j.deleted_at')
            ->where(fn ($q) => $q->whereNull('j.reference_type')->orWhereNotIn('j.reference_type', [PostingRules::STATEMENT, PostingRules::STATEMENT_PAID]))
            ->when($asOf, fn ($q) => $q->whereDate('j.entry_date', '<=', $asOf))
            ->sum(DB::raw('l.credit - l.debit')), 2);
    }

    /** One supplier's account: bills, payments and their reversals, with a running balance. */
    public function statement(int $supplierId, ?string $from = null, ?string $to = null): array
    {
        $to = $to ? Carbon::parse($to)->toDateString() : now()->toDateString();
        $events = [];
        foreach (SupplierBill::where('supplier_id', $supplierId)->whereNotNull('approved_at')->get() as $bill) {
            $events[] = ['date' => $bill->bill_date->toDateString(), 'order' => 1, 'type' => 'bill', 'number' => $bill->number,
                'reference' => $bill->supplier_reference, 'description' => $bill->isOpeningBalance() ? __('ledger_accounts.opening_balance') : ($bill->description ?: __('ledger_accounts.doc_supplier_bill')),
                'due_date' => $bill->due_date->toDateString(), 'billed' => (float) $bill->total, 'paid' => 0.0, 'id' => $bill->id, 'link' => 'bill'];
            if ($bill->isVoid() && $bill->voided_at) {
                $events[] = ['date' => $bill->voided_at->toDateString(), 'order' => 2, 'type' => 'bill_void', 'number' => $bill->number,
                    'reference' => $bill->supplier_reference, 'description' => __('ledger_accounts.bill_voided').($bill->void_reason ? ': '.$bill->void_reason : ''),
                    'due_date' => null, 'billed' => -(float) $bill->total, 'paid' => 0.0, 'id' => $bill->id, 'link' => 'bill'];
            }
        }
        foreach (SupplierPayment::with('allocations.bill')->where('supplier_id', $supplierId)->get() as $payment) {
            $paidBills = $payment->allocations->map(fn ($a) => $a->bill->number ?? '')->filter()->implode(', ');
            $events[] = ['date' => $payment->payment_date->toDateString(), 'order' => 3, 'type' => 'payment', 'number' => $payment->number,
                'reference' => $payment->reference, 'description' => __('ledger_accounts.payment').($paidBills ? ' - '.$paidBills : ''),
                'due_date' => null, 'billed' => 0.0, 'paid' => (float) $payment->amount, 'id' => $payment->id, 'link' => 'payment'];
            if ($payment->voided_at) {
                $events[] = ['date' => $payment->voided_at->toDateString(), 'order' => 4, 'type' => 'payment_void', 'number' => $payment->number,
                    'reference' => $payment->reference, 'description' => __('ledger_accounts.payment_voided').($payment->void_reason ? ': '.$payment->void_reason : ''),
                    'due_date' => null, 'billed' => 0.0, 'paid' => -(float) $payment->amount, 'id' => $payment->id, 'link' => 'payment'];
            }
        }
        usort($events, fn ($a, $b) => [$a['date'], $a['order'], $a['id']] <=> [$b['date'], $b['order'], $b['id']]);
        $opening = 0.0;
        $rows = [];
        $balance = 0.0;
        $billed = 0.0;
        $paidTotal = 0.0;
        foreach ($events as $e) {
            if ($e['date'] > $to) {
                continue;
            }
            $change = $e['billed'] - $e['paid'];
            if ($from && $e['date'] < $from) {
                $opening = round($opening + $change, 2);

                continue;
            }
            if (! $rows) {
                $balance = $opening;
            }
            $balance = round($balance + $change, 2);
            $billed = round($billed + $e['billed'], 2);
            $paidTotal = round($paidTotal + $e['paid'], 2);
            $rows[] = $e + ['balance' => $balance];
        }

        return ['from' => $from, 'to' => $to, 'opening' => $opening, 'rows' => $rows, 'billed' => $billed, 'paid' => $paidTotal,
            'closing' => round($opening + $billed - $paidTotal, 2)];
    }

    /* ------------------------------------------------------------ plumbing */

    /**
     * Post (or reverse) a record now, through the sync, and refuse when the entry is refused,
     * so the bill or payment and its entry stand or fall together. Returns the entry's id.
     */
    private function post(string $kind, int $id, bool $reversing = false): ?int
    {
        $result = (new LedgerSync())->record($kind, $id);
        $map = TransactionMapping::where('transaction_type', $kind)->where('transaction_id', (string) $id)->first();
        if ($result === 'failed') {
            throw new \RuntimeException($map->error ?? __('ledger_accounts.err_not_posted'));
        }
        if (! $reversing && ! in_array($result, ['posted', 'reposted', 'same'], true)) {
            throw new \RuntimeException(__('ledger_accounts.err_not_posted'));
        }

        return $map && $map->journal_entry_id ? (int) $map->journal_entry_id : null;
    }

    /** The entry that reversed $entryId. */
    private function reversalOf(?int $entryId): ?int
    {
        return $entryId ? (JournalEntry::where('reversed_entry_id', $entryId)->value('id') ?: null) : null;
    }

    /** SB-2026-0001 / SP-2026-0001, by the year of the document's own date, per set of books. */
    private function nextNumber(string $prefix, string $table, string $date): string
    {
        $head = $prefix.'-'.Carbon::parse($date)->year.'-';
        $max = DB::table($table)->where('business_id', Ledger::businessId())->where('number', 'like', $head.'%')->lockForUpdate()->pluck('number')
            ->map(fn ($n) => (int) substr($n, strlen($head)))->max() ?? 0;

        return $head.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
