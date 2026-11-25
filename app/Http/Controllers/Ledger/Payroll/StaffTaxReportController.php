<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Http\Controllers\Ledger\LedgerController;
use App\Ledger\Models\Department;
use App\Ledger\Models\Designation;
use App\Ledger\Models\Payroll;
use App\Ledger\Models\Staff;
use App\Ledger\Models\StaffTaxExemption;
use App\Ledger\Models\TaxGroup;
use App\Ledger\Models\TaxSetting;
use App\Ledger\Payroll\PayrollTaxBreakdownService;
use App\Ledger\Payroll\TaxDefaults;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * paxhitestbed Admin\StaffTaxReportController (Staff tax distribution report): every active staff
 * member's taxes under the rules in force on a date, one column per tax group and standalone tax,
 * with salary bands, effective rates, the last six months' payrolls, and PDF / Excel exports.
 *
 * It RECOMPUTES from the configuration (a "what if"). In E-Ride it calls the same engine as the
 * payroll (PayrollTaxBreakdownService), lines rounded to the franc as the payroll rounds them, so
 * the report applies exemptions exactly as the payslip does (paxhitestbed's gotcha #1).
 */
class StaffTaxReportController extends LedgerController
{
    public function index(Request $request)
    {
        $this->allow('tax-setting-view', 'payroll-report');
        TaxDefaults::ensure();

        return view('ledger.payroll.staff-tax-report.index', $this->build($request));
    }

    public function exportPdf(Request $request)
    {
        $this->allow('tax-setting-view', 'payroll-report');
        $data = $this->build($request) + ['books' => \App\Ledger\ERide\Books::current(), 'generated_at' => now(), 'generated_by' => auth()->user()];
        $pdf = $this->getMpdf('L');
        $pdf->WriteHTML(view('ledger.payroll.staff-tax-report.pdf', $data)->render());

        return response($pdf->Output('', 'S'), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="staff-tax-report-'.$data['effective_date'].'.pdf"']);
    }

    public function exportExcel(Request $request)
    {
        $this->allow('tax-setting-view', 'payroll-report');
        $data = $this->build($request);
        $head = [__('ledger_payroll.field_staff_id'), __('ledger_payroll.field_name'), __('ledger_payroll.field_department'), __('ledger_payroll.field_basic_salary')];
        foreach ($data['tax_columns'] as $c) {
            $head[] = $c['title'].' ('.__('ledger_payroll.employee').')';
            $head[] = $c['title'].' ('.__('ledger_payroll.employer').')';
        }
        array_push($head, __('ledger_payroll.field_employee_tax'), __('ledger_payroll.field_employer_tax'), __('ledger_payroll.field_net_salary'), __('ledger_payroll.field_total_cost'));
        $rows = [$head];
        foreach ($data['staff_rows'] as $r) {
            $line = [$r['staff']->staff_no, $r['staff']->name, $r['staff']->department->title ?? '', $r['basic_salary']];
            foreach ($data['tax_columns'] as $c) {
                $cell = $r[$c['type'] === 'group' ? 'groups' : 'standalone'][$c['id']] ?? ['employee' => 0, 'employer' => 0];
                $line[] = $cell['employee'];
                $line[] = $cell['employer'];
            }
            array_push($line, $r['employee_tax_total'], $r['employer_tax_total'], $r['net_salary'], $r['total_cost']);
            $rows[] = $line;
        }
        $export = new class($rows) extends \App\Ledger\Exports\LedgerRowsExport {
            public function __construct(array $rows)
            {
                $this->rows = $rows;
            }

            public function title(): string
            {
                return 'Staff tax';
            }
        };

        return \Maatwebsite\Excel\Facades\Excel::download($export, 'staff-tax-report-'.$data['effective_date'].'.xlsx');
    }

    private function build(Request $request): array
    {
        $date = $request->effective_date ? Carbon::parse($request->effective_date) : Carbon::today();
        $data = [
            'title' => __('ledger_payroll.staff_tax_report'),
            'departments' => Department::where('status', 1)->orderBy('title')->get(),
            'designations' => Designation::where('status', 1)->orderBy('title')->get(),
            'selected_department' => $request->department_id,
            'selected_designation' => $request->designation_id,
            'selected_salary_min' => $request->salary_min,
            'selected_salary_max' => $request->salary_max,
            'effective_date' => $date->format('Y-m-d'),
        ];
        $groups = TaxGroup::getEffectiveGroups($date);
        $standalone = TaxSetting::active()->standalone()->effectiveOn($date)->ordered()->get();
        $columns = [];
        foreach ($groups as $g) {
            $columns[] = ['type' => 'group', 'id' => $g->id, 'title' => $g->title, 'code' => $g->code];
        }
        foreach ($standalone as $t) {
            $columns[] = ['type' => 'standalone', 'id' => $t->id, 'title' => $t->title, 'dependent' => (bool) $t->is_dependent];
        }

        // monthly staff: an hourly staff member's basic salary is a rate per hour, not a month's pay
        $q = Staff::with(['department', 'designation'])->where('status', 1)->where('salary_type', Staff::SALARY_FIXED)->where('basic_salary', '>', 0);
        if ($request->department_id) {
            $q->where('department_id', $request->department_id);
        }
        if ($request->designation_id) {
            $q->where('designation_id', $request->designation_id);
        }
        if ($request->salary_min) {
            $q->where('basic_salary', '>=', $request->salary_min);
        }
        if ($request->salary_max) {
            $q->where('basic_salary', '<=', $request->salary_max);
        }
        $staff = $q->orderByDesc('basic_salary')->get();
        $exemptions = StaffTaxExemption::whereIn('staff_id', $staff->pluck('id'))->notExpired($date)->get()->groupBy('staff_id');

        $engine = app(PayrollTaxBreakdownService::class);
        $grand = ['basic_salary' => 0, 'employee_tax_total' => 0, 'employer_tax_total' => 0, 'net_salary' => 0, 'total_cost' => 0, 'groups' => [], 'standalone' => []];
        $rows = [];
        foreach ($staff as $s) {
            $salary = (float) $s->basic_salary;
            $mine = isset($exemptions[$s->id]) ? $exemptions[$s->id]->keyBy('tax_setting_id') : collect();
            $parts = [];
            $engine->forSalary($salary, $date, $mine, $parts);
            $row = ['staff' => $s, 'basic_salary' => $salary, 'groups' => [], 'standalone' => [], 'employee_tax_total' => 0, 'employer_tax_total' => 0,
                'exemption_count' => $mine->count()];
            foreach (['groups', 'standalone'] as $side) {
                foreach ($parts[$side] as $id => $p) {
                    $cell = ['employee' => round($p['employee']), 'employer' => round($p['employer']), 'is_exempt' => $p['is_exempt'] ?? false];
                    $row[$side][$id] = $cell;
                    $row['employee_tax_total'] += $cell['employee'];
                    $row['employer_tax_total'] += $cell['employer'];
                    $grand[$side][$id]['employee'] = ($grand[$side][$id]['employee'] ?? 0) + $cell['employee'];
                    $grand[$side][$id]['employer'] = ($grand[$side][$id]['employer'] ?? 0) + $cell['employer'];
                }
            }
            $row['net_salary'] = round($salary - $row['employee_tax_total'], 2);
            $row['total_cost'] = round($salary + $row['employer_tax_total'], 2);
            foreach (['basic_salary', 'employee_tax_total', 'employer_tax_total', 'net_salary', 'total_cost'] as $k) {
                $grand[$k] += $row[$k];
            }
            $rows[] = $row;
        }

        $bands = [
            '0 - 50 000' => [0, 50000], '50 001 - 100 000' => [50001, 100000], '100 001 - 150 000' => [100001, 150000],
            '150 001 - 200 000' => [150001, 200000], '200 001 - 300 000' => [200001, 300000], '300 001+' => [300001, PHP_INT_MAX],
        ];
        $salaryBands = [];
        foreach ($bands as $label => [$min, $max]) {
            $in = array_filter($rows, fn ($r) => $r['basic_salary'] >= $min && $r['basic_salary'] <= $max);
            $salaryBands[$label] = ['count' => count($in), 'total_salary' => array_sum(array_column($in, 'basic_salary')),
                'total_tax' => array_sum(array_column($in, 'employee_tax_total'))];
        }
        $rates = collect($rows)->map(fn ($r) => $r['basic_salary'] > 0 ? round($r['employee_tax_total'] / $r['basic_salary'] * 100, 2) : 0);
        $history = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = Carbon::today()->startOfMonth()->subMonthsNoOverflow($i);
            $p = Payroll::whereYear('salary_month', $m->year)->whereMonth('salary_month', $m->month)->get();
            $history[] = ['label' => $m->translatedFormat('M Y'), 'total_gross' => $p->sum('gross_salary'), 'total_tax' => $p->sum('tax'),
                'employer_tax' => $p->sum('employer_tax'), 'total_net' => $p->sum('net_salary'), 'count' => $p->count()];
        }

        return $data + [
            'tax_groups' => $groups, 'standalone_taxes' => $standalone, 'tax_columns' => $columns,
            'staff_rows' => $rows, 'grand_totals' => $grand, 'staff_count' => count($rows), 'salary_bands' => $salaryBands,
            'avg_effective_rate' => $rates->count() ? round($rates->avg(), 2) : 0,
            'min_effective_rate' => $rates->count() ? $rates->min() : 0,
            'max_effective_rate' => $rates->count() ? $rates->max() : 0,
            'historical_data' => $history,
        ];
    }
}
