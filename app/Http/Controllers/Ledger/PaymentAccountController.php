<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\Expense;
use App\Ledger\Models\Income;
use App\Ledger\Models\PaymentAccount;
use App\Ledger\Models\PaymentAccountTransaction;
use App\Ledger\Models\PaymentAccountType;
use App\Ledger\Models\TransactionMapping;
use App\Ledger\Services\LedgerSync;
use App\Ledger\Services\PaymentBook;
use App\Ledger\Services\PostingRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Payment accounts (paxhitestbed Admin\PaymentAccountController): the list, add /
 * edit / delete, the account book, deposits, withdrawals, and correcting or
 * deleting a manual movement.
 *
 * E-Ride: an account must be linked to its class-5 ledger account (paxhitestbed
 * allowed none, and its money then never reached the ledger). A deposit, a
 * withdrawal or an opening balance may name the other side of its entry (the
 * "Other side in the ledger"); then it posts (PostingRules::forPaymentAccountTransaction),
 * through the review. Without it, as in paxhitestbed, only the book moves, and
 * Cash accounts shows the book beside the ledger.
 */
class PaymentAccountController extends LedgerController
{
    public function index()
    {
        $this->allow('payment-account-view');
        PaymentBook::ensureDefaults();

        return view('ledger.payment-account.index', [
            'payment_accounts' => PaymentAccount::with('accountType', 'ledgerAccount', 'creator')->orderByDesc('created_at')->get(),
            'unlinked_count' => Income::whereNull('payment_account_id')->where('status', 1)->count() + Expense::whereNull('payment_account_id')->where('status', 1)->count(),
        ]);
    }

    public function create()
    {
        $this->allow('payment-account-create');
        PaymentBook::ensureDefaults();

        return view('ledger.payment-account.form', $this->formData(null));
    }

    private function formData(?PaymentAccount $a): array
    {
        return [
            'payment_account' => $a,
            'account_types' => PaymentAccountType::where('status', 1)->get(),
            'ledger_accounts' => PaymentBook::cashLedgerAccounts(),
            'contra_accounts' => ChartOfAccount::postable()->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_name_fr']),
            'opening_contra' => \App\Ledger\Services\DefaultAccount::id('opening_balance'),
        ];
    }

    private function rules(bool $create): array
    {
        $bid = Ledger::businessId();
        $cash = PaymentBook::cashLedgerAccounts()->pluck('id')->all();

        return array_filter([
            'title' => 'required|string|max:255',
            'account_type_id' => ['required', Rule::exists('payment_account_types', 'id')->where('business_id', $bid)],
            'account_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:0,1',
            'chart_of_account_id' => ['required', 'integer', Rule::in($cash)],
            'opening_balance' => $create ? 'required|numeric|min:0' : null,
            'contra_account_id' => $create ? ['nullable', Rule::exists('chart_of_accounts', 'id')->where('business_id', $bid)] : null,
        ]);
    }

    public function store(Request $request)
    {
        $this->allow('payment-account-create');
        $request->validate($this->rules(true), ['chart_of_account_id.in' => __('ledger_accounts.err_class5')],
            ['chart_of_account_id' => __('ledger_accounts.ledger_account')]);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        $msg = __('ledger_accounts.payment_account_created_successfully');
        DB::transaction(function () use ($request, &$msg) {
            $opening = round((float) $request->opening_balance, 2);
            $account = PaymentAccount::create([
                'title' => $request->title, 'account_number' => $request->account_number, 'account_type_id' => $request->account_type_id,
                'chart_of_account_id' => $request->chart_of_account_id, 'opening_balance' => $opening, 'current_balance' => 0,
                'description' => $request->description, 'status' => $request->status ?? 1, 'created_by' => auth()->id(),
            ]);
            if ($opening > 0) {
                $tx = PaymentBook::record([
                    'payment_account_id' => $account->id, 'transaction_type' => 'credit', 'amount' => $opening,
                    // E-Ride: what the account held on the day the books start (the opening balance's own date)
                    'transaction_date' => $this->openingDate(), 'title' => __('ledger_accounts.opening_balance'),
                    'description' => __('ledger_accounts.opening_balance_desc'), 'reference_type' => 'opening_balance',
                    'contra_account_id' => $request->contra_account_id ?: null, 'created_by' => auth()->id(),
                ]);
                $msg .= $this->sync($tx);
            }
        });
        // E-Ride: ticket money and till counts already posted on its ledger account join its book
        \App\Ledger\ERide\BusBook::backfill();

        return $this->ok(route('ledger.payment-account.index'), $msg);
    }

