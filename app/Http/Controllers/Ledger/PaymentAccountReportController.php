<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\Expense;
use App\Ledger\Models\Income;
use App\Ledger\Models\PaymentAccount;
use App\Ledger\Models\PaymentAccountTransaction;
use App\Ledger\Models\TransactionMapping;
use App\Ledger\Services\LedgerSync;
use App\Ledger\Services\PaymentBook;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Payment account reports (paxhitestbed Admin\PaymentAccountReportController):
 *
 *   Cash flow report         every movement on the books, filtered, with totals
 *   Account statement        one account over a period, opening and closing balance
 *   Summary                  each account's money in / out over a period
 *   Payment account report   incomes and expenses not linked to any payment account,
 *                            and "Link" to put one on an account after the fact
 *
 * E-Ride: the school's sources (fees, instalments, payroll) are not here; linking
 * goes through the review and is gated by its own permission (paxhitestbed's
 * gotcha #3); linking also re-posts the record from the account's ledger account.
 */
class PaymentAccountReportController extends LedgerController
{
    private $route = 'ledger.payment-account-report';

    public function cashflow(Request $request)
    {
        $this->allow('payment-account-report-cashflow', 'payment-account-report-view');
        $filters = fn ($q) => $q
            ->when($request->filled('account_id'), fn ($x) => $x->where('payment_account_id', $request->account_id))
            ->when($request->filled('date_from'), fn ($x) => $x->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($x) => $x->whereDate('transaction_date', '<=', $request->date_to))
            ->when($request->filled('payment_method'), fn ($x) => $x->where('payment_method', $request->payment_method))
            ->when($request->filled('reference_type'), fn ($x) => $request->reference_type === 'manual' ? $x->whereNull('reference_type') : $x->where('reference_type', $request->reference_type));
        $sort = $request->get('sort') === 'asc' ? 'asc' : 'desc';
        $query = $filters(PaymentAccountTransaction::with(['paymentAccount', 'creator']))
            ->when(in_array($request->transaction_type, ['credit', 'debit'], true), fn ($x) => $x->where('transaction_type', $request->transaction_type))
            ->orderBy('transaction_date', $sort)->orderBy('id', $sort);
        // the totals before pagination, as paxhitestbed (the type filter does not narrow them)
        $credit = (float) $filters(PaymentAccountTransaction::query())->where('transaction_type', 'credit')->sum('amount');
        $debit = (float) $filters(PaymentAccountTransaction::query())->where('transaction_type', 'debit')->sum('amount');

        return view('ledger.payment-account.reports.cashflow', [
            'route' => $this->route,
            'accounts' => PaymentAccount::where('status', 1)->orderBy('title')->get(),
            'transactions' => $query->paginate(25)->appends($request->query()),
            'summary' => ['total_credit' => $credit, 'total_debit' => $debit, 'net_flow' => $credit - $debit,
                'transaction_count' => $filters(PaymentAccountTransaction::query())->count()],
            'account_balances' => PaymentAccount::where('status', 1)->get(['id', 'title', 'current_balance']),
            'total_balance' => (float) PaymentAccount::where('status', 1)->sum('current_balance'),
            'methods' => PaymentAccountTransaction::whereNotNull('payment_method')->distinct()->pluck('payment_method'),
            'reference_types' => PaymentAccountTransaction::whereNotNull('reference_type')->distinct()->pluck('reference_type'),
            'sort_order' => $sort,
        ]);
    }

    public function accountStatement(Request $request, $id)
    {
        $this->allow('payment-account-report-statement', 'payment-account-report-view');
        $account = PaymentAccount::findOrFail($id);
        $from = $request->get('date_from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->get('date_to', Carbon::now()->endOfMonth()->toDateString());
        $opening = PaymentAccountTransaction::where('payment_account_id', $id)->whereDate('transaction_date', '<', $from)
            ->orderByDesc('transaction_date')->orderByDesc('id')->value('balance_after') ?? 0;
        $period = fn () => PaymentAccountTransaction::where('payment_account_id', $id)->whereDate('transaction_date', '>=', $from)->whereDate('transaction_date', '<=', $to);
        $credit = (float) $period()->where('transaction_type', 'credit')->sum('amount');
        $debit = (float) $period()->where('transaction_type', 'debit')->sum('amount');

        return view('ledger.payment-account.reports.statement', [
            'route' => $this->route, 'account' => $account, 'date_from' => $from, 'date_to' => $to,
            'opening_balance' => (float) $opening,
            'transactions' => $period()->orderBy('transaction_date')->orderBy('id')->paginate(50)->appends($request->query()),
            'period_credit' => $credit, 'period_debit' => $debit,
            'closing_balance' => round((float) $opening + $credit - $debit, 2),
        ]);
    }

    public function summary(Request $request)
    {
        $this->allow('payment-account-report-summary', 'payment-account-report-view');
        $from = $request->get('date_from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->get('date_to', Carbon::now()->endOfMonth()->toDateString());
        $sums = DB::table('payment_account_transactions')->where('business_id', Ledger::businessId())
            ->whereDate('transaction_date', '>=', $from)->whereDate('transaction_date', '<=', $to)
            ->groupBy('payment_account_id')
            ->selectRaw("payment_account_id, SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) as credit,
                SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as debit, COUNT(*) as n")->get()->keyBy('payment_account_id');
        $rows = PaymentAccount::where('status', 1)->orderBy('title')->get()->map(function ($a) use ($sums) {
            $s = $sums[$a->id] ?? null;
            $credit = (float) ($s->credit ?? 0);
            $debit = (float) ($s->debit ?? 0);

            return ['account' => $a, 'credit' => $credit, 'debit' => $debit, 'net_flow' => $credit - $debit,
                'transaction_count' => (int) ($s->n ?? 0), 'current_balance' => (float) $a->current_balance];
        });

        return view('ledger.payment-account.reports.summary', [
            'route' => $this->route, 'date_from' => $from, 'date_to' => $to, 'accounts_summary' => $rows,
            'grand_total' => ['credit' => $rows->sum('credit'), 'debit' => $rows->sum('debit'), 'net_flow' => $rows->sum('net_flow'),
                'transaction_count' => $rows->sum('transaction_count'), 'current_balance' => $rows->sum('current_balance')],
        ]);
    }

    /** Incomes and expenses not linked to any payment account (paxhitestbed's fee, instalment and payroll sources are school-only). */
    public function unlinkedTransactions(Request $request)
    {
        $this->allow('payment-account-report-statement', 'payment-account-report-view');
        $type = in_array($request->get('type'), ['expense', 'income'], true) ? $request->get('type') : 'all';
        $from = $request->get('date_from');
        $to = $request->get('date_to');
        $search = $request->get('search');
        $all = collect();
        foreach (['expense' => Expense::class, 'income' => Income::class] as $kind => $class) {
            if ($type !== 'all' && $type !== $kind) {
                continue;
            }
            $rows = $class::with('category')->whereNull('payment_account_id')->where('status', 1)
                ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
                ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', "%{$search}%")->orWhere('note', 'like', "%{$search}%")))
                ->get();
            $all = $all->merge($rows->map(fn ($r) => [
                'id' => $r->id,
                'type' => $kind,
                'date' => $r->date,
                'sort' => $r->date->toDateString().' '.$r->created_at,
                'payment_ref_no' => strtoupper(substr($kind, 0, 3)).$r->date->format('Y').'/'.str_pad((string) $r->id, 4, '0', STR_PAD_LEFT),
                'invoice_no' => $r->invoice_id ?: ($r->category->name ?? '-'),
                'amount' => (float) $r->amount,
                'payment_type' => __('ledger_accounts.'.$kind),
                'account' => PaymentBook::methodLabel($r->payment_method),
                'description' => $r->title,
                'category' => $r->category->name ?? '-',
            ]));
        }
        $all = $all->sortByDesc('sort')->values();
        $page = max(1, (int) $request->get('page', 1));
        $paginator = new LengthAwarePaginator($all->forPage($page, 25), $all->count(), 25, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('ledger.payment-account.reports.unlinked', [
            'route' => $this->route,
            'payment_accounts' => PaymentAccount::where('status', 1)->orderBy('title')->get(),
            'transactions' => $paginator,
            'selected_type' => $type, 'date_from' => $from, 'date_to' => $to, 'search' => $search,
            'total' => $all->sum('amount'),
        ]);
    }

    /** Put an unlinked income or expense on a payment account: its movement on the book, and its entry re-posted from that account. */
    public function linkTransaction(Request $request)
    {
        $this->allow('payment-account-report-link', 'payment-account-report-view');
        $request->validate([
            'type' => 'required|in:expense,income',
            'id' => 'required|integer',
            'payment_account_id' => ['required', Rule::exists('payment_accounts', 'id')->where('business_id', Ledger::businessId())],
        ]);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        $class = $request->type === 'income' ? Income::class : Expense::class;
        $row = $class::findOrFail($request->id);
        $account = PaymentAccount::findOrFail($request->payment_account_id);
        if ($row->payment_account_id) {
            return $this->err(back(), __('ledger_accounts.err_already_linked'));
        }
        if ($request->type === 'expense' && (float) $account->current_balance < (float) $row->amount - 0.005) {
            return $this->err(back(), __('ledger_accounts.insufficient_balance_in_account').' ('.__('ledger_accounts.field_balance').': '.Ledger::money($account->current_balance).')');
        }
        DB::transaction(function () use ($row, $account, $request) {
            $row->forceFill(['payment_account_id' => $account->id, 'updated_by' => auth()->id()])->save();
            $income = $request->type === 'income';
            PaymentBook::link($request->type, $row->id, $account->id, $income ? 'credit' : 'debit', (float) $row->amount, $row->date->toDateString(),
                __('ledger_accounts.'.$request->type).' - '.$row->title, __('ledger_accounts.linked_from', ['what' => __('ledger_accounts.'.$request->type), 'id' => $row->id]),
                $row->payment_method, $row->reference, auth()->id());
            (new LedgerSync())->record($request->type, $row->id);
        });
        $map = TransactionMapping::where('transaction_type', $request->type)->where('transaction_id', (string) $row->id)->first();
        $msg = __('ledger_accounts.linked_successfully', ['account' => $account->title]);
        if ($map && $map->status === 'failed') {
            $msg .= ' '.__('ledger_accounts.not_posted_yet', ['why' => $map->error]);
        }

        return $this->ok(back(), $msg);
    }
}
