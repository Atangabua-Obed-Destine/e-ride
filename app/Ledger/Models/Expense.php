<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * Money that went out (paxhitestbed Expense, without its school budget columns).
 * Posts through the sync (PostingRules::forExpense): Dr the category's expense
 * account, Cr the money's account.
 */
class Expense extends Model
{
    use Auditable, BelongsToBusiness;

    protected $table = 'expenses';

    protected $fillable = ['business_id', 'category_id', 'title', 'invoice_id', 'amount', 'date', 'reference', 'payment_method',
        'payment_account_id', 'note', 'attach', 'status', 'created_by', 'updated_by'];

    protected $casts = ['amount' => 'float', 'date' => 'date', 'status' => 'boolean'];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id')->withTrashed();
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
        return "Expense {$event}: {$this->title} ({$this->amount})";
    }
}
