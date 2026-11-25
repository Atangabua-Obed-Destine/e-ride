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
 * paxhitestbed PayrollPayer (and PayrollController::approve / unpay), with the payroll posted in two
 * steps (the user's rule, 2026-11-25):
 *
 *   approve   the accrual: Dr 661 / 664, Cr the withholdings, Cr 421 loans, Cr 422 net owed
 *   pay       Dr 422 / Cr the payment account it leaves (its book gets the movement); a payroll
 *             paid without approval (approval not required) gets the accrual too, on its pay date
 *   unpay     reverses the payment (and the accrual of a payroll paid without approval)
 *   unapprove reverses the accrual; refused while the payroll is paid
 *
 * The entries are posted by the sync (PostingRules::forPayroll / forPayrollPayment) at once, so
 * the review dialog shows them. A posted entry that cannot be reversed keeps the payroll as it was.
 * Refusals are RuntimeExceptions meant for the screen.
 */
class PayrollPayer
{
    public function approve(Payroll $payroll, ?string $byUserId): Payroll
    {
        return DB::transaction(function () use ($payroll, $byUserId) {
            $payroll = Payroll::whereKey($payroll->id)->lockForUpdate()->firstOrFail();
            if ($payroll->isPaid()) {
                throw new \RuntimeException(__('ledger_payroll.err_already_paid'));
            }
            if ($payroll->isApproved()) {
                return $payroll;
            }
            $payroll->forceFill(['approved_by' => $byUserId, 'approved_at' => now()])->save();
            // what each tax took, before the accrual reads it to split the withholdings by authority
            app(PayrollTaxBreakdownService::class)->record($payroll);
            $this->sync(PostingRules::PAYROLL, $payroll->id, ['posted', 'reposted', 'same']);

            return $payroll;
        });
    }

    public function unapprove(Payroll $payroll, ?string $byUserId): void
    {
        DB::transaction(function () use ($payroll, $byUserId) {
            $payroll = Payroll::whereKey($payroll->id)->lockForUpdate()->firstOrFail();
            if ($payroll->isPaid()) {
                throw new \RuntimeException(__('ledger_payroll.err_unapprove_paid'));
            }
            if (! $payroll->isApproved()) {
                throw new \RuntimeException(__('ledger_payroll.err_not_approved_yet'));
            }
            $payroll->forceFill(['approved_by' => null, 'approved_at' => null, 'updated_by' => $byUserId])->save();
            $this->sync(PostingRules::PAYROLL, $payroll->id, ['reversed', 'none']);
        });
    }

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
            $payroll->load('staff', 'details');

            if (! $payroll->isApproved()) {
                // paid without approval: the accrual is posted now, on the pay date
                app(PayrollTaxBreakdownService::class)->record($payroll);
            }
            $accrual = (new LedgerSync())->record(PostingRules::PAYROLL, $payroll->id);

            PaymentBook::link('payroll', $payroll->id, $account, 'debit', (float) $payroll->net_salary, $payroll->pay_date->toDateString(),
                __('ledger_payroll.book_title', ['name' => $payroll->staff->name ?? '', 'month' => $payroll->salary_month->translatedFormat('F Y')]),
                __('ledger_payroll.net_salary_payment'), $payroll->payment_method, $payroll->staff->staff_no ?? null, $byUserId);

            $result = (new LedgerSync())->record(PostingRules::PAYROLL_PAYMENT, $payroll->id);
            $posted = in_array($result, ['posted', 'reposted', 'same'], true) && in_array($accrual, ['posted', 'reposted', 'same'], true);

            return ['payroll' => $payroll, 'journal' => $posted, 'why' => $this->why(PostingRules::PAYROLL_PAYMENT, $payroll->id) ?: $this->why(PostingRules::PAYROLL, $payroll->id)];
        });
    }

    public function unpay(Payroll $payroll, ?string $byUserId): void
    {
        if (! $payroll->isPaid()) {
            throw new \RuntimeException(__('ledger_payroll.err_already_unpaid'));
        }
        DB::transaction(function () use ($payroll, $byUserId) {
            $payroll->fill(['pay_date' => null, 'payment_method' => null, 'payment_account_id' => null, 'status' => 0, 'updated_by' => $byUserId])->save();
            $this->sync(PostingRules::PAYROLL_PAYMENT, $payroll->id, ['reversed', 'none']);
            // a payroll paid without approval loses its accrual too; an approved one is owed again (422)
            $this->sync(PostingRules::PAYROLL, $payroll->id, ['reversed', 'none', 'same']);
            PaymentBook::remove('payroll', $payroll->id);
        });
    }

    /** Sync one kind and refuse (rolling back) unless it ended as expected. */
    private function sync(string $kind, int $id, array $expected): void
    {
        $result = (new LedgerSync())->record($kind, $id);
        if (! in_array($result, $expected, true)) {
            throw new \RuntimeException(__('ledger_payroll.err_ledger_step', ['why' => $this->why($kind, $id) ?: $result]));
        }
    }

    private function why(string $kind, int $payrollId): ?string
    {
        $map = TransactionMapping::where('transaction_type', $kind)->where('transaction_id', (string) $payrollId)->first();

        return $map && $map->status === 'failed' ? $map->error : null;
    }
}
