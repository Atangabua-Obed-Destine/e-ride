<?php

namespace App\Ledger\Payroll;

use App\Ledger\Models\AllowanceType;
use App\Ledger\Models\Payroll;
use App\Ledger\Models\PayrollSetting;
use App\Ledger\Models\Staff;
use App\Ledger\Models\StaffLoan;
use App\Ledger\Models\TaxGroup;
use Carbon\Carbon;

/**
 * paxhitestbed PayrollCalculator: every figure on a payroll, worked out on the server. The page
 * previews through it as lines are typed, and saving asks it again and stores its answer; posted
 * figures are only compared with it (a disagreement refuses the save).
 *
 * From the person generating the payroll it takes only which allowances and deductions apply and
 * how much each is; the rest comes from the staff record, the allowance types and the rates in
 * force for the month. Monthly (fixed) pay only: hourly pay reads attendance, which is not ported.
 *
 * E-Ride: FCFA has no centimes, so each tax line is rounded to the franc (on a 180 000 salary that
 * gives paxhitestbed's own figures: 21 005 withheld, 12 060 employer charges).
 */
class PayrollCalculator
{
    /** The income-tax group, by code (paxhitestbed used the id 1, its own install's number). */
    public const INCOME_TAX_CODE = 'PIT';

    private const EPSILON = 0.005;

    public function __construct(private PayrollTaxBreakdownService $breakdown)
    {
    }

    public function calculate(Staff $staff, $salaryMonth, array $allowances = [], array $deductions = [], ?Payroll $existing = null): array
    {
        $month = Carbon::parse($salaryMonth)->startOfMonth();
        $problems = [];
        $notes = [];

        $salaryType = (int) ($staff->salary_type ?: Staff::SALARY_FIXED);
        $basic = round((float) ($staff->basic_salary ?? 0), 2);
        $hours = null;
        $withoutTimes = 0;
        $paidDays = null;
        $periodDays = null;
        if ($salaryType === Staff::SALARY_HOURLY) {
            // paid for the hours present (paxhitestbed: the rate times the hours, never the rate as the month's pay)
            [$hours, $withoutTimes] = $this->hoursWorked($staff->id, $month);
            $earning = round($basic * $hours);
            if ($withoutTimes > 0) {
                $notes[] = trans_choice('ledger_payroll.note_without_times', $withoutTimes, ['count' => $withoutTimes]);
            }
        } else {
            $earning = $basic;
            [$paidDays, $periodDays, $prorationNote] = $this->proration($staff, $month);
            if ($paidDays !== null) {
                $earning = $periodDays > 0 ? round($basic * $paidDays / $periodDays) : 0.0;
                $notes[] = $prorationNote;
            }
        }
        if ($basic <= 0) {
            $problems[] = __('ledger_payroll.err_no_salary');
        }

        // --- allowances and deductions
        $knownTitles = AllowanceType::query()->get(['title', 'status', 'is_taxable'])->keyBy('title');
        $ownTitles = $existing ? $existing->details()->where('status', 1)->get(['title', 'is_taxable'])->keyBy('title') : collect();

        $allowanceLines = [];
        foreach ($this->lines($allowances, __('ledger_payroll.allowance'), $problems) as [$title, $amount]) {
            $type = $knownTitles->get($title);
            $own = $ownTitles->get($title);
            if ((! $type || ! $type->status) && ! $own) {
                $problems[] = __('ledger_payroll.err_not_allowance_type', ['title' => $title]);
                continue;
            }
            $taxable = $type ? (bool) $type->is_taxable : (bool) ($own->is_taxable ?? false);
            $allowanceLines[] = ['title' => $title, 'amount' => $amount, 'is_taxable' => $taxable];
        }

        $deductionLines = [];
        foreach ($this->lines($deductions, __('ledger_payroll.deduction'), $problems) as [$title, $amount, $loanId]) {
            if ($loanId) {
                $loan = StaffLoan::find($loanId);
                if (! $loan || (int) $loan->staff_id !== (int) $staff->id || $loan->status !== 'active') {
                    $problems[] = __('ledger_payroll.err_not_open_loan', ['title' => $title]);
                    continue;
                }
                $available = $loan->available($existing?->id);
                if ($amount - $available > self::EPSILON) {
                    $problems[] = __('ledger_payroll.err_loan_left', ['title' => $title, 'left' => \App\Ledger\Ledger::money($available), 'amount' => \App\Ledger\Ledger::money($amount)]);
                    continue;
                }
                $deductionLines[] = ['title' => $loan->title, 'amount' => $amount, 'loan_id' => $loan->id];
                continue;
            }
            $deductionLines[] = ['title' => $title, 'amount' => $amount, 'loan_id' => null];
        }

        $totalAllowance = round(array_sum(array_column($allowanceLines, 'amount')), 2);
        $taxableAllowance = round(array_sum(array_map(fn ($l) => $l['is_taxable'] ? $l['amount'] : 0, $allowanceLines)), 2);
        $totalDeduction = round(array_sum(array_column($deductionLines, 'amount')), 2);

        // --- tax
        $taxableSalary = round($earning + $taxableAllowance, 2);
        $taxLines = [];
        $tax = 0.0;
        $employerTax = 0.0;
        try {
            ['lines' => $taxLines, 'employee' => $tax, 'employer' => $employerTax] = $this->taxFor($staff->id, $taxableSalary, $month);
        } catch (\RuntimeException $e) {
            $problems[] = $e->getMessage();
        }

        // --- the pay
        $gross = round($earning + $totalAllowance - $totalDeduction, 2);
        $net = round($gross - $tax, 2);
        if ($net < -self::EPSILON) {
            $problems[] = __('ledger_payroll.err_net_negative', ['out' => \App\Ledger\Ledger::money($totalDeduction + $tax),
                'in' => \App\Ledger\Ledger::money($earning + $totalAllowance), 'net' => \App\Ledger\Ledger::money($net)]);
        }

        return [
            'salary_type' => $salaryType,
            'basic_salary' => $basic,
            'hours_worked' => $hours,
            'sessions_without_times' => $withoutTimes,
            'paid_days' => $paidDays,
            'period_days' => $periodDays,
            'total_earning' => $earning,
            'allowances' => $allowanceLines,
            'deductions' => $deductionLines,
            'total_allowance' => $totalAllowance,
            'taxable_allowance' => $taxableAllowance,
            'total_deduction' => $totalDeduction,
            'taxable_salary' => $taxableSalary,
            'gross_salary' => $gross,
            'tax_lines' => $taxLines,
            'tax' => $tax,
            'employer_tax' => $employerTax,
            'net_salary' => $net,
            'total_cost' => round($net + $tax + $employerTax, 2),
            'effective_rate' => $taxableSalary > 0 ? round($tax / $taxableSalary * 100, 2) : 0.0,
            'problems' => array_values(array_unique($problems)),
            'notes' => $notes,
        ];
    }

