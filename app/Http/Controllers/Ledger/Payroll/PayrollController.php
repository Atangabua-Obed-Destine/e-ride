<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Http\Controllers\Ledger\LedgerController;
use App\Ledger\Ledger;
use App\Ledger\Models\AllowanceType;
use App\Ledger\Models\DeductionType;
use App\Ledger\Models\Department;
use App\Ledger\Models\Designation;
use App\Ledger\Models\PaymentAccount;
use App\Ledger\Models\Payroll;
use App\Ledger\Models\PayrollSetting;
use App\Ledger\Models\PrintSetting;
use App\Ledger\Models\Staff;
use App\Ledger\Models\StaffLoan;
use App\Ledger\Models\TaxGroup;
use App\Ledger\Payroll\PayrollBankFile;
use App\Ledger\Payroll\PayrollCalculator;
use App\Ledger\Payroll\PayrollPayer;
use App\Ledger\Payroll\PayrollTaxBreakdownService;
use App\Ledger\Payroll\PayrollWriter;
use App\Ledger\Payroll\TaxDefaults;
use App\Ledger\Services\PaymentBook;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * paxhitestbed Admin\PayrollController: the month's list (generate, approve, pay, unpay, bank file,
 * bulk), the generate screen (its problems panel and tax panel, previewed by the server's one
 * calculator, a confirmation, then saved), the payslip (print and PDF) and the payroll report.
 *
 * E-Ride differences: the staff are the register's (not users); monthly pay only (hourly reads
 * attendance, not ported, so the attendance section is left out); pay is made FROM a payment
 * account; paying and unpaying go through the review dialog (route middleware ledger.review, and
 * refused here without reviewed=1); payslips are not emailed.
 */
class PayrollController extends LedgerController
{
    private function base(): array
    {
        return ['title' => __('ledger_payroll.payroll'), 'route' => 'ledger.payroll', 'access' => 'payroll'];
    }

    public function index(Request $request)
    {
        $this->allow('payroll-view', 'payroll-action', 'payroll-print');
        TaxDefaults::ensure();
        $data = $this->base();
        $data['selected_salary_type'] = $request->input('salary_type', '1');
        $data['selected_department'] = $request->department ?: '0';
        $data['selected_designation'] = $request->designation ?: '0';
        $data['selected_month'] = (int) ($request->month ?: date('m'));
        $data['selected_year'] = (int) ($request->year ?: date('Y'));
        $data['departments'] = Department::where('status', 1)->orderBy('title')->get();
        $data['designations'] = Designation::where('status', 1)->orderBy('title')->get();
        $data['settings'] = PayrollSetting::current();
        $data['payment_accounts'] = PaymentAccount::where('status', 1)->orderBy('title')->get();
        $data['methods'] = PaymentBook::methods();

        $staff = Staff::with(['designation', 'department'])->where('status', 1)->where('salary_type', Staff::SALARY_FIXED);
        if ($request->filled('department')) {
            $staff->where('department_id', $request->department);
        }
        if ($request->filled('designation')) {
            $staff->where('designation_id', $request->designation);
        }
        $data['rows'] = $staff->orderBy('staff_no')->get();
        $data['payrolls'] = Payroll::with('approver')->whereYear('salary_month', $data['selected_year'])->whereMonth('salary_month', $data['selected_month'])
            ->get()->keyBy('staff_id');

        return view('ledger.payroll.payroll.index', $data);
    }

