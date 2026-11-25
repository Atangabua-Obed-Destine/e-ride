<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * paxhitestbed StaffLoan: a loan or salary advance, repaid by a deduction on each payroll from
 * start_month (the instalment, or what is left). Repaid counts paid payrolls; pending counts unpaid
 * ones, so two payrolls cannot both take the last instalment. Posts through the sync
 * (PostingRules::forStaffLoan): Dr 421 staff advances / Cr the payment account it was paid from.
 */
class StaffLoan extends Model
{
    use Auditable, BelongsToBusiness;

    protected $table = 'staff_loans';

    protected $fillable = [
        'business_id', 'staff_id', 'kind', 'title', 'amount', 'instalment', 'start_month', 'given_on', 'payment_account_id',
        'status', 'note', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_month' => 'date',
        'given_on' => 'date',
        'amount' => 'float',
        'instalment' => 'float',
    ];

    public const KINDS = ['loan', 'advance'];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function lines()
    {
        return $this->hasMany(PayrollDetail::class, 'staff_loan_id');
    }

    /** Taken from paid payrolls. */
    public function repaid(): float
    {
        return round((float) DB::table('payroll_details')
            ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
            ->where('payroll_details.staff_loan_id', $this->id)
            ->where('payrolls.status', 1)
            ->sum('payroll_details.amount'), 2);
    }

    /** On payrolls generated but not paid yet (optionally leaving one out: the one being regenerated). */
    public function pending(?int $exceptPayrollId = null): float
    {
        return round((float) DB::table('payroll_details')
            ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
            ->where('payroll_details.staff_loan_id', $this->id)
            ->where('payrolls.status', 0)
            ->when($exceptPayrollId, fn ($q) => $q->where('payrolls.id', '!=', $exceptPayrollId))
            ->sum('payroll_details.amount'), 2);
    }

    public function balance(): float
    {
        return round(max(0, $this->amount - $this->repaid()), 2);
    }

    public function available(?int $exceptPayrollId = null): float
    {
        return round(max(0, $this->amount - $this->repaid() - $this->pending($exceptPayrollId)), 2);
    }

    /** What a payroll for this month takes: the instalment, or what is left, from the start month on. */
    public function dueFor($salaryMonth, ?int $exceptPayrollId = null): float
    {
        if ($this->status !== 'active') {
            return 0.0;
        }
        $month = Carbon::parse($salaryMonth)->startOfMonth();
        if ($month->lt(Carbon::parse($this->start_month)->startOfMonth())) {
            return 0.0;
        }

        return round(min($this->instalment, $this->available($exceptPayrollId)), 2);
    }

    public function isCleared(): bool
    {
        return $this->balance() <= 0.004;
    }

    /** The repayment lines a staff member's payroll for a month starts with. */
    public static function dueLines(int $staffId, $salaryMonth, ?int $exceptPayrollId = null): array
    {
        $lines = [];
        foreach (static::where('staff_id', $staffId)->where('status', 'active')->orderBy('id')->get() as $loan) {
            $due = $loan->dueFor($salaryMonth, $exceptPayrollId);
            if ($due > 0) {
                $lines[] = ['title' => $loan->title, 'amount' => $due, 'loan_id' => $loan->id];
            }
        }

        return $lines;
    }

    public function getAuditDescription($event)
    {
        return "Staff loan {$event}: {$this->title} - Amount: {$this->amount}";
    }
}
