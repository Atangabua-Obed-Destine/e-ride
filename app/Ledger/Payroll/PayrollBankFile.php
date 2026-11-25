<?php

namespace App\Ledger\Payroll;

use App\Ledger\Models\PayrollSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * paxhitestbed PayrollBankFile: a month's net pay as a delimited file in the layout set on Payroll
 * settings, for the bank (or the Mobile Money bulk payment). E-Ride: the pay-to details are on the
 * staff register; someone with neither an account number nor a Mobile Money number is listed as
 * missing.
 */
class PayrollBankFile
{
    public function build(Collection $payrolls): array
    {
        $settings = PayrollSetting::current();
        $decimals = (int) $settings->bank_file_decimals;
        $rows = [];
        $missing = [];
        $total = 0.0;

        foreach ($payrolls as $payroll) {
            $payroll->loadMissing('staff');
            $s = $payroll->staff;
            $name = $s ? $s->name : '#'.$payroll->staff_id;
            if (! $s || (trim((string) $s->account_number) === '' && trim((string) $s->momo_number) === '')) {
                $missing[] = $name.' ('.($s->staff_no ?? $payroll->staff_id).')';
                continue;
            }
            $month = Carbon::parse($payroll->salary_month);
            $reference = strtr((string) $settings->bank_file_reference, [
                '{MONTH}' => strtoupper($month->format('M')),
                '{MONTH_NUMBER}' => $month->format('m'),
                '{YEAR}' => $month->format('Y'),
                '{STAFF_ID}' => (string) $s->staff_no,
                '{NAME}' => $name,
            ]);
            $values = [
                'staff_id' => (string) $s->staff_no,
                'name' => $name,
                'account_name' => (string) ($s->account_name ?: $name),
                'bank_name' => (string) $s->bank_name,
                'branch' => (string) $s->bank_branch,
                'account_number' => (string) $s->account_number,
                'bank_code' => (string) $s->bank_code,
                'mobile_money' => trim(strtoupper((string) $s->momo_provider).' '.$s->momo_number),
                'amount' => number_format((float) $payroll->net_salary, $decimals, '.', ''),
                'currency' => 'XAF',
                'reference' => $reference,
                'month' => $month->format('Y-m'),
            ];
            $row = [];
            foreach ($settings->bankColumns() as $column) {
                $row[$column] = $values[$column];
            }
            $rows[] = $row;
            $total += round((float) $payroll->net_salary, $decimals);
        }

        return ['rows' => $rows, 'missing' => $missing, 'total' => round($total, $decimals)];
    }

    public function csv(array $rows): string
    {
        $settings = PayrollSetting::current();
        $d = (string) $settings->bank_file_delimiter;
        $delimiter = $d === 'tab' ? "\t" : ($d !== '' ? substr($d, 0, 1) : ',');
        $out = fopen('php://temp', 'r+');
        if ($settings->bank_file_header) {
            fputcsv($out, array_map(fn ($c) => __('ledger_payroll.bank_col_'.$c), $settings->bankColumns()), $delimiter);
        }
        foreach ($rows as $row) {
            // a leading = + @ makes a spreadsheet read a cell as a formula
            fputcsv($out, array_map(fn ($v) => preg_match('/^[=+@]/', $v) ? "'".$v : $v, array_values($row)), $delimiter);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
