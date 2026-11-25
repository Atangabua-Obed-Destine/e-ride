<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * A real container of money (paxhitestbed PaymentAccount): a cash box, a bank
 * account, a mobile-money wallet. chart_of_account_id is its class-5 ledger
 * account: money recorded through it posts there. current_balance is the
 * running balance of its transactions (PaymentBook::recompute keeps it).
 */
class PaymentAccount extends Model
{
    use Auditable, BelongsToBusiness;

    protected $fillable = ['business_id', 'title', 'account_number', 'account_type_id', 'chart_of_account_id', 'opening_balance',
        'current_balance', 'description', 'status', 'created_by', 'updated_by'];

    protected $casts = ['opening_balance' => 'float', 'current_balance' => 'float', 'status' => 'boolean'];

    public function accountType()
    {
        return $this->belongsTo(PaymentAccountType::class, 'account_type_id');
    }

    public function ledgerAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function transactions()
    {
        return $this->hasMany(PaymentAccountTransaction::class, 'payment_account_id')->orderByDesc('transaction_date')->orderByDesc('id');
    }

    public function transfersFrom()
    {
        return $this->hasMany(PaymentAccountTransfer::class, 'from_account_id');
    }

    public function transfersTo()
    {
        return $this->hasMany(PaymentAccountTransfer::class, 'to_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(\App\User::class, 'updated_by');
    }

    public function getAuditDescription($event)
    {
        return "Payment account {$event}: {$this->title}";
    }
}
