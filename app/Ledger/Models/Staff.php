<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * The people a set of books pays: paxhitestbed's staff (its users with HR fields), as a register of
 * their own, because a bus branch's drivers, conductors and loaders are often not E-Ride users.
 * Only the fields payroll and tax read are kept. user_id links the person to their E-Ride account
 * when they have one (a staff member of the branch or of the head office).
 */
class Staff extends Model
{
    use Auditable, BelongsToBusiness;

    protected $table = 'ledger_staff';

    public const SALARY_FIXED = 1;

    public const PAY_CHANNELS = ['bank', 'mobile_money', 'cash'];

    public const MOMO_PROVIDERS = ['mtn', 'orange'];

    protected $fillable = ['business_id', 'staff_no', 'first_name', 'last_name', 'gender', 'phone', 'email', 'designation_id', 'department_id',
        'contract_type', 'salary_type', 'basic_salary', 'joining_date', 'ending_date', 'pay_channel', 'bank_name', 'bank_branch', 'account_name',
        'account_number', 'bank_code', 'momo_provider', 'momo_number', 'cnps_no', 'niu', 'user_id', 'note', 'status', 'created_by', 'updated_by'];

    protected $casts = ['basic_salary' => 'float', 'joining_date' => 'date', 'ending_date' => 'date', 'status' => 'boolean',
        'salary_type' => 'integer', 'contract_type' => 'integer'];

    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class, 'staff_id');
    }

    public function loans()
    {
        return $this->hasMany(StaffLoan::class, 'staff_id');
    }

    /** paxhitestbed User::exemptTaxes */
    public function exemptTaxes()
    {
        return $this->hasMany(StaffTaxExemption::class, 'staff_id');
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** "Name (Staff ID)", as the bulk results name people. */
    public function label(): string
    {
        return $this->name.' ('.$this->staff_no.')';
    }

    /** Where the pay goes, in a line: the bank account or the Mobile Money number. */
    public function payTo(): string
    {
        return match ($this->pay_channel) {
            'bank' => trim(implode(' - ', array_filter([$this->bank_name, $this->account_number]))) ?: '-',
            'mobile_money' => trim(strtoupper((string) $this->momo_provider).' '.$this->momo_number) ?: '-',
            'cash' => __('ledger_payroll.channel_cash'),
            default => '-',
        };
    }

    /** Has a place to send the pay (a bank account or a Mobile Money number), or is paid in cash. */
    public function hasPayTo(): bool
    {
        return match ($this->pay_channel) {
            'bank' => (bool) $this->account_number,
            'mobile_money' => (bool) $this->momo_number,
            'cash' => true,
            default => false,
        };
    }

    /** The next free staff number: paxhitestbed generates staff_id, prefixed. */
    public static function nextNumber(): string
    {
        $n = (int) static::query()->count() + 1;
        do {
            $no = 'ST'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            $n++;
        } while (static::where('staff_no', $no)->exists());

        return $no;
    }

    public function getAuditDescription($event)
    {
        return "Staff {$event}: {$this->name} ({$this->staff_no})";
    }
}
