<?php

namespace App\Ledger\Payroll;

use App\Ledger\Models\Payroll;
use App\Ledger\Models\PayrollTaxLine;
use App\Ledger\Models\StaffTaxExemption;
use App\Ledger\Models\TaxGroup;
use App\Ledger\Models\TaxSetting;
use Carbon\Carbon;

/**
 * paxhitestbed PayrollTaxBreakdownService: what each tax takes from a salary, in the engine's three
 * passes (grouped taxes by step lookup, standalone base taxes, then dependent taxes on their
 * source's output), with exemptions applied the one way (custom rate replaces the normal one, none
 * means nothing, employer side zero while exempt, expired means full tax).
 *
 * In E-Ride this is the ONE implementation: the payroll screen, saving, the payslip and the staff
 * tax report all call it (paxhitestbed kept hand-synchronised copies, its gotcha #1).
 */
class PayrollTaxBreakdownService
{
    /** The itemised taxes of a payroll, under the rules of its own salary month. */
    public function forPayroll(Payroll $payroll): array
    {
        return $this->forSalary(
            (float) ($payroll->taxable_salary ?? $payroll->total_earning),
            Carbon::parse($payroll->salary_month),
            $this->exemptionsFor($payroll->staff_id, $payroll->salary_month)
        );
    }

    /** A staff member's exemptions in force in a month, keyed by tax_setting_id. */
    public function exemptionsFor($staffId, $salaryMonth)
    {
        return StaffTaxExemption::where('staff_id', $staffId)->notExpired(Carbon::parse($salaryMonth))->get()->keyBy('tax_setting_id');
    }

    /**
     * @return array<int, array{tax_group_id:?int, tax_setting_id:?int, label:string, employee:float, employer:float, liability_account_id:?int}>
     */
    public function forSalary(float $salary, Carbon $date, $exemptions = null, ?array &$parts = null): array
    {
        $exemptions = $exemptions ?: collect();
        $lines = [];
        $parts = ['groups' => [], 'standalone' => []];

        // --- grouped taxes: income tax, local development, housing fund, CRTV
        $groupResults = [];
        foreach (TaxGroup::getEffectiveGroups($date) as $group) {
            $result = $group->calculateTax($salary, $exemptions);
            $bracket = $group->applicableBracket($salary);
            $employer = 0.0;
            if ($bracket && in_array($bracket->paid_by, ['employer', 'both'], true) && ! $this->isExempt($bracket->id, $exemptions)) {
                $employer = (float) $bracket->calculateEmployerContribution($salary);
            }
            $groupResults[$group->id] = ['employee' => (float) $result['amount'], 'employer' => $employer];
            $parts['groups'][$group->id] = $groupResults[$group->id] + ['is_exempt' => $bracket && $this->isExempt($bracket->id, $exemptions)];
            if ($result['amount'] == 0.0 && $employer == 0.0) {
                continue;
            }
            $lines[] = [
                'tax_group_id' => (int) $group->id,
                'tax_setting_id' => $bracket ? (int) $bracket->id : null,
                'label' => $group->title,
                'employee' => (float) $result['amount'],
                'employer' => $employer,
                'liability_account_id' => $this->liabilityFor($bracket, $group),
            ];
        }

        // --- standalone base taxes: CNPS, employment fund (both bounds are read here)
        $standaloneResults = [];
        foreach (TaxSetting::getEffectiveBrackets($date) as $tax) {
            $standaloneResults[$tax->id] = ['employee' => 0.0, 'employer' => 0.0];
            $parts['standalone'][$tax->id] = ['employee' => 0.0, 'employer' => 0.0, 'is_exempt' => $this->isExempt($tax->id, $exemptions)];
            if ($salary < $tax->min_amount || $salary > $tax->max_amount) {
                continue;
            }
            [$employee, $employer] = $this->amountsFor($tax, $salary, $exemptions);
            $standaloneResults[$tax->id] = ['employee' => $employee, 'employer' => $employer];
            $parts['standalone'][$tax->id] = ['employee' => $employee, 'employer' => $employer, 'is_exempt' => $this->isExempt($tax->id, $exemptions)];
            if ($employee == 0.0 && $employer == 0.0) {
                continue;
            }
            $lines[] = ['tax_group_id' => null, 'tax_setting_id' => (int) $tax->id, 'label' => $tax->title, 'employee' => $employee,
                'employer' => $employer, 'liability_account_id' => $this->liabilityFor($tax, null)];
        }

        // --- dependent taxes: a percentage of another tax (the council tax is 10% of the income tax)
        foreach (TaxSetting::getEffectiveDependentBrackets($date) as $tax) {
            $parts['standalone'][$tax->id] = ['employee' => 0.0, 'employer' => 0.0, 'is_exempt' => $this->isExempt($tax->id, $exemptions), 'is_dependent' => true];
            if ($salary < $tax->min_amount || $salary > $tax->max_amount) {
                continue;
            }
            $source = 0.0;
            if ($tax->depends_on_type === 'tax_group' && isset($groupResults[$tax->depends_on_id])) {
                $source = $groupResults[$tax->depends_on_id]['employee'] + $groupResults[$tax->depends_on_id]['employer'];
            } elseif ($tax->depends_on_type === 'tax_setting' && isset($standaloneResults[$tax->depends_on_id])) {
                $source = $standaloneResults[$tax->depends_on_id]['employee'] + $standaloneResults[$tax->depends_on_id]['employer'];
            }
            [$employee, $employer] = $this->amountsFor($tax, $source, $exemptions, true);
            $parts['standalone'][$tax->id] = ['employee' => $employee, 'employer' => $employer, 'is_exempt' => $this->isExempt($tax->id, $exemptions),
                'is_dependent' => true, 'source_amount' => $source];
            if ($employee == 0.0 && $employer == 0.0) {
                continue;
            }
            $lines[] = ['tax_group_id' => null, 'tax_setting_id' => (int) $tax->id, 'label' => $tax->title, 'employee' => $employee,
                'employer' => $employer, 'liability_account_id' => $this->liabilityFor($tax, null)];
        }

        return $lines;
    }

