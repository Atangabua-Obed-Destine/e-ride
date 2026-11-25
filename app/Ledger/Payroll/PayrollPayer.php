<?php

namespace App\Ledger\Payroll;

use App\Ledger\Ledger;
use App\Ledger\Models\Payroll;
use App\Ledger\Models\PayrollSetting;
use App\Ledger\Models\TransactionMapping;
use App\Ledger\Services\LedgerSync;
use App\Ledger\Services\PaymentBook;
use App\Ledger\Services\PostingRules;
use Illuminate\Support\Facades\DB;

/**
 * paxhitestbed PayrollPayer (and PayrollController::unpay): paying a payroll, one from its row or
 * many from the list, and reversing a payment. Refusals are RuntimeExceptions meant for the screen.
 *
 * E-Ride: the pay leaves a payment account (its book gets the movement), and the entry is posted
 * by the sync (PostingRules::forPayroll) at once, so the review dialog shows it. Unpaying reverses
 * the entry (never deletes), takes the movement off the book and clears the itemised taxes; when a
 * posted entry cannot be reversed the payroll stays paid (paxhitestbed's rule).
 */
class PayrollPayer
{
    /** @param array{pay_date:string, payment_method:string, payment_account_id:int, note?:?string} $data */
    public function pay(Payroll $payroll, array $data, ?string $byUserId): array
    {
        $settings = PayrollSetting::current();

        return DB::transaction(function () use ($payroll, $data, $byUserId, $settings) {
            $payroll = Payroll::whereKey($payroll->id)->lockForUpdate()->firstOrFail();
            if ($payroll->isPaid()) {
                throw new \RuntimeException(__('ledger_payroll.err_already_paid'));
            }
            if ($settings->require_approval && ! $payroll->isApproved()) {
                throw new \RuntimeException(__('ledger_payroll.err_not_approved'));
            }
            $account = (int) ($data['payment_account_id'] ?? 0);
            if (PaymentBook::available($account) < round((float) $payroll->net_salary, 2) - 0.005) {
                throw new \RuntimeException(__('ledger_payroll.err_insufficient', ['balance' => Ledger::money(PaymentBook::available($account))]));
            }

            $payroll->fill([
                'pay_date' => $data['pay_date'],
                'payment_method' => $data['payment_method'],
                'payment_account_id' => $account,
                'note' => $data['note'] ?? null,
                'status' => 1,
                'updated_by' => $byUserId,
            ])->save();

            // what each tax took, before the entry reads it to split the withholdings by authority
            app(PayrollTaxBreakdownService::class)->record($payroll);
            $payroll->load('staff', 'details');

            PaymentBook::link('payroll', $payroll->id, $account, 'debit', (float) $payroll->net_salary, $payroll->pay_date->toDateString(),
                __('ledger_payroll.book_title', ['name' => $payroll->staff->name ?? '', 'month' => $payroll->salary_month->translatedFormat('F Y')]),
                __('ledger_payroll.net_salary_payment'), $payroll->payment_method, $payroll->staff->staff_no ?? null, $byUserId);

            $result = (new LedgerSync())->record(PostingRules::PAYROLL, $payroll->id);

            return ['payroll' => $payroll, 'journal' => in_array($result, ['posted', 'reposted', 'same'], true), 'why' => $this->why($payroll->id)];
        });
    }

    public function unpay(Payroll $payroll, ?string $byUserId): void
    {
        if (! $payroll->isPaid()) {
            throw new \RuntimeException(__('ledger_payroll.err_already_unpaid'));
        }
        DB::transaction(function () use ($payroll, $byUserId) {
            $map = TransactionMapping::where('transaction_type', PostingRules::PAYROLL)->where('transaction_id', (string) $payroll->id)->first();
            $posted = $map && $map->status === 'posted' && $map->journal_entry_id;

            $payroll->fill(['pay_date' => null, 'payment_method' => null, 'payment_account_id' => null, 'status' => 0, 'updated_by' => $byUserId])->save();
            $result = (new LedgerSync())->record(PostingRules::PAYROLL, $payroll->id);
            if ($posted && $result !== 'reversed') {
                throw new \RuntimeException(__('ledger_payroll.err_unpay_ledger', ['why' => $this->why($payroll->id) ?: $result]));
            }
            PaymentBook::remove('payroll', $payroll->id);
            // an unpaid payroll withheld nothing, so it owes nothing to the State or the CNPS
            app(PayrollTaxBreakdownService::class)->clear($payroll);
        });
    }

    private function why(int $payrollId): ?string
    {
        $map = TransactionMapping::where('transaction_type', PostingRules::PAYROLL)->where('transaction_id', (string) $payrollId)->first();

        return $map && $map->status === 'failed' ? $map->error : null;
    }
}
