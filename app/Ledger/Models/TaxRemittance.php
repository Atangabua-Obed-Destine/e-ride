<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * paxhitestbed TaxRemittance: withheld tax paid over to the body it was withheld for (CNPS, the
 * State), for one salary month, with the receipt number. E-Ride: paid from a payment account.
 * Posts through the sync (PostingRules::forTaxRemittance): Dr the liability / Cr the money.
 * A voided one keeps its row (the audit trail) and its entry is reversed.
 */
class TaxRemittance extends Model
{
    use Auditable, BelongsToBusiness;

    protected $table = 'tax_remittances';

    protected $fillable = [
        'business_id', 'liability_account_id', 'salary_month', 'amount', 'payment_date', 'payment_account_id', 'source_account_id',
        'reference', 'note', 'voided_at', 'void_reason', 'voided_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'salary_month' => 'date',
        'payment_date' => 'date',
        'voided_at' => 'datetime',
        'amount' => 'float',
    ];

    public function liabilityAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'liability_account_id');
    }

    public function sourceAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'source_account_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function voider()
    {
        return $this->belongsTo(\App\User::class, 'voided_by');
    }

    public function scopeLive($query)
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function getAuditDescription($event)
    {
        return "Tax remittance {$event}: ".number_format((float) $this->amount, 0).' ('.($this->reference ?: '-').')';
    }
}
