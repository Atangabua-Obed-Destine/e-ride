<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * One movement on a payment account's book (paxhitestbed PaymentAccountTransaction):
 * credit = money in, debit = money out, balance_after = the running balance.
 *
 * reference_type / reference_id point back at the record it came from (one token
 * per source, paxhitestbed's gotcha #2): income, expense, transfer, supplier_payment,
 * supplier_payment_reversal; null for a manual deposit or withdrawal and
 * 'opening_balance' for an account's opening balance. Only those manual ones post
 * by themselves (PostingRules::forPaymentAccountTransaction, when the other side,
 * contra_account_id, is given); the others post through their own record.
 */
class PaymentAccountTransaction extends Model
{
    use Auditable, BelongsToBusiness;

    const MANUAL = [null, 'opening_balance'];

    const LINKED = ['income', 'expense', 'transfer', 'supplier_payment', 'supplier_payment_reversal'];

    protected $fillable = ['business_id', 'payment_account_id', 'transaction_type', 'amount', 'transaction_date', 'title', 'description',
        'reference_type', 'reference_id', 'payment_method', 'payment_reference', 'balance_after', 'contra_account_id', 'attach', 'created_by'];

    protected $casts = ['amount' => 'float', 'balance_after' => 'float', 'transaction_date' => 'date'];

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function contraAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'contra_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function isManual(): bool
    {
        return $this->reference_type === null || $this->reference_type === 'opening_balance';
    }

    /** The record it came from (paxhitestbed reference()). */
    public function reference()
    {
        $class = match ($this->reference_type) {
            'income' => Income::class,
            'expense' => Expense::class,
            'transfer' => PaymentAccountTransfer::class,
            'supplier_payment', 'supplier_payment_reversal' => SupplierPayment::class,
            default => null,
        };

        return $class && $this->reference_id ? $class::find($this->reference_id) : null;
    }

    public function getAuditDescription($event)
    {
        return "Payment account transaction {$event}: {$this->transaction_type} {$this->amount}";
    }
}
