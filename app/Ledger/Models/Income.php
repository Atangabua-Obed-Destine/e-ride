<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * Money that came in (paxhitestbed Income): a categorised, dated amount. When it
 * names a payment account, the money is recorded on that account's book too
 * (App\Ledger\Services\PaymentBook). It posts through the sync
 * (PostingRules::forIncome): Dr the money's account, Cr the category's income account.
 */
class Income extends Model
{
    use Auditable, BelongsToBusiness;

    protected $table = 'incomes';

    protected $fillable = ['business_id', 'category_id', 'title', 'invoice_id', 'amount', 'date', 'reference', 'payment_method',
        'payment_account_id', 'note', 'attach', 'status', 'created_by', 'updated_by'];

    protected $casts = ['amount' => 'float', 'date' => 'date', 'status' => 'boolean'];

    public function category()
    {
        return $this->belongsTo(IncomeCategory::class, 'category_id')->withTrashed();
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function getAuditDescription($event)
    {
        return "Income {$event}: {$this->title} ({$this->amount})";
    }
}