    /** The payroll screen for one staff member and month, with PayrollCalculator's figures. */
    public function generate($id, $month, $year)
    {
        $this->allow('payroll-action');
        TaxDefaults::ensure();
        $data = $this->base();
        $data['selected_month'] = (int) $month;
        $data['selected_year'] = (int) $year;
        $staff = $data['row'] = Staff::with(['designation', 'department'])->where('status', 1)->findOrFail($id);
        $salaryMonth = Carbon::createFromDate((int) $year, (int) $month, 1)->format('Y-m-d');
        $payroll = $data['payroll'] = Payroll::with('details')->where('staff_id', $staff->id)->whereDate('salary_month', $salaryMonth)->first();
        if ($payroll && $payroll->isPaid()) {
            return $this->err(route('ledger.payroll.index', ['month' => $month, 'year' => $year]), __('ledger_payroll.err_paid_locked'));
        }

        if ($payroll) {
            $allowances = $payroll->details->where('status', 1)->map(fn ($d) => ['title' => $d->title, 'amount' => $d->amount])->values()->all();
            $deductions = $payroll->details->where('status', 0)->map(fn ($d) => ['title' => $d->title, 'amount' => $d->amount, 'loan_id' => $d->staff_loan_id])->values()->all();
            $data['carried_from'] = null;
            // a loan given after this payroll was generated: its instalment is due but not on it yet
            $onPayroll = $payroll->details->pluck('staff_loan_id')->filter()->all();
            foreach (StaffLoan::dueLines($staff->id, $salaryMonth, $payroll->id) as $line) {
                if (! in_array($line['loan_id'], $onPayroll)) {
                    $deductions[] = $line;
                }
            }
        } else {
            $start = app(PayrollWriter::class)->startingLines($staff, $salaryMonth);
            $allowances = $start['allowances'];
            $deductions = $start['deductions'];
            $data['carried_from'] = $start['from'];
        }

        $data['calc'] = app(PayrollCalculator::class)->calculate($staff, $salaryMonth, $allowances, $deductions, $payroll);
        $data['start_allowances'] = $allowances;
        $data['start_deductions'] = $deductions;
        $data['open_loans'] = StaffLoan::where('staff_id', $staff->id)->where('status', 'active')->get()
            ->filter(fn ($loan) => $loan->available($payroll?->id) > 0)->values();
        $data['tax_groups'] = TaxGroup::getEffectiveGroups(Carbon::parse($salaryMonth));
        $data['allowance_types'] = AllowanceType::where('status', 1)->orderBy('title')->get();
        $data['deduction_types'] = DeductionType::where('status', 1)->orderBy('title')->get();
        $data['salary_month'] = $salaryMonth;

        return view('ledger.payroll.payroll.generate', $data);
    }

    /** The figures as lines are typed: the server's calculator, so the page cannot show what saving would refuse. */
    public function preview(Request $request)
    {
        $this->allow('payroll-action');
        $request->validate(['staff_id' => 'required|integer', 'salary_month' => 'required|date']);
        $staff = Staff::where('status', 1)->findOrFail($request->staff_id);
        $existing = Payroll::where('staff_id', $staff->id)->whereDate('salary_month', Carbon::parse($request->salary_month)->startOfMonth())->first();

        return response()->json(app(PayrollCalculator::class)->calculate($staff, $request->salary_month,
            $this->postedLines($request, 'allowance_titles', 'allowances'),
            $this->postedLines($request, 'deduction_titles', 'deductions', 'deduction_loans'), $existing));
    }