    /** Both sides of a single standalone tax, exemptions honoured. */
    private function amountsFor(TaxSetting $tax, float $base, $exemptions, bool $isDependent = false): array
    {
        $employee = 0.0;
        $employer = 0.0;
        $paidBy = $tax->paid_by ?: 'employee';
        $taxable = $isDependent ? $base : max(0, $base - ($tax->max_no_taxable_amount ?? 0));

        if ($this->isExempt($tax->id, $exemptions)) {
            $exemption = $exemptions->get($tax->id);
            if ($exemption && $exemption->custom_percentage && $tax->tax_type == 1) {
                $employee = ($taxable / 100) * $exemption->custom_percentage;
            } elseif ($exemption && $exemption->custom_fixed_amount && $tax->tax_type == 2) {
                $employee = (float) $exemption->custom_fixed_amount;
            }

            return [$employee, $employer];
        }
        if ($paidBy === 'employee' || $paidBy === 'both') {
            $employee = $tax->tax_type == 2 ? (float) ($tax->fixed_amount ?? 0) : ($taxable / 100) * (float) ($tax->percentange ?? 0);
        }
        if ($paidBy === 'employer' || $paidBy === 'both') {
            $employer = $tax->tax_type == 2 ? (float) ($tax->employer_fixed_amount ?? 0) : ($taxable / 100) * (float) ($tax->employer_percentage ?? 0);
        }

        return [$employee, $employer];
    }

    private function isExempt($taxSettingId, $exemptions): bool
    {
        if (! $exemptions || ! $exemptions->has($taxSettingId)) {
            return false;
        }
        $exemption = $exemptions->get($taxSettingId);

        return ! ($exemption && method_exists($exemption, 'isExpired') && $exemption->isExpired());
    }

    /** The band's own liability account, else its group's, else none (the posting then uses the payroll_tax mapping). */
    private function liabilityFor($setting, $group): ?int
    {
        if ($setting && ! empty($setting->liability_account_id)) {
            return (int) $setting->liability_account_id;
        }
        if ($group && ! empty($group->liability_account_id)) {
            return (int) $group->liability_account_id;
        }

        return null;
    }

    public function totals(array $lines): array
    {
        return [
            'employee' => round(array_sum(array_column($lines, 'employee')), 2),
            'employer' => round(array_sum(array_column($lines, 'employer')), 2),
        ];
    }

    public function reconciles(Payroll $payroll, array $lines, float $tolerance = 0.51): bool
    {
        $totals = $this->totals($lines);

        return abs($totals['employee'] - (float) $payroll->tax) <= $tolerance
            && abs($totals['employer'] - (float) ($payroll->employer_tax ?? 0)) <= $tolerance;
    }

    /** Write a payroll's itemised taxes, replacing what was there; refuses when they do not add up. */
    public function record(Payroll $payroll): array
    {
        // E-Ride: to the franc, as PayrollCalculator::taxFor() charged them (FCFA has no centimes)
        $lines = array_values(array_filter(array_map(function ($l) {
            $l['employee'] = round((float) $l['employee']);
            $l['employer'] = round((float) $l['employer']);

            return $l;
        }, $this->forPayroll($payroll)), fn ($l) => $l['employee'] != 0.0 || $l['employer'] != 0.0));
        if (! $this->reconciles($payroll, $lines)) {
            return ['written' => 0, 'lines' => $lines, 'reconciled' => false];
        }
        $this->clear($payroll);
        foreach ($lines as $line) {
            PayrollTaxLine::create([
                'payroll_id' => $payroll->id,
                'staff_id' => $payroll->staff_id,
                'salary_month' => $payroll->salary_month,
                'tax_group_id' => $line['tax_group_id'],
                'tax_setting_id' => $line['tax_setting_id'],
                'label' => $line['label'],
                'employee_amount' => round($line['employee'], 2),
                'employer_amount' => round($line['employer'], 2),
                'liability_account_id' => $line['liability_account_id'],
            ]);
        }

        return ['written' => count($lines), 'lines' => $lines, 'reconciled' => true];
    }

    public function clear(Payroll $payroll): void
    {
        PayrollTaxLine::where('payroll_id', $payroll->id)->delete();
    }
}
