<?php

namespace App\Ledger;

use App\Ledger\Models\Expense;
use App\Ledger\Models\Income;
use App\Ledger\Models\PaymentAccountTransaction;
use App\Ledger\Models\PaymentAccountTransfer;
use App\Ledger\Models\SupplierBill;
use App\Ledger\Models\SupplierPayment;
use App\Ledger\Services\LedgerSync;
use App\Ledger\Services\PostingRules;

/**
 * paxhitestbed's IncomeObserver / ExpenseObserver (and the transfer and supplier
 * postings), on E-Ride's sync: a save or delete of a record that posts queues it,
 * and LedgerSync posts, re-posts or reverses it once the request's own database
 * work is done. The screens also sync at once (so the review dialog shows the
 * entry); this catches every other way a record changes.
 */
class AccountsObserver
{
    const KINDS = [
        Income::class => PostingRules::INCOME,
        Expense::class => PostingRules::EXPENSE,
        SupplierBill::class => PostingRules::SUPPLIER_BILL,
        SupplierPayment::class => PostingRules::SUPPLIER_PAYMENT,
        PaymentAccountTransfer::class => PostingRules::PAYMENT_TRANSFER,
        PaymentAccountTransaction::class => PostingRules::PAYMENT_ACCOUNT_TXN,
    ];

    public function saved($model)
    {
        $this->queue($model);
    }

    public function deleted($model)
    {
        $this->queue($model);
    }

    private function queue($model): void
    {
        try {
            $kind = self::KINDS[get_class($model)] ?? null;
            if (! $kind || empty($model->id) || empty($model->business_id)) {
                return;
            }
            if ($model instanceof PaymentAccountTransaction && ! $model->isManual()) {
                return; // it posts through the income, expense, transfer or payment it belongs to
            }
            LedgerSync::queue($kind, (int) $model->id, (int) $model->business_id);
        } catch (\Throwable $e) {
            \Log::error('Ledger accounts observer: '.$e->getMessage());
        }
    }

    public static function register(): void
    {
        foreach (array_keys(self::KINDS) as $class) {
            $class::observe(self::class);
        }
    }
}
