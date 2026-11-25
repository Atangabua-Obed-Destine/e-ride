<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Http\Controllers\Ledger\LedgerController;
use App\Ledger\Ledger;
use App\Ledger\Models\PaymentAccount;
use App\Ledger\Models\PayrollSetting;
use App\Ledger\Models\Staff;
use App\Ledger\Models\StaffLoan;
use App\Ledger\Models\TransactionMapping;
use App\Ledger\Services\LedgerSync;
use App\Ledger\Services\PaymentBook;
use App\Ledger\Services\PostingRules;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * paxhitestbed Admin\StaffLoanController: loans and salary advances, repaid by a deduction on each
 * payroll. Giving one posts Dr 421 staff advances / Cr the payment account it is paid from (E-Ride:
 * chosen on the form, the Payroll settings' account by default), through the review dialog.
 * Cancelling (only before anything was taken on a payroll) reverses that entry.
 */
class StaffLoanController extends LedgerController
{
    public function index(Request $request)
    {
        $this->allow('staff-loan-view', 'staff-loan-action');
        $selected = $request->get('status', 'open');
        $rows = StaffLoan::with(['staff', 'paymentAccount'])->orderByDesc('id')->get()->map(function ($loan) {
            $loan->repaid_amount = $loan->repaid();
            $loan->pending_amount = $loan->pending();
            $loan->balance_amount = round(max(0, $loan->amount - $loan->repaid_amount), 2);
            $loan->state = $loan->status === 'cancelled' ? 'cancelled' : ($loan->balance_amount <= 0.004 ? 'repaid' : 'open');
            $loan->has_lines = $loan->lines()->exists();

            return $loan;
        });
        $states = TransactionMapping::where('transaction_type', PostingRules::STAFF_LOAN)->whereIn('transaction_id', $rows->pluck('id')->map(fn ($i) => (string) $i))
            ->get(['transaction_id', 'status', 'error'])->keyBy('transaction_id');

        return view('ledger.payroll.staff-loan.index', [
            'title' => __('ledger_payroll.loans_and_advances'),
            'route' => 'ledger.staff-loan',
            'selected_status' => $selected,
            'counts' => $rows->countBy('state'),
            'rows' => $rows->filter(fn ($l) => $selected === 'all' || $l->state === $selected)->values(),
            'states' => $states,
            'staff' => Staff::where('status', 1)->orderBy('first_name')->get(),
            'kinds' => StaffLoan::KINDS,
            'payment_accounts' => PaymentAccount::where('status', 1)->orderBy('title')->get(),
            'settings' => PayrollSetting::current(),
        ]);
    }

    public function store(Request $request)
    {
        $this->allow('staff-loan-action');
        $bid = Ledger::businessId();
        $request->validate([
            'staff_id' => ['required', 'integer', Rule::exists('ledger_staff', 'id')->where('business_id', $bid)->where('status', 1)],
            'kind' => ['required', Rule::in(StaffLoan::KINDS)],
            'title' => 'nullable|string|max:100',
            'amount' => 'required|numeric|min:1',
            'instalment' => 'required|numeric|min:1|lte:amount',
            'start_month' => 'required|date_format:Y-m',
            'given_on' => 'required|date|before_or_equal:today',
            'payment_account_id' => ['required', Rule::exists('payment_accounts', 'id')->where('business_id', $bid)->where('status', 1)],
            'note' => 'nullable|string|max:500',
        ], ['instalment.lte' => __('ledger_payroll.err_instalment')]);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        if (PaymentBook::available((int) $request->payment_account_id) < round((float) $request->amount, 2) - 0.005) {
            return $this->err(back(), __('ledger_payroll.err_insufficient', ['balance' => Ledger::money(PaymentBook::available((int) $request->payment_account_id))]));
        }
        $title = trim((string) $request->title) ?: __('ledger_payroll.kind_'.$request->kind);
        try {
            DB::transaction(function () use ($request, $title) {
                $loan = StaffLoan::create([
                    'staff_id' => $request->staff_id,
                    'kind' => $request->kind,
                    'title' => $title,
                    'amount' => round((float) $request->amount, 2),
                    'instalment' => round((float) $request->instalment, 2),
                    'start_month' => Carbon::createFromFormat('Y-m-d', $request->start_month.'-01')->toDateString(),
                    'given_on' => $request->given_on,
                    'payment_account_id' => $request->payment_account_id,
                    'status' => 'active',
                    'note' => $request->note,
                    'created_by' => auth()->id(),
                ]);
                $loan->load('staff');
                PaymentBook::link('staff_loan', $loan->id, (int) $loan->payment_account_id, 'debit', (float) $loan->amount, $loan->given_on->toDateString(),
                    $loan->title.' - '.($loan->staff->name ?? ''), __('ledger_payroll.loans_and_advances'), null, $loan->staff->staff_no ?? null, auth()->id());
                // recorded in the accounts in the same transaction: a loan never exists without its entry
                $result = (new LedgerSync())->record(PostingRules::STAFF_LOAN, $loan->id);
                if ($result !== 'posted') {
                    $map = TransactionMapping::where('transaction_type', PostingRules::STAFF_LOAN)->where('transaction_id', (string) $loan->id)->first();
                    throw new \RuntimeException($map->error ?? __('ledger_payroll.err_not_posted'));
                }
            });
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.staff-loan.index'), __('ledger_payroll.loan_recorded', ['month' => Carbon::createFromFormat('Y-m-d', $request->start_month.'-01')->translatedFormat('F Y')]));
    }

    /** Cancel a loan entered by mistake, only before anything was taken for it on a payroll. */
    public function cancel(Request $request, $id)
    {
        $this->allow('staff-loan-action');
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        $loan = StaffLoan::findOrFail($id);
        if ($loan->status !== 'active') {
            return $this->err(back(), __('ledger_payroll.err_loan_cancelled'));
        }
        if ($loan->lines()->exists()) {
            return $this->err(back(), __('ledger_payroll.err_loan_on_payroll'));
        }
        try {
            DB::transaction(function () use ($loan) {
                $loan->forceFill(['status' => 'cancelled', 'updated_by' => auth()->id()])->save();
                PaymentBook::remove('staff_loan', $loan->id);
                $result = (new LedgerSync())->record(PostingRules::STAFF_LOAN, $loan->id);
                if (! in_array($result, ['reversed', 'none'], true)) {
                    throw new \RuntimeException(__('ledger_payroll.err_not_posted'));
                }
            });
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(back(), __('ledger_payroll.loan_cancelled'));
    }
}