    /** E-Ride: the books' start date (an opening balance is what the account held then), or today. */
    private function openingDate(): string
    {
        $from = \App\Ledger\Models\AccountingSetting::getValue('post_from_date');

        return $from && $from < now()->toDateString() ? (string) $from : now()->toDateString();
    }

    public function edit($id)
    {
        $this->allow('payment-account-edit');

        return view('ledger.payment-account.form', $this->formData(PaymentAccount::findOrFail($id)));
    }

    public function update(Request $request, $id)
    {
        $this->allow('payment-account-edit');
        $account = PaymentAccount::findOrFail($id);
        $request->validate($this->rules(false), ['chart_of_account_id.in' => __('ledger_accounts.err_class5')],
            ['chart_of_account_id' => __('ledger_accounts.ledger_account')]);
        $account->update([
            'title' => $request->title, 'account_number' => $request->account_number, 'account_type_id' => $request->account_type_id,
            'chart_of_account_id' => $request->chart_of_account_id, 'description' => $request->description,
            'status' => $request->status ?? 1, 'updated_by' => auth()->id(),
        ]);
        if ($account->wasChanged(['chart_of_account_id', 'status'])) {
            \App\Ledger\ERide\BusBook::rebuild(); // E-Ride: the bus money follows the account's ledger account
        }

        return $this->ok(route('ledger.payment-account.index'), __('ledger_accounts.payment_account_updated_successfully'));
    }

    public function destroy($id)
    {
        $this->allow('payment-account-delete');
        $account = PaymentAccount::findOrFail($id);
        if (PaymentAccountTransaction::where('payment_account_id', $account->id)->exists()) {
            return $this->err(back(), __('ledger_accounts.cannot_delete_account_with_transactions'));
        }
        $account->delete();

        return $this->ok(route('ledger.payment-account.index'), __('ledger_accounts.payment_account_deleted_successfully'));
    }

    public function accountBook(Request $request, $id)
    {
        $this->allow('payment-account-book');
        $account = PaymentAccount::with('accountType', 'ledgerAccount')->findOrFail($id);
        $q = PaymentAccountTransaction::with('creator', 'contraAccount')->where('payment_account_id', $id)
            ->when($request->filled('date_from'), fn ($x) => $x->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($x) => $x->whereDate('transaction_date', '<=', $request->date_to))
            ->when(in_array($request->transaction_type, ['credit', 'debit'], true), fn ($x) => $x->where('transaction_type', $request->transaction_type));
        $transactions = $q->orderByDesc('transaction_date')->orderByDesc('id')->paginate(50)->appends($request->query());
        $states = TransactionMapping::where('transaction_type', PostingRules::PAYMENT_ACCOUNT_TXN)
            ->whereIn('transaction_id', collect($transactions->items())->pluck('id')->map(fn ($i) => (string) $i))->get()->keyBy('transaction_id');

        return view('ledger.payment-account.account-book', ['payment_account' => $account, 'transactions' => $transactions, 'states' => $states]);
    }

    public function deposit(Request $request, $id)
    {
        return $this->movement($request, $id, 'credit');
    }

    public function withdraw(Request $request, $id)
    {
        return $this->movement($request, $id, 'debit');
    }