    /** The tax on a taxable pay for a month, itemised; refuses when the rates do not cover it. */
    public function taxFor(int $staffId, float $taxableSalary, $salaryMonth): array
    {
        $month = Carbon::parse($salaryMonth)->startOfMonth();
        $this->assertRatesCover($taxableSalary, $month);

        $lines = array_map(function ($line) {
            $line['employee'] = round((float) $line['employee']);
            $line['employer'] = round((float) $line['employer']);

            return $line;
        }, $this->breakdown->forSalary($taxableSalary, $month, $this->breakdown->exemptionsFor($staffId, $month)));
        $lines = array_values(array_filter($lines, fn ($l) => $l['employee'] != 0.0 || $l['employer'] != 0.0));

        return [
            'lines' => $lines,
            'employee' => round(array_sum(array_column($lines, 'employee')), 2),
            'employer' => round(array_sum(array_column($lines, 'employer')), 2),
        ];
    }

    /** paxhitestbed: the share of a part month paid (joining / leaving date), when the books prorate. */
    /**
     * paxhitestbed hoursWorked(): the hours an hourly staff member was present in a month, and how
     * many present sessions could not be counted (a time missing, or the end not after the start).
     *
     * @return array{0: float, 1: int}
     */
    public function hoursWorked(int $staffId, Carbon $month): array
    {
        $minutes = 0;
        $withoutTimes = 0;
        $sessions = \App\Ledger\Models\StaffHourlyAttendance::where('staff_id', $staffId)
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->where('attendance', \App\Ledger\Models\StaffHourlyAttendance::PRESENT)->get();
        foreach ($sessions as $s) {
            $h = $s->hours();
            if ($h === null) {
                $withoutTimes++;
                continue;
            }
            $minutes += (int) round($h * 60);
        }

        return [round($minutes / 60, 2), $withoutTimes];
    }

