<?php

namespace App\Ledger\ERide;

use App\Ledger\Models\JournalEntry;
use App\Ledger\Models\PaymentAccount;
use App\Ledger\Models\PaymentAccountTransaction;
use App\Ledger\Models\TillCount;
use App\Ledger\Models\TransactionMapping;
use App\Ledger\Services\PaymentBook;
use App\Ledger\Services\PostingRules;
use Illuminate\Support\Facades\DB;

/**
 * The bus money in the payment accounts' books (paxhitestbed: a fee paid into an
 * account is a movement in that account's book, beside its ledger entry).
 *
 * Ticket money, Fapshi's share, E-Ride's statement paid, and an agent's till
 * counted into the safe post by themselves (PostingRules, Tills). Each of their
 * entries that touches the ledger account of a payment account is written into
 * that account's book as one movement, linked to the record by PaymentBook::link,
 * so its Current balance is the money really there. The book follows the entry:
 * reposted, the movement changes; reversed, it goes.
 *
 * Counter cash in an agent's till (5712) has no payment account: it reaches the
 * safe's book when the till is counted.
 */
class BusBook
{
    /** The kind of each synced bus record, and the reference type its movement carries. */
    public const REFS = [
        PostingRules::MOVE => 'bus_ticket',
        PostingRules::STATEMENT_PAID => 'bus_statement',
        HeadOffice::MOVE => 'ho_move',
        HeadOffice::STATEMENT_PAID => 'ho_statement',
    ];

    public const TILL = 'till_count';

    /** Ledger account id => the payment account kept on it (the first active one, then the first). */
    private static array $byLedger = [];

    public static function forget(): void
    {
        self::$byLedger = [];
    }

    /**
     * After the sync posted, reposted or reversed the entry of a bus record: its
     * movement in the payment account book follows. $lines null = no entry any more.
     */
    public static function sync(string $kind, int $mapId, ?array $lines, ?string $date, ?string $title, ?string $reference = null): void
    {
        $ref = self::REFS[$kind] ?? null;
        if (! $ref) {
            return;
        }
        [$account, $net] = self::pick($lines ?? []);
        if (! $account || abs($net) < 0.005 || ! $date) {
            PaymentBook::link($ref, $mapId, null, 'credit', 0, (string) $date, '');

            return;
        }
        PaymentBook::link($ref, $mapId, $account, $net > 0 ? 'credit' : 'debit', abs($net), substr($date, 0, 10),
            (string) $title, null, null, $reference);
    }

    /** A till count posted: the counted cash into the book of the account it was put into. */
    public static function till(TillCount $count, int $toLedgerAccountId, string $date, string $title): void
    {
        $account = self::accountFor($toLedgerAccountId);
        PaymentBook::link(self::TILL, (int) $count->id, $count->counted > 0 ? $account : null, 'credit', (float) $count->counted, $date,
            $title, $count->note, 'cash', 'TILL-'.$count->id);
    }

    public static function tillVoided(TillCount $count): void
    {
        PaymentBook::remove(self::TILL, (int) $count->id);
    }

    /**
     * Money posted before its payment account existed (or before this book was kept):
     * every live bus entry and till count with no movement yet is written in now.
     *
     * @return int movements written
     */
    public static function backfill(): int
    {
        self::forget();
        $n = 0;
        PaymentBook::deferRecompute(function () use (&$n) {
            $have = PaymentAccountTransaction::whereIn('reference_type', array_merge(array_values(self::REFS), [self::TILL]))
                ->get(['reference_type', 'reference_id'])->map(fn ($t) => $t->reference_type.':'.$t->reference_id)->flip();
            TransactionMapping::whereIn('transaction_type', array_keys(self::REFS))->where('status', 'posted')->whereNotNull('journal_entry_id')
                ->orderBy('id')->chunkById(500, function ($maps) use ($have, &$n) {
                    $entries = JournalEntry::with('lines')->whereIn('id', $maps->pluck('journal_entry_id'))->get()->keyBy('id');
                    foreach ($maps as $m) {
                        if (isset($have[self::REFS[$m->transaction_type].':'.$m->id])) {
                            continue;
                        }
                        $e = $entries[$m->journal_entry_id] ?? null;
                        if (! $e || ! $e->is_posted || $e->is_reversed) {
                            continue;
                        }
                        $lines = $e->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => (float) $l->debit, 'credit' => (float) $l->credit])->all();
                        if (self::pick($lines)[0]) {
                            self::sync($m->transaction_type, (int) $m->id, $lines, $e->entry_date->toDateString(), $e->description, $e->reference_number);
                            $n++;
                        }
                    }
                });
            foreach (TillCount::where('status', 'posted')->where('counted', '>', 0)->get() as $c) {
                if (! isset($have[self::TILL.':'.$c->id]) && self::accountFor((int) $c->to_account_id)) {
                    self::till($c, (int) $c->to_account_id, $c->count_date->toDateString(), __('ledger_tills.entry_desc', ['agent' => Tills::names([$c->agent_id])[$c->agent_id] ?? '']));
                    $n++;
                }
            }
        });

        return $n;
    }

    /** Start the bus movements of every book again (a payment account changed its ledger account). */
    public static function rebuild(): int
    {
        return PaymentBook::deferRecompute(function () {
            $old = PaymentAccountTransaction::whereIn('reference_type', array_merge(array_values(self::REFS), [self::TILL]))->get();
            foreach ($old as $t) {
                $t->delete();
                PaymentBook::recompute((int) $t->payment_account_id);
            }

            return self::backfill();
        });
    }

    /** The payment account an entry's money moved on, and how much came in (+) or went out (-). */
    private static function pick(array $lines): array
    {
        $net = [];
        foreach ($lines as $l) {
            $acc = (int) $l['account_id'];
            $net[$acc] = ($net[$acc] ?? 0) + (float) ($l['debit'] ?? 0) - (float) ($l['credit'] ?? 0);
        }
        $best = [null, 0.0];
        foreach ($net as $acc => $amount) {
            $pa = self::accountFor($acc);
            if ($pa && abs($amount) > abs($best[1])) {
                $best = [$pa, round($amount, 2)];
            }
        }

        return $best;
    }

    private static function accountFor(int $ledgerAccountId): ?int
    {
        if (! array_key_exists($ledgerAccountId, self::$byLedger)) {
            self::$byLedger[$ledgerAccountId] = PaymentAccount::where('chart_of_account_id', $ledgerAccountId)
                ->orderByDesc('status')->orderBy('id')->value('id');
        }

        return self::$byLedger[$ledgerAccountId] ? (int) self::$byLedger[$ledgerAccountId] : null;
    }
}