    /** A deposit (credit, money in) or a withdrawal (debit, money out): the form, or the posting. */
    private function movement(Request $request, $id, string $type)
    {
        $deposit = $type === 'credit';
        $this->allow($deposit ? 'payment-account-deposit' : 'payment-account-withdraw');
        $account = PaymentAccount::findOrFail($id);
        if (! $request->isMethod('post')) {
            return view('ledger.payment-account.movement', ['payment_account' => $account, 'type' => $type,
                'contra_accounts' => ChartOfAccount::postable()->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_name_fr'])]);
        }
        $this->validateMovement($request);
        if (! $deposit && (float) $account->current_balance < round((float) $request->amount, 2) - 0.005) {
            return $this->err(back(), __('ledger_accounts.insufficient_balance'));
        }
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        $msg = __($deposit ? 'ledger_accounts.deposit_successful' : 'ledger_accounts.withdrawal_successful');
        DB::transaction(function () use ($request, $account, $type, &$msg) {
            $tx = PaymentBook::record([
                'payment_account_id' => $account->id, 'transaction_type' => $type, 'amount' => round((float) $request->amount, 2),
                'transaction_date' => $request->transaction_date, 'title' => $request->title, 'description' => $request->description,
                'payment_method' => $request->payment_method, 'payment_reference' => $request->payment_reference,
                'contra_account_id' => $request->contra_account_id ?: null, 'attach' => $this->upload($request), 'created_by' => auth()->id(),
            ]);
            $msg .= $this->sync($tx);
        });

        return $this->ok(route('ledger.payment-account.account-book', $account->id), $msg);
    }

    private function validateMovement(Request $request): void
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'transaction_date' => 'required|date|before_or_equal:today',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'payment_method' => 'nullable|string|max:255',
            'payment_reference' => 'nullable|string|max:255',
            'contra_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('business_id', Ledger::businessId())],
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ], [], ['amount' => __('ledger_accounts.amount'), 'title' => __('ledger_accounts.field_title')]);
    }

    public function getTransactions($id)
    {
        $this->allow('payment-account-book');

        return response()->json(PaymentAccountTransaction::where('payment_account_id', PaymentAccount::findOrFail($id)->id)
            ->orderByDesc('transaction_date')->orderByDesc('id')->get());
    }

    public function editTransaction($id)
    {
        $this->allow('payment-account-transaction-edit');
        $tx = PaymentAccountTransaction::with('paymentAccount')->findOrFail($id);
        if (! $tx->isManual()) {
            return $this->err(back(), __('ledger_accounts.cannot_edit_linked_transaction'));
        }

        return view('ledger.payment-account.transaction-edit', ['transaction' => $tx,
            'contra_accounts' => ChartOfAccount::postable()->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_name_fr'])]);
    }

    public function updateTransaction(Request $request, $id)
    {
        $this->allow('payment-account-transaction-edit');
        $tx = PaymentAccountTransaction::findOrFail($id);
        if (! $tx->isManual()) {
            return $this->err(back(), __('ledger_accounts.cannot_edit_linked_transaction'));
        }
        $this->validateMovement($request);
        if ($tx->transaction_type === 'debit') {
            $available = (float) $tx->paymentAccount->current_balance + (float) $tx->amount;
            if ($available < round((float) $request->amount, 2) - 0.005) {
                return $this->err(back(), __('ledger_accounts.insufficient_balance'));
            }
        }
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        $msg = __('ledger_accounts.transaction_updated_successfully');
        DB::transaction(function () use ($request, $tx, &$msg) {
            $file = $this->upload($request);
            $tx->update([
                'amount' => round((float) $request->amount, 2), 'transaction_date' => $request->transaction_date, 'title' => $request->title,
                'description' => $request->description, 'payment_method' => $request->payment_method, 'payment_reference' => $request->payment_reference,
                'contra_account_id' => $request->contra_account_id ?: null, 'attach' => $file ?: $tx->attach,
            ]);
            PaymentBook::recompute((int) $tx->payment_account_id);
            $msg .= $this->sync($tx);
        });

        return $this->ok(route('ledger.payment-account.account-book', $tx->payment_account_id), $msg);
    }

    public function deleteTransaction(Request $request, $id)
    {
        $this->allow('payment-account-transaction-delete');
        $tx = PaymentAccountTransaction::findOrFail($id);
        if ($tx->reference_type === 'transfer') {
            return $this->err(route('ledger.payment-account-transfer.index'), __('ledger_accounts.delete_transfer_from_transfer_page'));
        }
        if (! $tx->isManual()) {
            return $this->err(back(), __('ledger_accounts.cannot_delete_linked_transaction'));
        }
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        DB::transaction(function () use ($tx) {
            $tx->delete();
            PaymentBook::recompute((int) $tx->payment_account_id);
            (new LedgerSync())->record(PostingRules::PAYMENT_ACCOUNT_TXN, $tx->id);
        });

        return $this->ok(route('ledger.payment-account.account-book', $tx->payment_account_id), __('ledger_accounts.transaction_deleted_successfully'));
    }

    /** Post a manual movement now (so the review shows it); the words to add when it could not be posted. */
    private function sync(PaymentAccountTransaction $tx): string
    {
        $r = (new LedgerSync())->record(PostingRules::PAYMENT_ACCOUNT_TXN, $tx->id);
        if ($r === 'failed') {
            $why = TransactionMapping::where('transaction_type', PostingRules::PAYMENT_ACCOUNT_TXN)->where('transaction_id', (string) $tx->id)->value('error');

            return ' '.__('ledger_accounts.not_posted_yet', ['why' => $why]);
        }

        return '';
    }

    private function upload(Request $request): ?string
    {
        return $request->hasFile('attach') ? $request->file('attach')->store('ledger/payment-account/'.Ledger::businessId(), 'public') : null;
    }
}
