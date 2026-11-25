<?php

namespace App\Ledger\Payroll;

use App\Ledger\Models\Payroll;
use App\Ledger\Models\PayrollDetail;
use App\Ledger\Models\PayrollSetting;
use App\Ledger\Models\Staff;
use App\Ledger\Models\StaffLoan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * paxhitestbed PayrollWriter: saving a payroll, from the payroll page or for many at once from the
 * list, so a bulk-generated payroll is exactly what the page would have saved. Saving posts
 * nothing (the entry is made when the payroll is paid) and clears any approval.
 */
class PayrollWriter
{
    public function __construct(private PayrollCalculator $calculator)
    {
    }

    public function save(Staff $staff, string $salaryMonth, array $calc, ?string $byUserId): Payroll
    {
        if (! empty($calc['problems'])) {
            throw new \RuntimeException(implode(' ', $calc['problems']));
        }

        return DB::transaction(function () use ($staff, $salaryMonth, $calc, $byUserId) {
            $existing = Payroll::where('staff_id', $staff->id)->whereDate('salary_month', $salaryMonth)->lockForUpdate()->first();
            if ($existing && $existing->isPaid()) {
                throw new \RuntimeException(__('ledger_payroll.err_paid_locked'));
            }
            $payroll = $existing ?: new Payroll(['staff_id' => $staff->id, 'salary_month' => $salaryMonth]);
            $payroll->fill([
                'basic_salary' => $calc['basic_salary'],
                'salary_type' => $calc['salary_type'],
                'total_earning' => $calc['total_earning'],
                'paid_days' => $calc['paid_days'] ?? null,
                'period_days' => $calc['period_days'] ?? null,
                'total_allowance' => $calc['total_allowance'],
                'bonus' => 0,
                'total_deduction' => $calc['total_deduction'],
                'gross_salary' => $calc['gross_salary'],
                'taxable_salary' => $calc['taxable_salary'],
                'tax' => $calc['tax'],
                'employer_tax' => $calc['employer_tax'],
                'net_salary' => $calc['net_salary'],
                'total_cost' => $calc['total_cost'],
                'status' => 0,
                'approved_by' => null,
                'approved_at' => null,
            ]);
            $payroll->created_by = $payroll->created_by ?: $byUserId;
            $payroll->updated_by = $byUserId;
            $payroll->save();

            PayrollDetail::where('payroll_id', $payroll->id)->delete();
            foreach ($calc['allowances'] as $line) {
                PayrollDetail::create(['payroll_id' => $payroll->id, 'title' => $line['title'], 'amount' => $line['amount'],
                    'is_taxable' => $line['is_taxable'], 'status' => 1]);
            }
            foreach ($calc['deductions'] as $line) {
                PayrollDetail::create(['payroll_id' => $payroll->id, 'staff_loan_id' => $line['loan_id'] ?? null, 'title' => $line['title'],
                    'amount' => $line['amount'], 'status' => 0]);
            }

            // itemise the tax in the same transaction that charged it
            $breakdown = app(PayrollTaxBreakdownService::class);
            $recorded = $breakdown->record($payroll);
            if (! $recorded['reconciled']) {
                throw new \RuntimeException(__('ledger_payroll.err_breakdown', ['lines' => \App\Ledger\Ledger::money($breakdown->totals($recorded['lines'])['employee']),
                    'tax' => \App\Ledger\Ledger::money($payroll->tax)]));
            }

            return $payroll->fresh();
        });
    }

    /** Last month's lines (when the books carry them over) and every open loan's instalment. */
    public function startingLines(Staff $staff, string $salaryMonth, ?Payroll $existing = null): array
    {
        $allowances = [];
        $deductions = [];
        $from = null;
        if (PayrollSetting::current()->carry_over_lines) {
            $previous = Payroll::with('details')->where('staff_id', $staff->id)->whereDate('salary_month', '<', $salaryMonth)
                ->orderByDesc('salary_month')->first();
            if ($previous) {
                $from = Carbon::parse($previous->salary_month)->translatedFormat('F Y');
                foreach ($previous->details as $detail) {
                    if ($detail->staff_loan_id) {
                        continue;
                    }
                    $row = ['title' => $detail->title, 'amount' => $detail->amount];
                    if ((int) $detail->status === 1) {
                        $allowances[] = $row;
                    } else {
                        $deductions[] = $row;
                    }
                }
            }
        }
        foreach (StaffLoan::dueLines($staff->id, $salaryMonth, $existing?->id) as $line) {
            $deductions[] = $line;
        }

        return ['allowances' => $allowances, 'deductions' => $deductions, 'from' => $from];
    }

    public function generateFromStartingLines(Staff $staff, string $salaryMonth, ?string $byUserId): array
    {
        $existing = Payroll::where('staff_id', $staff->id)->whereDate('salary_month', $salaryMonth)->first();
        $lines = $this->startingLines($staff, $salaryMonth, $existing);
        $calc = $this->calculator->calculate($staff, $salaryMonth, $lines['allowances'], $lines['deductions'], $existing);
        if (! empty($calc['problems'])) {
            return ['payroll' => null, 'calc' => $calc, 'from' => $lines['from']];
        }

        return ['payroll' => $this->save($staff, $salaryMonth, $calc, $byUserId), 'calc' => $calc, 'from' => $lines['from']];
    }
}
