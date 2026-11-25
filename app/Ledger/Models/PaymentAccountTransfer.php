<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * Money moved between two payment accounts (paxhitestbed PaymentAccountTransfer):
 * one debit on the sending account's book, one credit on the receiving one's, and
 * in the ledger Dr the receiving account's ledger account, Cr the sending one's
 * (PostingRules::forPaymentAccountTransfer).
 */
class PaymentAccountTransfer extends Model
{
    use Auditable, BelongsToBusiness;

    protected $fillable = ['business_id', 'from_account_id', 'to_account_id', 'amount', 'transfer_date', 'note', 'attach', 'created_by', 'updated_by'];

    protected $casts = ['amount' => 'float', 'transfer_date' => 'date'];

    public function fromAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'to_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function getAuditDescription($event)
    {
        return "Fund transfer {$event}: {$this->amount}";
    }
}
