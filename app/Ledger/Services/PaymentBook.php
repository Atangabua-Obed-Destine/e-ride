<?php

namespace App\Ledger\Services;

use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\DefaultAccountMapping;
use App\Ledger\Models\PaymentAccount;
use App\Ledger\Models\PaymentAccountTransaction;
use App\Ledger\Models\PaymentAccountType;
use Illuminate\Support\Facades\DB;

/**
 * The payment accounts' books (paxhitestbed's PaymentAccountController /
 * Income / Expense / transfer balance mechanics), in one place.
 *
 * paxhitestbed keeps current_balance as a stored running figure that each screen
 * adds to or takes from, and its gotchas #1 and #4 are that editing or deleting an
 * income or expense never touched it, so it drifted. Here every change to a book
 * goes through link() / record() / remove(), and recompute() then rebuilds the
 * account's running balance (balance_after, in date order) and current_balance from
 * its transactions, so the book can never drift from what is in it.
 */
class PaymentBook
{
    /** Ensure the record $refType #$refId has exactly one movement on $accountId (none when $accountId is null). */
    public static function link(string $refType, int $refId, ?int $accountId, string $type, float $amount, string $date,
        string $title, ?string $description = null, ?string $method = null, ?string $reference = null, ?string $userId = null): ?PaymentAccountTransaction
    {
        $tx = PaymentAccountTransaction::where('reference_type', $refType)->where('reference_id', $refId)->first();
        $old = $tx ? (int) $tx->payment_account_id : null;
        if (! $accountId) {
            if ($tx) {
                $tx->delete();
                self::recompute($old);
            }

            return null;
        }
        $tx = $tx ?: new PaymentAccountTransaction(['reference_type' => $refType, 'reference_id' => $refId, 'created_by' => $userId]);
        $tx->fill([
            'payment_account_id' => $accountId,
            'transaction_type' => $type,
            'amount' => round($amount, 2),
            'transaction_date' => $date,
            'title' => mb_substr($title, 0, 250),
            'description' => $description,
            'payment_method' => $method,
            'payment_reference' => $reference,
        ])->save();
        self::recompute($accountId);
        if ($old && $old !== $accountId) {
            self::recompute($old);
        }

        return $tx;
    }

    /** A new movement (a deposit, a withdrawal, an opening balance, a reversal). */
    public static function record(array $data): PaymentAccountTransaction
    {
        $tx = PaymentAccountTransaction::create($data + ['balance_after' => 0]);
        self::recompute((int) $tx->payment_account_id);

        return $tx->fresh();
    }

    /** Take the movements of a record off the books. */
    public static function remove(string $refType, int $refId): void
    {
        $accounts = PaymentAccountTransaction::where('reference_type', $refType)->where('reference_id', $refId)->pluck('payment_account_id')->unique();
        PaymentAccountTransaction::where('reference_type', $refType)->where('reference_id', $refId)->get()->each->delete();
        foreach ($accounts as $a) {
            self::recompute((int) $a);
        }
    }

    /** E-Ride: accounts whose rebuild waits for the end of a sync run (null: rebuild at once). */
    private static ?array $deferred = null;

    /**
     * E-Ride: run $fn with the running balances rebuilt once at the end, not after each
     * movement (a sync run can put hundreds of ticket payments into one account's book).
     */
    public static function deferRecompute(callable $fn)
    {
        if (self::$deferred !== null) {
            return $fn();
        }
        self::$deferred = [];
        try {
            return $fn();
        } finally {
            $ids = array_keys(self::$deferred);
            self::$deferred = null;
            foreach ($ids as $id) {
                self::recompute($id);
            }
        }
    }

