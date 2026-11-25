<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * Which ledger account a kind of document uses (Transaction mappings ›
 * Mapping settings), as paxhitestbed's DefaultAccountMapping. PosInnova keys:
 *
 *   mapping_type        category_key          account used
 *   ------------------  --------------------  ---------------------------------
 *   customers           ''                    411 receivable (debit)
 *   sale_goods          ''                    701 sales of goods (credit)
 *   sale_services       ''                    706 services sold (credit)
 *   sale_other          ''                    707 shipping / other charges (credit)
 *   vat_collected       ''                    443 VAT on sales (credit)
 *   customer_advances   ''                    4191 advances from customers (credit)
 *   suppliers           ''                    401 payable (credit)
 *   purchases           ''                    601 purchases of goods (debit)
 *   purchase_costs      ''                    6015 shipping on purchases (debit)
 *   vat_deductible      ''                    445 VAT on purchases (debit)
 *   expense_category    expense category id   6xx (debit); '' = any other category
 *   expense_payable     ''                    401 / 4718 who an unpaid expense is owed to (credit)
 *   payment_method      cash, card, ...       57 / 52 / mobile money (cash side)
 *   opening_balance     ''                    offset of contact opening balances
 *   rounding            ''                    round-off differences
 */
class DefaultAccountMapping extends Model
{
    use Auditable, BelongsToBusiness;

    protected $fillable = [
        'business_id', 'mapping_type', 'category_key', 'debit_account_id', 'credit_account_id',
        'description', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = ['status' => 'boolean'];

    public function debitAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    public function creditAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }

    /** The account set for $type (and $key), falling back to the type's '' row. */
    public static function accountFor(string $type, string $key = '', string $side = 'debit'): ?int
    {
        $col = $side === 'credit' ? 'credit_account_id' : 'debit_account_id';
        $rows = static::where('mapping_type', $type)->where('status', 1)
            ->whereIn('category_key', array_unique([$key, '']))->get()->keyBy('category_key');
        $row = $rows->get($key) ?: $rows->get('');
        if (! $row) {
            return null;
        }

        return $row->$col ?: ($side === 'credit' ? $row->debit_account_id : $row->credit_account_id);
    }

    public function getAuditDescription($event)
    {
        return "Account mapping {$event}: {$this->mapping_type}".($this->category_key !== '' ? " ({$this->category_key})" : '');
    }
}
