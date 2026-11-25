<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\PaymentAccount;
use App\Ledger\Models\PaymentAccountTransfer;
use App\Ledger\Models\TransactionMapping;
use App\Ledger\Services\LedgerSync;
use App\Ledger\Services\PaymentBook;
use App\Ledger\Services\PostingRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Fund transfers between payment accounts (paxhitestbed Admin\PaymentAccountTransferController):
 * one transfer row, a debit on the sending account's book and a credit on the
 * receiving one's, and the entry Dr receiving / Cr sending ledger account
 * (PostingRules::forPaymentAccountTransfer). Editing re-posts, deleting reverses.
 */
class PaymentAccountTransferController extends LedgerController
{
    public function index()
    {
        $this->allow('payment-account-transfer-view');

        return view('ledger.payment-account.transfers.index', [
            'transfers' => PaymentAccountTransfer::with(['fromAccount', 'toAccount', 'creator'])->orderByDesc('transfer_date')->orderByDesc('id')->paginate(50),
            'states' => TransactionMapping::where('transaction_type', PostingRules::PAYMENT_TRANSFER)->get()->keyBy('transaction_id'),
        ]);
    }

    public function create(Request $request)
    {
        $this->allow('payment-account-transfer-create');

        return view('ledger.payment-account.transfers.form', [
            'transfer' => null,
            'payment_accounts' => PaymentAccount::where('status', 1)->orderBy('title')->get(),
            'from_account_id' => $request->query('from'),
            'to_account_id' => $request->query('to'),
        ]);
    }

    public function store(Request $request)
    {
        $this->allow('payment-account-transfer-create');
        $this->validateTransfer($request, null);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        $transfer = DB::transaction(function () use ($request) {
            $t = PaymentAccountTransfer::create([
                'from_account_id' => $request->from_account_id, 'to_account_id' => $request->to_account_id,
                'amount' => round((float) $request->amount, 2), 'transfer_date' => $request->transfer_date, 'note' => $request->note,
                'attach' => $this->upload($request), 'created_by' => auth()->id(),
            ]);
            $this->book($t);

            return $t;
        });

        return $this->ok(route('ledger.payment-account-transfer.index'), __('ledger_accounts.transfer_successful').$this->failed($transfer));
    }

    public function edit($id)
    {
        $this->allow('payment-account-transfer-edit');

        return view('ledger.payment-account.transfers.form', [
            'transfer' => PaymentAccountTransfer::findOrFail($id),
            'payment_accounts' => PaymentAccount::where('status', 1)->orderBy('title')->get(),
            'from_account_id' => null, 'to_account_id' => null,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->allow('payment-account-transfer-edit');
        $t = PaymentAccountTransfer::findOrFail($id);
        $this->validateTransfer($request, $t);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        DB::transaction(function () use ($request, $t) {
            $file = $this->upload($request);
            $t->update(['from_account_id' => $request->from_account_id, 'to_account_id' => $request->to_account_id,
                'amount' => round((float) $request->amount, 2), 'transfer_date' => $request->transfer_date, 'note' => $request->note,
                'attach' => $file ?: $t->attach, 'updated_by' => auth()->id()]);
            $this->book($t->fresh());
        });

        return $this->ok(route('ledger.payment-account-transfer.index'), __('ledger_accounts.transfer_updated_successfully').$this->failed($t));
    }

    public function destroy(Request $request, $id)
    {
        $this->allow('payment-account-transfer-delete');
        $t = PaymentAccountTransfer::findOrFail($id);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        DB::transaction(function () use ($t) {
            PaymentBook::remove('transfer', $t->id);
            $t->delete();
            (new LedgerSync())->record(PostingRules::PAYMENT_TRANSFER, $t->id);
        });

        return $this->ok(route('ledger.payment-account-transfer.index'), __('ledger_accounts.transfer_deleted_successfully'));
    }

    private function validateTransfer(Request $request, ?PaymentAccountTransfer $t): void
    {
        $bid = Ledger::businessId();
        $request->validate([
            'from_account_id' => ['required', Rule::exists('payment_accounts', 'id')->where('business_id', $bid)],
            'to_account_id' => ['required', 'different:from_account_id', Rule::exists('payment_accounts', 'id')->where('business_id', $bid)],
            'amount' => 'required|numeric|min:1',
            'transfer_date' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string',
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ], ['to_account_id.different' => __('ledger_accounts.cannot_transfer_to_same_account')],
            ['from_account_id' => __('ledger_accounts.from_account'), 'to_account_id' => __('ledger_accounts.to_account'), 'amount' => __('ledger_accounts.amount')]);
        // the sending account must hold the money (on an edit, what this transfer took from it is back)
        $available = PaymentBook::available((int) $request->from_account_id, 'transfer', $t->id ?? null);
        if ($t) {
            // the transfer's two movements share reference (transfer, id): only the debit is on the sending account
            $available = (float) PaymentAccount::whereKey($request->from_account_id)->value('current_balance')
                + ((int) $t->from_account_id === (int) $request->from_account_id ? (float) $t->amount : 0)
                - ((int) $t->to_account_id === (int) $request->from_account_id ? (float) $t->amount : 0);
        }
        if ($available < round((float) $request->amount, 2) - 0.005) {
            throw \Illuminate\Validation\ValidationException::withMessages(['amount' => __('ledger_accounts.insufficient_balance_in_from_account')
                .' ('.__('ledger_accounts.available_balance').': '.Ledger::money($available).')']);
        }
    }

    /** The two movements on the books, then the entry (at once, so the review shows it). */
    private function book(PaymentAccountTransfer $t): void
    {
        $t->load('fromAccount', 'toAccount');
        // the debit and the credit are kept as two rows with reference (transfer, id): rebuilt each time
        PaymentBook::remove('transfer', $t->id);
        foreach ([['from', 'debit', __('ledger_accounts.transfer_to').' '.$t->toAccount->title], ['to', 'credit', __('ledger_accounts.transfer_from').' '.$t->fromAccount->title]] as [$side, $type, $title]) {
            PaymentBook::record(['payment_account_id' => $t->{$side.'_account_id'}, 'transaction_type' => $type, 'amount' => (float) $t->amount,
                'transaction_date' => $t->transfer_date->toDateString(), 'title' => $title, 'description' => $t->note,
                'reference_type' => 'transfer', 'reference_id' => $t->id, 'attach' => $t->attach, 'created_by' => auth()->id()]);
        }
        (new LedgerSync())->record(PostingRules::PAYMENT_TRANSFER, $t->id);
    }

    private function failed(PaymentAccountTransfer $t): string
    {
        $map = TransactionMapping::where('transaction_type', PostingRules::PAYMENT_TRANSFER)->where('transaction_id', (string) $t->id)->first();

        return $map && $map->status === 'failed' ? ' '.__('ledger_accounts.not_posted_yet', ['why' => $map->error]) : '';
    }

    private function upload(Request $request): ?string
    {
        return $request->hasFile('attach') ? $request->file('attach')->store('ledger/payment-account/'.Ledger::businessId(), 'public') : null;
    }
}