    /** Rebuild an account's running balance from its transactions, in date order. */
    public static function recompute(?int $accountId): void
    {
        if (! $accountId) {
            return;
        }
        if (self::$deferred !== null) {
            self::$deferred[$accountId] = true;

            return;
        }
        $balance = 0.0;
        $rows = DB::table('payment_account_transactions')->where('business_id', \App\Ledger\Ledger::businessId())
            ->where('payment_account_id', $accountId)->orderBy('transaction_date')->orderBy('id')->get(['id', 'transaction_type', 'amount', 'balance_after']);
        foreach ($rows as $r) {
            $balance = round($balance + ($r->transaction_type === 'credit' ? 1 : -1) * (float) $r->amount, 2);
            if (abs((float) $r->balance_after - $balance) > 0.001) {
                DB::table('payment_account_transactions')->where('id', $r->id)->update(['balance_after' => $balance]);
            }
        }
        PaymentAccount::whereKey($accountId)->update(['current_balance' => $balance]);
    }

    /** What an account can pay out now; on an edit, what the record already took from it is available again. */
    public static function available(int $accountId, ?string $refType = null, ?int $refId = null): float
    {
        $balance = (float) PaymentAccount::whereKey($accountId)->value('current_balance');
        if ($refType && $refId) {
            $tx = PaymentAccountTransaction::where('reference_type', $refType)->where('reference_id', $refId)->where('payment_account_id', $accountId)->first();
            if ($tx) {
                $balance += ($tx->transaction_type === 'debit' ? 1 : -1) * (float) $tx->amount;
            }
        }

        return round($balance, 2);
    }

    /**
     * What the books need before these screens are used: the three kinds of payment
     * account, and an account for incomes in a category with no mapping of its own
     * (758 Produits divers, as expenses have 638), like Set up the books does.
     */
    public static function ensureDefaults(): void
    {
        PaymentAccountType::ensureDefaults();
        if (! DefaultAccountMapping::where('mapping_type', 'income_category')->where('category_key', '')->exists()) {
            $id = ChartOfAccount::postable()->where('account_code', '758')->value('id');
            if ($id) {
                DefaultAccountMapping::create(['mapping_type' => 'income_category', 'category_key' => '',
                    'debit_account_id' => $id, 'credit_account_id' => $id, 'status' => 1, 'created_by' => auth()->id()]);
            }
        }
    }

    /** Ledger accounts money can sit on: class-5 detail accounts, less 59 (provisions). */
    public static function cashLedgerAccounts()
    {
        return ChartOfAccount::postable()->where('account_code', 'like', '5%')->where('account_code', 'not like', '59%')
            ->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_name_fr']);
    }

    public static function isCash($accountId): bool
    {
        return $accountId && self::cashLedgerAccounts()->contains('id', (int) $accountId);
    }

    /** The payment methods of these books (the bus books' methods). */
    public static function methods(): array
    {
        // E-Ride: the ways money is paid or received at the office, named plainly (Mapping settings keeps the
        // ticket-side names: counter cash there is the agents' tills)
        $out = [];
        foreach (array_keys(LedgerHealth::paymentMethods()) as $m) {
            $out[$m] = __('ledger_accounts.pay_'.$m);
        }

        return $out;
    }

    public static function methodLabel(?string $method): string
    {
        return $method ? (self::methods()[$method] ?? $method) : __('ledger_accounts.not_recorded');
    }

    /** An account as a select shows it: code and name. */
    public static function accountLabel($a): string
    {
        return $a ? $a->account_code.' - '.Preview::accountName($a) : '-';
    }

    /** The name of a person who recorded something (E-Ride users have first and last names). */
    public static function userName($u): string
    {
        if (! $u) {
            return '-';
        }

        return trim((string) ($u->full_name ?? '')) ?: trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: (string) ($u->email ?? '-');
    }

    /** The label of a kind of record in the mapping lists (ledger_accounts.doc_*, else ledger.doc_*). */
    public static function docLabel(string $kind): string
    {
        return \Lang::has('ledger_accounts.doc_'.$kind) ? __('ledger_accounts.doc_'.$kind) : __('ledger.doc_'.$kind);
    }
}