    /**
     * Save a payroll: every figure from the calculator; the figures the page posted are only
     * compared, and a disagreement refuses the save (paxhitestbed's rule).
     */
    public function store(Request $request)
    {
        $this->allow('payroll-action');
        $request->validate([
            'staff_id' => 'required|integer',
            'salary_month' => 'required|date',
            'allowance_titles' => 'nullable|array', 'allowances' => 'nullable|array',
            'deduction_titles' => 'nullable|array', 'deductions' => 'nullable|array',
            'basic_salary' => 'nullable|numeric', 'total_earning' => 'nullable|numeric', 'total_allowance' => 'nullable|numeric',
            'total_deduction' => 'nullable|numeric', 'gross_salary' => 'nullable|numeric', 'tax' => 'nullable|numeric',
            'employer_tax' => 'nullable|numeric', 'net_salary' => 'nullable|numeric',
        ]);
        $staff = Staff::where('status', 1)->findOrFail($request->staff_id);
        $salaryMonth = Carbon::parse($request->salary_month)->startOfMonth()->format('Y-m-d');
        $existing = Payroll::where('staff_id', $staff->id)->whereDate('salary_month', $salaryMonth)->first();
        if ($existing && $existing->isPaid()) {
            return $this->err(back(), __('ledger_payroll.err_paid_locked'));
        }
        $calc = app(PayrollCalculator::class)->calculate($staff, $salaryMonth,
            $this->postedLines($request, 'allowance_titles', 'allowances'),
            $this->postedLines($request, 'deduction_titles', 'deductions', 'deduction_loans'), $existing);
        if (! empty($calc['problems'])) {
            return $this->err(back(), __('ledger_payroll.not_saved').' '.implode(' ', $calc['problems']));
        }
        $disagree = [];
        foreach (['basic_salary', 'total_earning', 'total_allowance', 'total_deduction', 'gross_salary', 'tax', 'employer_tax', 'net_salary'] as $field) {
            if ($request->filled($field) && abs((float) $request->input($field) - (float) $calc[$field]) > 2.0) {
                $disagree[] = __('ledger_payroll.figure_disagrees', ['label' => __('ledger_payroll.field_'.$field),
                    'shown' => Ledger::money($request->input($field)), 'computed' => Ledger::money($calc[$field])]);
            }
        }
        if ($disagree) {
            return $this->err(back(), __('ledger_payroll.err_figures_changed').' '.implode('; ', $disagree).'.');
        }
        try {
            app(PayrollWriter::class)->save($staff, $salaryMonth, $calc, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.payroll.index', ['salary_type' => 1, 'month' => Carbon::parse($salaryMonth)->format('m'),
            'year' => Carbon::parse($salaryMonth)->format('Y')]), __('ledger_payroll.created'));
    }

    public function pay(Request $request, $id)
    {
        $this->allow('payroll-action');
        $request->validate($this->payRules());
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        $payroll = Payroll::findOrFail($id);
        try {
            $result = app(PayrollPayer::class)->pay($payroll, $request->only('pay_date', 'payment_method', 'payment_account_id', 'note'), auth()->id());
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(back(), $result['journal'] ? __('ledger_payroll.paid_and_posted')
            : __('ledger_payroll.paid_not_posted', ['why' => $result['why'] ?: __('ledger_payroll.check_mapping')]));
    }

    /** Reverse a payment: the entry is reversed (never deleted); a posted one that cannot be reversed keeps the payroll paid. */
    public function unpay(Request $request, $id)
    {
        $this->allow('payroll-action');
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        try {
            app(PayrollPayer::class)->unpay(Payroll::findOrFail($id), auth()->id());
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(back(), __('ledger_payroll.unpaid_reversed'));
    }

    /** Approve a generated payroll (when the books require approval before paying): the manager's. */
    public function approve($id)
    {
        $this->allow('payroll-approve');
        $payroll = Payroll::findOrFail($id);
        if ($payroll->isPaid()) {
            return $this->err(back(), __('ledger_payroll.err_already_paid'));
        }
        if (! $payroll->isApproved()) {
            $payroll->forceFill(['approved_by' => auth()->id(), 'approved_at' => now()])->save();
        }

        return $this->ok(back(), __('ledger_payroll.approved_msg'));
    }

    /** Generate or approve several at once (each as the single screen would, one refusal leaves the others done). */
    public function bulk(Request $request)
    {
        $this->allow('payroll-action');
        $request->validate([
            'action' => 'required|in:generate,approve',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2000|max:2100',
            'staff' => 'required|array|min:1',
            'staff.*' => 'integer',
        ], ['staff.required' => __('ledger_payroll.tick_first')]);
        if ($request->action === 'approve') {
            $this->allow('payroll-approve');
        }

        return $this->runBulk($request, function (Staff $staff, ?Payroll $payroll, string $salaryMonth) use ($request) {
            if ($request->action === 'generate') {
                if ($payroll) {
                    return __($payroll->isPaid() ? 'ledger_payroll.already_paid' : 'ledger_payroll.already_generated');
                }
                $result = app(PayrollWriter::class)->generateFromStartingLines($staff, $salaryMonth, auth()->id());

                return $result['payroll'] ? null : implode(' ', $result['calc']['problems']);
            }
            if (! $payroll || $payroll->isPaid()) {
                return __(! $payroll ? 'ledger_payroll.not_generated' : 'ledger_payroll.already_paid');
            }
            if (! $payroll->isApproved()) {
                $payroll->forceFill(['approved_by' => auth()->id(), 'approved_at' => now()])->save();
            }

            return null;
        });
    }

    /** Pay several at once, from one payment account (posts: through the review). */
    public function bulkPay(Request $request)
    {
        $this->allow('payroll-action');
        $request->validate($this->payRules() + [
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2000|max:2100',
            'staff' => 'required|array|min:1',
            'staff.*' => 'integer',
        ], ['staff.required' => __('ledger_payroll.tick_first')]);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        $request->merge(['action' => 'pay']);
        $notes = [];

        return $this->runBulk($request, function (Staff $staff, ?Payroll $payroll) use ($request, &$notes) {
            if (! $payroll) {
                return __('ledger_payroll.not_generated');
            }
            if ($payroll->isPaid()) {
                return __('ledger_payroll.already_paid');
            }
            $result = app(PayrollPayer::class)->pay($payroll, $request->only('pay_date', 'payment_method', 'payment_account_id', 'note'), auth()->id());
            if (! $result['journal']) {
                $notes['journal'] = __('ledger_payroll.some_not_posted');
            }

            return null;
        }, $notes);
    }

    private function runBulk(Request $request, callable $one, array &$notes = [])
    {
        $salaryMonth = Carbon::createFromDate((int) $request->year, (int) $request->month, 1)->format('Y-m-d');
        $done = [];
        $skipped = [];
        foreach (Staff::whereIn('id', $request->staff)->where('status', 1)->orderBy('staff_no')->get() as $staff) {
            $payroll = Payroll::where('staff_id', $staff->id)->whereDate('salary_month', $salaryMonth)->first();
            try {
                $why = $one($staff, $payroll, $salaryMonth);
            } catch (\RuntimeException $e) {
                $why = $e->getMessage();
            }
            if ($why) {
                $skipped[] = $staff->label().': '.$why;
            } else {
                $done[] = $staff->label();
            }
        }
        $verb = __('ledger_payroll.verb_'.$request->action);

        return back()->with('payroll_bulk_result', ['action' => $request->action, 'verb' => $verb, 'done' => $done, 'skipped' => $skipped,
            'notes' => array_values($notes)])
            ->with('status', ['success' => $done ? 1 : 0, 'msg' => trans_choice('ledger_payroll.bulk_done', count($done), ['count' => count($done), 'verb' => $verb])
                .($skipped ? ' '.trans_choice('ledger_payroll.bulk_skipped', count($skipped), ['count' => count($skipped)]) : '')]);
    }

    /** The bank file for a month, in the layout set on Payroll settings. */
    public function bankFile(Request $request)
    {
        $this->allow('payroll-action');
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2000|max:2100',
            'which' => 'required|in:unpaid,paid,all',
            'staff' => 'nullable|array',
            'staff.*' => 'integer',
        ]);
        $payrolls = Payroll::with('staff')->whereYear('salary_month', $request->year)->whereMonth('salary_month', $request->month)
            ->when($request->which === 'unpaid', fn ($q) => $q->where('status', 0))
            ->when($request->which === 'paid', fn ($q) => $q->where('status', 1))
            ->when(! empty($request->staff), fn ($q) => $q->whereIn('staff_id', $request->staff))
            ->get()->sortBy(fn ($p) => $p->staff->staff_no ?? '')->values();
        if (PayrollSetting::current()->require_approval && $request->which !== 'paid') {
            $payrolls = $payrolls->filter(fn ($p) => $p->isPaid() || $p->isApproved())->values();
        }
        $file = app(PayrollBankFile::class);
        $built = $file->build($payrolls);
        if (empty($built['rows'])) {
            return $this->err(back(), __('ledger_payroll.bank_file_empty').($built['missing'] ? ' '.__('ledger_payroll.no_pay_to', ['names' => implode(', ', $built['missing'])]) : ''));
        }
        $name = 'salaries-'.sprintf('%04d-%02d', $request->year, $request->month).'-'.$request->which.'.csv';

        return response($file->csv($built['rows']), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'X-Payroll-Rows' => (string) count($built['rows']),
            'X-Payroll-Total' => (string) $built['total'],
        ]);
    }

    public function report(Request $request)
    {
        $this->allow('payroll-report');
        $data = $this->base();
        $data['title'] = __('ledger_payroll.payroll_report');
        $data['selected_department'] = $request->department ?: '0';
        $data['selected_designation'] = $request->designation ?: '0';
        $data['selected_month'] = (int) ($request->month ?: date('m'));
        $data['selected_year'] = (int) ($request->year ?: date('Y'));
        $data['departments'] = Department::where('status', 1)->orderBy('title')->get();
        $data['designations'] = Designation::where('status', 1)->orderBy('title')->get();
        $data['print'] = PrintSetting::payslip();
        $q = Payroll::with(['staff', 'paymentAccount'])->whereYear('salary_month', $data['selected_year'])->whereMonth('salary_month', $data['selected_month']);
        if ($request->filled('department')) {
            $q->whereHas('staff', fn ($s) => $s->where('department_id', $request->department));
        }
        if ($request->filled('designation')) {
            $q->whereHas('staff', fn ($s) => $s->where('designation_id', $request->designation));
        }
        $data['rows'] = $q->orderBy('id')->get();
        $data['methods'] = PaymentBook::methods();

        return view('ledger.payroll.payroll.report', $data);
    }

    /** The payslip: the taxes this payroll was charged, from its own itemised lines. */
    public function print($id)
    {
        $this->allow('payroll-print', 'payroll-action', 'payroll-view');

        return view('ledger.payroll.payroll.print', $this->payslipData(Payroll::findOrFail($id)) + ['pdf' => false]);
    }

    public function pdf($id)
    {
        $this->allow('payroll-print', 'payroll-action', 'payroll-view');
        $payroll = Payroll::findOrFail($id);
        $html = view('ledger.payroll.payroll.payslip-pdf', $this->payslipData($payroll))->render();
        $pdf = $this->getMpdf();
        $pdf->WriteHTML($html);
        $who = preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($payroll->staff->staff_no ?? $payroll->staff_id));

        return response($pdf->Output('', 'S'), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="payslip-'.trim($who, '-').'-'.$payroll->salary_month->format('Y-m').'.pdf"']);
    }

    private function payslipData(Payroll $payroll): array
    {
        $payroll->loadMissing(['staff.department', 'staff.designation', 'details', 'taxLines', 'approver', 'paymentAccount']);
        $lines = $payroll->taxLines->map(fn ($l) => ['label' => $l->label, 'employee' => (float) $l->employee_amount, 'employer' => (float) $l->employer_amount])->all();
        $reconcile = true;
        if (empty($lines) && ((float) $payroll->tax > 0 || (float) $payroll->employer_tax > 0)) {
            $breakdown = app(PayrollTaxBreakdownService::class);
            $computed = array_map(fn ($l) => ['label' => $l['label'], 'employee' => round($l['employee']), 'employer' => round($l['employer'])], $breakdown->forPayroll($payroll));
            $reconcile = $breakdown->reconciles($payroll, $computed);
            $lines = $reconcile ? $computed : [];
        }

        return $this->base() + ['row' => $payroll, 'print' => PrintSetting::payslip(), 'tax_lines' => $lines, 'tax_lines_reconcile' => $reconcile,
            'books' => \App\Ledger\ERide\Books::current(), 'methods' => PaymentBook::methods()];
    }

    private function payRules(): array
    {
        $bid = Ledger::businessId();

        return [
            'pay_date' => 'required|date|before_or_equal:today',
            'payment_method' => ['required', Rule::in(array_keys(PaymentBook::methods()))],
            'payment_account_id' => ['required', Rule::exists('payment_accounts', 'id')->where('business_id', $bid)->where('status', 1)],
            'note' => 'nullable|string|max:255',
        ];
    }

    private function postedLines(Request $request, string $titles, string $amounts, ?string $loans = null): array
    {
        $titleList = (array) $request->input($titles, []);
        $loanList = $loans ? (array) $request->input($loans, []) : [];

        return collect((array) $request->input($amounts, []))
            ->map(fn ($amount, $key) => ['title' => $titleList[$key] ?? '', 'amount' => $amount, 'loan_id' => $loanList[$key] ?? null])
            ->values()->all();
    }
}