    public function proration(Staff $staff, Carbon $month): array
    {
        $settings = PayrollSetting::current();
        if (! $settings->prorate_partial_months) {
            return [null, null, null];
        }
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth()->startOfDay();
        $from = $start->copy();
        $to = $end->copy();
        if ($staff->joining_date) {
            $joined = Carbon::parse($staff->joining_date)->startOfDay();
            if ($joined->gt($start) && $joined->lte($end)) {
                $from = $joined;
            } elseif ($joined->gt($end)) {
                $from = $end->copy()->addDay();
            }
        }
        if ($staff->ending_date) {
            $left = Carbon::parse($staff->ending_date)->startOfDay();
            if ($left->gte($start) && $left->lt($end)) {
                $to = $left;
            } elseif ($left->lt($start)) {
                $to = $start->copy()->subDay();
            }
        }
        if ($from->eq($start) && $to->eq($end)) {
            return [null, null, null];
        }
        $working = $settings->proration_basis === 'working_days';
        $count = function (Carbon $a, Carbon $b) use ($working): int {
            if ($b->lt($a)) {
                return 0;
            }
            if (! $working) {
                return (int) $a->diffInDays($b) + 1;
            }
            $n = 0;
            for ($d = $a->copy(); $d->lte($b); $d->addDay()) {
                if (! $d->isWeekend()) {
                    $n++;
                }
            }

            return $n;
        };
        $period = $count($start, $end);
        $paid = $count($from, $to);
        $note = __('ledger_payroll.note_prorated', ['paid' => $paid, 'period' => $period,
            'unit' => $working ? __('ledger_payroll.working_days') : __('ledger_payroll.days'),
            'from' => $from->format('d/m'), 'to' => $to->lt($from) ? $from->format('d/m') : $to->format('d/m')]);

        return [(float) $paid, (float) $period, $note];
    }

    /** Blank rows dropped; anything else must be a title and a non-negative amount. */
    private function lines(array $rows, string $kind, array &$problems): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            $raw = $row['amount'] ?? null;
            if ($raw === null || trim((string) $raw) === '') {
                continue;
            }
            $raw = str_replace([',', ' ', "\u{202F}", "\u{00A0}"], '', (string) $raw);
            if (! is_numeric($raw)) {
                $problems[] = __('ledger_payroll.err_not_amount', ['amount' => $row['amount'], 'kind' => $kind, 'title' => $title]);
                continue;
            }
            $amount = round((float) $raw, 2);
            if ($amount < 0) {
                $problems[] = __('ledger_payroll.err_negative', ['kind' => $kind, 'title' => $title]);
                continue;
            }
            if ($title === '') {
                $problems[] = __('ledger_payroll.err_needs_title', ['kind' => $kind]);
                continue;
            }
            if ($amount <= self::EPSILON) {
                continue;
            }
            $loanId = isset($row['loan_id']) && is_numeric($row['loan_id']) && (int) $row['loan_id'] > 0 ? (int) $row['loan_id'] : null;
            $clean[] = [$title, $amount, $loanId];
        }

        return $clean;
    }

    /** No income tax in force must not read as "no tax is due", and pay in a hole between bands is refused. */
    private function assertRatesCover(float $taxableSalary, Carbon $month): void
    {
        $groups = TaxGroup::getEffectiveGroups($month);
        $incomeTax = $groups->first(fn ($g) => $g->code === self::INCOME_TAX_CODE) ?? $groups->first(fn ($g) => $g->is_progressive);
        $bandsInForce = $incomeTax ? $incomeTax->brackets->where('status', 1)->count() : 0;
        if (! $incomeTax || $bandsInForce === 0) {
            throw new \RuntimeException(__('ledger_payroll.err_no_rates', ['month' => $month->translatedFormat('F Y')]));
        }
        if ($taxableSalary <= 0) {
            return;
        }
        $gap = $incomeTax->gapFor($taxableSalary);
        if ($gap !== null) {
            $range = $gap['to'] === null ? __('ledger_payroll.anything_above', ['from' => \App\Ledger\Ledger::money($gap['from'])])
                : \App\Ledger\Ledger::money($gap['from']).' - '.\App\Ledger\Ledger::money($gap['to']);
            throw new \RuntimeException(__('ledger_payroll.err_gap', ['pay' => \App\Ledger\Ledger::money($taxableSalary), 'range' => $range,
                'band' => $gap['charged_as'] ?? '']));
        }
    }
}
