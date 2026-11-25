<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\BelongsToBusiness;
use App\Ledger\Ledger;
use Illuminate\Database\Eloquent\Model;

/**
 * paxhitestbed PayrollSetting, one row per set of books. Its staff-advances account is the
 * 'staff_advances' mapping here (Mapping settings, also set from the Payroll settings screen), and
 * the account loans are paid out of is a payment account. Its payslip-email and staff
 * self-service switches are not ported.
 */
class PayrollSetting extends Model
{
    use BelongsToBusiness;

    protected $table = 'payroll_settings';

    protected $fillable = [
        'business_id', 'require_approval', 'carry_over_lines', 'prorate_partial_months', 'proration_basis',
        'bank_file_columns', 'bank_file_delimiter', 'bank_file_header', 'bank_file_reference', 'bank_file_decimals',
        'advance_payment_account_id', 'updated_by',
    ];

    protected $casts = [
        'require_approval' => 'boolean',
        'carry_over_lines' => 'boolean',
        'prorate_partial_months' => 'boolean',
        'bank_file_header' => 'boolean',
        'bank_file_columns' => 'array',
        'bank_file_decimals' => 'integer',
    ];

    /** The columns a bank file may carry (labels: ledger_payroll.bank_col_*). */
    public const BANK_COLUMNS = ['staff_id', 'name', 'account_name', 'bank_name', 'branch', 'account_number', 'bank_code', 'mobile_money',
        'amount', 'currency', 'reference', 'month'];

    public const DEFAULT_BANK_COLUMNS = ['staff_id', 'name', 'bank_name', 'account_number', 'mobile_money', 'amount', 'reference'];

    public const PRORATION_BASES = ['calendar_days', 'working_days'];

    private static array $cached = [];

    /** These books' settings (made with the defaults on first use). */
    public static function current(): self
    {
        $bid = Ledger::businessId();
        if (isset(static::$cached[$bid]) && static::$cached[$bid]->exists) {
            return static::$cached[$bid];
        }

        return static::$cached[$bid] = static::query()->first() ?? static::create(static::defaults());
    }

    public static function flush(): void
    {
        static::$cached = [];
    }

    public static function defaults(): array
    {
        return [
            'require_approval' => false,
            'carry_over_lines' => true,
            'prorate_partial_months' => false,
            'proration_basis' => 'calendar_days',
            'bank_file_columns' => self::DEFAULT_BANK_COLUMNS,
            'bank_file_delimiter' => ',',
            'bank_file_header' => true,
            'bank_file_reference' => 'SALARY {MONTH} {YEAR}',
            'bank_file_decimals' => 0,
        ];
    }

    public function bankColumns(): array
    {
        $columns = array_values(array_filter((array) ($this->bank_file_columns ?: self::DEFAULT_BANK_COLUMNS), fn ($c) => in_array($c, self::BANK_COLUMNS, true)));

        return $columns ?: self::DEFAULT_BANK_COLUMNS;
    }

    public function advancePaymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'advance_payment_account_id');
    }
}
