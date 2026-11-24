<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** Something recorded by hand (an expense, a supplier payment, money moved, an agent's cash count...), and its entry. */
class BusAccDocument extends Model
{
    use HasUuid;

    public const EXPENSE = 'expense';
    public const BILL_PAYMENT = 'bill_payment';
    public const INCOME = 'income';
    public const TRANSFER = 'transfer';
    public const CASH_COUNT = 'cash_count';
    public const CHECK = 'check';
    public const JOURNAL = 'journal';
    public const OPENING = 'opening';
    public const ASSET = 'asset';
    public const DISPOSAL = 'disposal';
    public const BRANCH_MONEY = 'branch_money';

    public const PREFIX = [
        self::EXPENSE => 'EXP', self::BILL_PAYMENT => 'PAY', self::INCOME => 'INC', self::TRANSFER => 'TRF',
        self::CASH_COUNT => 'CNT', self::CHECK => 'CHK', self::JOURNAL => 'JNL', self::OPENING => 'OPN',
        self::ASSET => 'BUS', self::DISPOSAL => 'DSP', self::BRANCH_MONEY => 'HOB',
    ];

    protected $table = 'bus_acc_documents';

    protected $fillable = [
        'agency_id', 'number', 'type', 'date', 'amount', 'account_id', 'money_account_id', 'to_account_id', 'parent_id', 'supplier', 'owed', 'due_on',
        'vehicle_id', 'destination_id', 'departure_id', 'staff_user_id', 'expected', 'counted', 'fee', 'reference', 'note', 'reverse_on', 'lines',
        'status', 'entry_id', 'void_entry_id', 'auto_reverse_entry_id', 'void_reason', 'created_by', 'voided_by', 'voided_at',
    ];

    protected $casts = [
        'date' => 'date', 'due_on' => 'date', 'reverse_on' => 'date', 'voided_at' => 'datetime', 'lines' => 'array',
        'amount' => 'float', 'owed' => 'float', 'expected' => 'float', 'counted' => 'float', 'fee' => 'float',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(BusAccAccount::class, 'account_id');
    }

    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(BusAccAccount::class, 'money_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(BusAccAccount::class, 'to_account_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(BusVehicle::class, 'vehicle_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(BusDestination::class, 'destination_id');
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return translate(match ($this->type) {
            self::EXPENSE => 'Expense',
            self::BILL_PAYMENT => 'Bill payment',
            self::INCOME => 'Other income',
            self::TRANSFER => 'Money moved',
            self::CASH_COUNT => 'Cash count',
            self::CHECK => 'Reconciliation',
            self::JOURNAL => 'Journal entry',
            self::ASSET => 'Bus added',
            self::DISPOSAL => 'Bus sold or retired',
            self::BRANCH_MONEY => 'Money with a branch',
            default => 'Opening balances',
        });
    }
}
