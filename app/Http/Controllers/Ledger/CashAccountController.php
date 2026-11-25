<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\DefaultAccountMapping;
use App\Ledger\Services\LedgerHealth;
use App\Ledger\Services\Preview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cash and bank in the ledger (paxhitestbed Admin\CashAccountController):
 * the ledger account each payment method posts to, and the ledger account
 * behind each payment account (E-Ride: paxhitestbed's payment_accounts: cash boxes,
 * bank accounts, mobile-money wallets). A payment that names a payment
 * account posts to that account's ledger account (payment_accounts.chart_of_account_id);
 * otherwise to its method's (PostingRules::cashAccount).
 */
class CashAccountController extends LedgerController
{
    public function index()
    {
        $this->allow('transaction-mapping-settings');
        $ledgerAccounts = $this->cashLedgerAccounts();
        $mapped = DefaultAccountMapping::where('mapping_type', 'payment_method')->get()->keyBy('category_key');
        $methods = [];
        foreach (LedgerHealth::paymentMethods() as $key => $label) {
            $m = $mapped->get($key);
            $methods[$key] = ['label' => $label, 'account' => $m ? ($m->debit_account_id ?: $m->credit_account_id) : null];
        }

        return view('ledger.cash-accounts.index', [
            'ledgerAccounts' => $ledgerAccounts,
            'methods' => $methods,
            'paymentAccounts' => $this->paymentAccounts(),
            'comparison' => $this->comparison(),
        ]);
    }

    public function update(Request $request)
    {
        $this->allow('transaction-mapping-settings');
        $ids = $this->cashLedgerAccounts()->pluck('id')->all();
        $rule = ['nullable', 'integer', \Illuminate\Validation\Rule::in($ids)];
        $validated = $request->validate([
            'methods' => ['nullable', 'array'],
            'methods.*' => $rule,
            'links' => ['nullable', 'array'],
            'links.*' => $rule,
        ], ['in' => __('ledger_map.err_cash_class5')]);

        $methods = LedgerHealth::paymentMethods();
        $own = $this->paymentAccounts()->pluck('id')->flip();
        DB::transaction(function () use ($validated, $methods, $own) {
            foreach ($validated['methods'] ?? [] as $method => $acc) {
                // a method always needs an account: a blank choice leaves it as it is
                if (! $acc || ! isset($methods[$method])) {
                    continue;
                }
                $row = DefaultAccountMapping::where('mapping_type', 'payment_method')->where('category_key', $method)->first()
                    ?: new DefaultAccountMapping(['mapping_type' => 'payment_method', 'category_key' => (string) $method, 'created_by' => auth()->id()]);
                $row->fill(['debit_account_id' => (int) $acc, 'credit_account_id' => (int) $acc, 'status' => 1, 'updated_by' => auth()->id()])->save();
            }
            foreach ($validated['links'] ?? [] as $accountId => $ledgerId) {
                if (! isset($own[(int) $accountId])) {
                    continue;
                }
                // E-Ride: paxhitestbed's payment accounts; a payment account always keeps a ledger account
                if ($ledgerId) {
                    DB::table('payment_accounts')->where('business_id', Ledger::businessId())->where('id', (int) $accountId)
                        ->update(['chart_of_account_id' => $ledgerId, 'updated_at' => now()]);
                }
            }
        });

        return $this->ok(route('ledger.cash-accounts'), __('ledger_map.cash_saved'));
    }

    /** Class-5 detail accounts, less 59 (provisions on treasury). */
    private function cashLedgerAccounts()
    {
        return ChartOfAccount::postable()->where('account_code', 'like', '5%')->where('account_code', 'not like', '59%')
            ->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_name_fr']);
    }

    /** The payment accounts of these books that are in use (E-Ride: paxhitestbed's Payment Accounts, payment_accounts). */
    private function paymentAccounts()
    {
        return DB::table('payment_accounts')->where('business_id', Ledger::businessId())->where('status', 1)
            ->orderBy('title')->get(['id', DB::raw('title as name'), 'account_number', 'chart_of_account_id', 'current_balance']);
    }

    /**
     * Books beside the ledger: what the payment accounts show (their book: credits less
     * debits, payment_account_transactions) beside the balance of the ledger account they
     * are linked to (paxhitestbed CashAccounts::comparison).
     */
    private function comparison()
    {
        $b = Ledger::businessId();
        $linked = $this->paymentAccounts()->whereNotNull('chart_of_account_id')->groupBy('chart_of_account_id');
        if ($linked->isEmpty()) {
            return collect();
        }
        $book = DB::table('payment_account_transactions as at')->where('at.business_id', $b)
            ->whereIn('at.payment_account_id', $linked->flatten()->pluck('id'))
            ->groupBy('at.payment_account_id')->selectRaw("at.payment_account_id as account_id, SUM(IF(at.transaction_type = 'credit', at.amount, -at.amount)) as bal")
            ->pluck('bal', 'account_id');
        $ledger = DB::table('journal_entry_lines as l')->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->where('j.business_id', $b)->whereNull('j.deleted_at')->where('j.is_posted', 1)
            ->whereIn('l.account_id', $linked->keys())
            ->groupBy('l.account_id')->selectRaw('l.account_id, SUM(l.debit) - SUM(l.credit) as bal')->pluck('bal', 'account_id');
        $coa = ChartOfAccount::whereIn('id', $linked->keys())->get()->keyBy('id');

        return $linked->map(function ($accounts, $coaId) use ($book, $ledger, $coa) {
            $a = $coa[$coaId] ?? null;
            $bookBal = round($accounts->sum(fn ($x) => (float) ($book[$x->id] ?? 0)), 2);
            $ledgerBal = round((float) ($ledger[$coaId] ?? 0), 2);

            return (object) [
                'account' => $a ? $a->account_code.' - '.Preview::accountName($a) : '#'.$coaId,
                'books' => $accounts->pluck('name')->implode(', '),
                'book_balance' => $bookBal,
                'ledger_balance' => $ledgerBal,
                'difference' => round($ledgerBal - $bookBal, 2),
            ];
        })->values();
    }
}
