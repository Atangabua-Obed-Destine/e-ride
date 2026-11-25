<?php

namespace Modules\BusManagement\Service\Accounting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\BusManagement\Entities\BusAccAsset;
use Modules\BusManagement\Entities\BusAccBook;
use Modules\BusManagement\Entities\BusAccDocument;
use Modules\BusManagement\Entities\BusAccEntry;
use Modules\BusManagement\Entities\BusAccLine;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusCompany;
use Modules\BusManagement\Entities\BusCompanyStatement;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusMoneyMove;
use Modules\BusManagement\Entities\BusStatement;
use Modules\BusManagement\Lib\AccChart;
use Throwable;

/**
 * Brings a branch's books up to date with what happened in the bus business. Idempotent: every
 * event has one source key, so running it twice posts nothing twice. Order matters and is fixed:
 *  1. money moves (tickets paid, refunds), in the order they happened;
 *  2. trips that left: the ticket money held for them (4771) becomes sales (7061 / 7062);
 *  3. E-Ride statements (commission owed) and their payment;
 *  4. journal entries due to reverse themselves (accruals).
 *
 * A ticket paid is not income yet: Dr money / Cr 4771 "Tickets paid for trips not gone yet".
 * When the bus leaves (or its trip time has passed), what is still held for that trip becomes
 * sales, per channel, tagged with the trip, the bus and the route. A refund before the trip comes
 * off 4771; after it, it is a "refund given after the trip" (7091). Owed refunds wait on 4191.
 */
class LedgerSync
{
    public function __construct(protected Ledger $ledger)
    {
    }

    /** @return array counts of what was posted */
    public function sync(BusAgency|BusCompany|string $agency, int $limit = 1000): array
    {
        $agencyId = is_string($agency) ? $agency : (string)$agency->id;
        $books = $this->ledger->books($agencyId);
        if (!$books) {
            return [];
        }
        if ($books->isCompany()) {
            // The branches first: the head office mirrors what they recorded.
            foreach (BusAgency::where('company_id', $agencyId)->pluck('id') as $branchId) {
                $this->sync((string)$branchId, $limit);
            }
        }
        $lock = Cache::lock('bus-acc-sync:' . $agencyId, 120);
        if (!$lock->get()) {
            return ['busy' => 1];
        }
        try {
            if ($books->isCompany()) {
                $counts = ['mirrored' => $this->mirrorBranches($books)];
                $counts['fees'] = $this->postFees($books, 'company');
            } else {
                $counts = ['moves' => $this->postMoves($books, $limit), 'trips' => $this->recognize($books, $limit)];
                $counts['fees'] = $this->postFees($books, 'fapshi');
                $counts['statements'] = $this->statements($books);
            }
            $counts['wear'] = $this->depreciate($books);
            $counts['reversed'] = $this->autoReversals($books);
            $books->update(['synced_at' => now()]);
            return $counts;
        } finally {
            $lock->release();
        }
    }

    /**
     * A head office's books follow its branches' records: app and web payments collected on the
     * company account (Dr company Fapshi / Cr the branch's account), branch statements paid by the
     * head office (Dr the branch's account / Cr E-Ride), and the company statement paid (Dr E-Ride /
     * Cr the paying account). The branch books post their side of the same events, so the head
     * office's account of each branch always equals that branch's 1811.
     */
    protected function mirrorBranches(BusAccBook $books): int
    {
        $n = 0;
        $company = BusCompany::find($books->agency_id);
        if (!$company) {
            return 0;
        }
        $branches = BusAgency::where('company_id', $company->id)->get()->keyBy(fn($b) => (string)$b->id);
        $start = $books->starts_on->copy()->startOfDay();
        $moves = BusMoneyMove::whereIn('agency_id', $branches->keys())->where('method', 'company')->whereIn('kind', [BusMoneyMove::SALE, BusMoneyMove::EXTRA])
            ->where('at', '>=', $start)
            ->whereNotExists(fn($q) => $q->from('bus_acc_entries')->where('bus_acc_entries.agency_id', $books->agency_id)
                ->whereRaw("bus_acc_entries.source_key = concat('mm:', bus_money_moves.id)"))
            ->orderBy('id')->limit(1000)->get();
        foreach ($moves as $m) {
            try {
                $this->ledger->post($books->agency_id, [
                    'date' => $m->at, 'journal' => 'cash', 'auto' => true,
                    'memo' => translate('App and web tickets paid to the company account') . ' · ' . $branches[(string)$m->agency_id]->branchLabel(),
                    'lines' => [[$this->ledger->account($books->agency_id, 'fapshi'), $m->amount, 0],
                        [$this->ledger->branchAccount($company, $branches[(string)$m->agency_id]), 0, $m->amount]],
                    'source_type' => 'move', 'source_id' => (string)$m->id, 'source_key' => 'mm:' . $m->id,
                ]);
                $n++;
            } catch (Throwable $e) {
                Log::warning('Bus accounting: a company payment could not be mirrored', ['move' => $m->id, 'error' => $e->getMessage()]);
                break;
            }
        }
        $statements = BusStatement::whereIn('agency_id', $branches->keys())->whereNotNull('company_statement_id')->where('status', BusStatement::PAID)
            ->where('paid_at', '>=', $start)->where('total', '>', 0)->get();
        foreach ($statements as $s) {
            if (BusAccEntry::where('agency_id', $books->agency_id)->where('source_key', 'ms:' . $s->id)->exists()) {
                continue;
            }
            try {
                $this->ledger->post($books->agency_id, [
                    'date' => $s->paid_at, 'journal' => 'cash', 'auto' => true,
                    'memo' => translate('E-Ride commission of the branch, paid by the head office') . ' · ' . $branches[(string)$s->agency_id]->branchLabel() . ' · ' . $s->month->translatedFormat('F Y'),
                    'lines' => [[$this->ledger->branchAccount($company, $branches[(string)$s->agency_id]), $s->total, 0], [$this->ledger->account($books->agency_id, 'eride'), 0, $s->total]],
                    'source_type' => 'statement', 'source_id' => (string)$s->id, 'source_key' => 'ms:' . $s->id,
                ]);
                $n++;
            } catch (Throwable $e) {
                Log::warning('Bus accounting: a branch statement could not be mirrored', ['statement' => $s->id, 'error' => $e->getMessage()]);
            }
        }
        foreach (BusCompanyStatement::where('company_id', $company->id)->where('status', 'paid')->where('paid_at', '>=', $start)->where('total', '>', 0)->get() as $cs) {
            if (BusAccEntry::where('agency_id', $books->agency_id)->where('source_key', 'cs:' . $cs->id)->exists()) {
                continue;
            }
            try {
                $this->ledger->post($books->agency_id, [
                    'date' => $cs->paid_at, 'journal' => 'cash', 'auto' => true,
                    'memo' => translate('E-Ride company statement paid') . ' · ' . $cs->month->translatedFormat('F Y'),
                    'lines' => [[$this->ledger->account($books->agency_id, 'eride'), $cs->total, 0], [$this->ledger->account($books->agency_id, $books->setting('eride_from', 'bank')), 0, $cs->total]],
                    'source_type' => 'statement_paid', 'source_id' => (string)$cs->id, 'source_key' => 'cs:' . $cs->id,
                ]);
                $n++;
            } catch (Throwable $e) {
                Log::warning('Bus accounting: a company statement could not post', ['statement' => $cs->id, 'error' => $e->getMessage()]);
            }
        }
        return $n;
    }

    /**
     * Fapshi keeps its fee on each app or web payment and puts the rest on the balance (amount 12000,
     * revenue 11640). The ticket stays at its full price; the fee is a charge (Dr 6311 / Cr Fapshi),
     * posted as its own entry right after the payment, so the Fapshi account agrees with Fapshi's
     * statement. On a branch's books for its own Fapshi account; on the head office's books for the
     * company account (method "company").
     */
    protected function postFees(BusAccBook $books, string $method): int
    {
        $n = 0;
        $q = BusMoneyMove::where('method', $method)->whereIn('kind', [BusMoneyMove::SALE, BusMoneyMove::EXTRA])->whereNull('skipped');
        if ($method === 'company') {
            $q->whereIn('agency_id', BusAgency::where('company_id', $books->agency_id)->pluck('id'))->where('at', '>=', $books->starts_on->copy()->startOfDay());
        } else {
            $q->where('agency_id', $books->agency_id)->whereNotNull('entry_id');
        }
        $prefix = $method === 'company' ? 'cfee:' : 'fee:';
        $moves = $q->whereNotExists(fn($w) => $w->from('bus_acc_entries')->where('bus_acc_entries.agency_id', $books->agency_id)
            ->whereRaw("bus_acc_entries.source_key = concat(?, bus_money_moves.id)", [$prefix]))->orderBy('id')->limit(1000)->get();
        foreach ($moves as $m) {
            $fee = $this->feeFor($m);
            if ($fee === null) {
                continue;
            }
            try {
                $code = DB::table('bus_bookings')->where('id', $m->booking_id)->value('code');
                $this->ledger->post($books->agency_id, [
                    'date' => $m->at, 'journal' => 'cash', 'auto' => true,
                    'memo' => translate('Fapshi fee kept on the payment') . ($code ? ' · ' . $code : ''),
                    'lines' => [[$this->ledger->account($books->agency_id, 'charges'), $fee, 0, 'channel' => $m->channel],
                        [$this->ledger->account($books->agency_id, 'fapshi'), 0, $fee]],
                    'source_type' => 'fee', 'source_id' => (string)$m->id, 'source_key' => $prefix . $m->id,
                ]);
                $n++;
            } catch (Throwable $e) {
                Log::warning('Bus accounting: a Fapshi fee could not post', ['move' => $m->id, 'error' => $e->getMessage()]);
                break;
            }
        }
        return $n;
    }

    /**
     * The fee Fapshi kept on the payment behind a money move: the booking's successful payments in
     * order, matched to its online money-in moves in order. Null when not known yet (no revenue
     * figure), 0 when nothing was kept.
     */
    public function feeFor(BusMoneyMove $m): ?float
    {
        $payments = \Modules\BusManagement\Entities\BusPayment::where('booking_id', $m->booking_id)->where('status', \Modules\BusManagement\Entities\BusPayment::SUCCESSFUL)
            ->orderBy('id')->get();
        $moves = BusMoneyMove::where('booking_id', $m->booking_id)->whereIn('kind', [BusMoneyMove::SALE, BusMoneyMove::EXTRA])
            ->whereIn('method', ['fapshi', 'company'])->orderBy('id')->pluck('id')->map(fn($id) => (int)$id)->values()->all();
        $i = array_search((int)$m->id, $moves, true);
        $p = $i === false ? null : ($payments[$i] ?? null);
        if (!$p) {
            return null;
        }
        $revenue = $p->raw_response['payment-status']['revenue'] ?? null;
        $amount = $p->raw_response['payment-status']['amount'] ?? $p->amount;
        if (!is_numeric($revenue) || !is_numeric($amount)) {
            return null;
        }
        $fee = round((float)$amount - (float)$revenue, 2);
        return $fee > 0 && $fee < (float)$amount ? $fee : 0.0;
    }

    /**
     * Each month's wear of the buses carried in these books (straight-line), for every month ended
     * up to $upTo (the end of last month by default). A head office's bus is charged to the branch it
     * belongs to when that branch keeps books: the head office's account of the branch goes down
     * (Dr) against the wear (Cr 2845), and the branch carries the cost (Dr 6813, tagged with the bus)
     * against 1811. Otherwise the cost stays where the bus is.
     */
    public function depreciate(BusAccBook $books, ?Carbon $upTo = null): int
    {
        $n = 0;
        $upTo = ($upTo ?? today()->startOfMonth()->subDay())->copy()->endOfMonth();
        if ($upTo->gte(today()->startOfDay())) {
            $upTo = today()->startOfMonth()->subDay();
        }
        foreach (BusAccAsset::with('vehicle')->where('agency_id', $books->agency_id)->where('status', 'active')->get() as $asset) {
            $month = $asset->starts_on->copy()->startOfMonth();
            $last = $asset->lastMonth();
            $target = round($asset->value - $asset->end_value, 2);
            while ($month->lte($last) && $month->copy()->endOfMonth()->lte($upTo)) {
                $key = 'wear:' . $asset->id . ':' . $month->format('Y-m');
                if (!BusAccEntry::where('agency_id', $books->agency_id)->where('source_key', $key)->exists()) {
                    $done = round(DB::table('bus_acc_entries')->where('agency_id', $books->agency_id)->where('source_type', 'wear')->where('source_id', (string)$asset->id)
                        ->whereNull('reverses_id')->sum('total'), 2);
                    $amount = $month->eq($last) ? round($target - $done, 2) : min($asset->monthly(), round($target - $done, 2));
                    if ($amount >= 0.005) {
                        try {
                            DB::transaction(fn() => $this->postWear($books, $asset, $month, $amount, $key));
                            $n++;
                        } catch (Throwable $e) {
                            Log::warning('Bus accounting: bus wear could not post', ['asset' => $asset->id, 'month' => $month->format('Y-m'), 'error' => $e->getMessage()]);
                            break;
                        }
                    }
                }
                $month->addMonth();
            }
        }
        return $n;
    }

    protected function postWear(BusAccBook $books, BusAccAsset $asset, Carbon $month, float $amount, string $key): void
    {
        $tag = ['vehicle_id' => (string)$asset->vehicle_id];
        $date = $month->copy()->endOfMonth()->startOfDay();
        $memo = translate('Bus wear of the month') . ' · ' . $asset->name . ' · ' . $month->translatedFormat('F Y');
        $common = ['date' => $date, 'journal' => 'general', 'auto' => true, 'memo' => $memo, 'source_type' => 'wear', 'source_id' => (string)$asset->id, 'source_key' => $key];
        $home = $books->isCompany() && $asset->vehicle ? BusAgency::find($asset->vehicle->agency_id) : null;
        $homeBooks = $home && (string)$home->company_id === (string)$books->agency_id ? $this->ledger->books($home) : null;
        if ($homeBooks && $homeBooks->starts_on->lte($date)) {
            $this->ledger->post($books->agency_id, $common + ['lines' => [
                [$this->ledger->branchAccount((string)$books->agency_id, $home), $amount, 0, 'memo' => translate('Charged to the branch')] + $tag,
                [$this->ledger->account($books->agency_id, 'bus_wear'), 0, $amount] + $tag,
            ]]);
            $this->ledger->post($home, ['source_type' => 'wear_charge'] + $common + ['lines' => [
                [$this->ledger->account($home, 'depreciation'), $amount, 0] + $tag,
                [$this->ledger->account($home, 'head_office'), 0, $amount, 'memo' => translate('Charged by the head office')],
            ]]);
            return;
        }
        $this->ledger->post($books->agency_id, $common + ['lines' => [
            [$this->ledger->account($books->agency_id, 'depreciation'), $amount, 0] + $tag,
            [$this->ledger->account($books->agency_id, 'bus_wear'), 0, $amount] + $tag,
        ]]);
    }

    /** Moves not posted yet (or that could not post, like a closed month). */
    public function pending(string $agencyId): int
    {
        return BusMoneyMove::where('agency_id', $agencyId)->whereNull('entry_id')->whereNull('skipped')->count();
    }

    protected function postMoves(BusAccBook $books, int $limit): int
    {
        $posted = 0;
        $start = $books->starts_on->copy()->startOfDay();
        $moves = BusMoneyMove::where('agency_id', $books->agency_id)->whereNull('entry_id')->whereNull('skipped')->orderBy('id')->limit($limit)->get();
        foreach ($moves as $m) {
            if ($m->at->lt($start)) {
                // Before the books: the opening balances carry it.
                $m->update(['skipped' => 'before_start']);
                continue;
            }
            try {
                DB::transaction(function () use ($books, $m) {
                    $entry = $this->postMove($books, $m);
                    $m->update(['entry_id' => (string)$entry->id]);
                });
                $posted++;
            } catch (Throwable $e) {
                // A closed month, a missing account: it stays pending and is shown on the overview.
                Log::warning('Bus accounting: a money move could not post', ['move' => $m->id, 'error' => $e->getMessage()]);
                break;
            }
        }
        return $posted;
    }

    /** The entry for one money move (see the class comment). */
    public function postMove(BusAccBook $books, BusMoneyMove $m): BusAccEntry
    {
        $a = fn(string $role) => $this->ledger->account($books->agency_id, $role);
        $dep = $m->departure_id ? BusDeparture::find($m->departure_id) : null;
        $tags = $this->tripTags($dep, $m->channel);
        $money = function (?string $method) use ($a, $books, $m) {
            $role = $method ? (AccChart::METHOD_ROLE[$method] ?? 'mtn') : $books->setting('refunds_from', 'mtn');
            return [$a($role), $role === 'counter_cash' ? $m->staff_user_id : null];
        };
        // Held for the trip (4771) until it leaves; after that, straight to sales or refunds after the trip.
        $held = fn(?BusDeparture $d) => $this->recognized($books, $d) ? null : $a('unearned');
        $sales = $a($m->channel === 'counter' ? 'sales_counter' : 'sales_online');
        $code = DB::table('bus_bookings')->where('id', $m->booking_id)->value('code');

        $lines = match ($m->kind) {
            BusMoneyMove::SALE => (function () use ($money, $m, $held, $dep, $sales, $tags) {
                [$acc, $staff] = $money($m->method);
                return [[$acc, $m->amount, 0, 'staff_user_id' => $staff, 'channel' => $m->channel] + $tags, [$held($dep) ?? $sales, 0, $m->amount] + $tags];
            })(),
            BusMoneyMove::EXTRA => (function () use ($money, $m, $a) {
                [$acc, $staff] = $money($m->method);
                return [[$acc, $m->amount, 0, 'staff_user_id' => $staff], [$a('refunds_owed'), 0, $m->amount]];
            })(),
            BusMoneyMove::REFUND_OWED => [[$held($dep) ?? $a('refunds_given'), $m->amount, 0] + $tags, [$a('refunds_owed'), 0, $m->amount]],
            BusMoneyMove::CASH_REFUND => (function () use ($money, $m, $held, $dep, $a, $tags) {
                [$acc, $staff] = $money($m->method);
                return [[$held($dep) ?? $a('refunds_given'), $m->amount, 0] + $tags, [$acc, 0, $m->amount, 'staff_user_id' => $staff]];
            })(),
            BusMoneyMove::REFUND_SENT => (function () use ($money, $m, $a) {
                [$acc] = $money(null);
                return [[$a('refunds_owed'), $m->amount, 0], [$acc, 0, $m->amount]];
            })(),
            BusMoneyMove::REBOOK => (function () use ($m, $held, $dep, $a, $tags, $sales) {
                $from = BusDeparture::find($m->from_departure_id);
                return [[$held($from) ?? $a('refunds_given'), $m->amount, 0] + $this->tripTags($from, $m->channel), [$held($dep) ?? $sales, 0, $m->amount] + $tags];
            })(),
        };
        $memo = translate(match ($m->kind) {
            BusMoneyMove::SALE => 'Tickets paid',
            BusMoneyMove::EXTRA => 'Money paid beyond the fare, to send back',
            BusMoneyMove::REFUND_OWED => 'Refund owed to the passenger',
            BusMoneyMove::CASH_REFUND => 'Refund given at the counter',
            BusMoneyMove::REFUND_SENT => 'Refund sent to the passenger',
            default => 'Tickets moved to another trip',
        }) . ($code ? ' · ' . $code : '');

        return $this->ledger->post($books->agency_id, [
            'date' => $m->at, 'journal' => in_array($m->kind, [BusMoneyMove::SALE, BusMoneyMove::REBOOK], true) ? 'sales' : 'cash',
            'memo' => $memo, 'lines' => $this->vatSplit($books, $lines), 'auto' => true,
            'source_type' => 'move', 'source_id' => (string)$m->id, 'source_key' => 'move:' . $m->id,
        ]);
    }

    /**
     * When the books carry VAT on tickets (Settings), a ticket sale or a refund after the trip is
     * split: the price before VAT on the sales account, the VAT on 4431 "VAT collected". The
     * ticket money held before the trip (4771) stays at the full price.
     */
    public function vatSplit(BusAccBook $books, array $lines): array
    {
        if (!$books->setting('vat_registered') || !$books->setting('vat_on_tickets')) {
            return $lines;
        }
        $rate = (float)$books->setting('vat_rate', 19.25);
        $out = [];
        $vatAccount = null;
        foreach ($lines as $l) {
            $acc = $l[0];
            if (!($acc instanceof \Modules\BusManagement\Entities\BusAccAccount) || !in_array($acc->role, ['sales_online', 'sales_counter', 'refunds_given'], true)) {
                $out[] = $l;
                continue;
            }
            $vatAccount ??= $this->ledger->account($books->agency_id, 'vat_out');
            $debit = round((float)($l[1] ?? 0), 2);
            $credit = round((float)($l[2] ?? 0), 2);
            $amount = max($debit, $credit);
            $net = round($amount / (1 + $rate / 100), 2);
            $vat = round($amount - $net, 2);
            $l[1] = $debit > 0 ? $net : 0;
            $l[2] = $credit > 0 ? $net : 0;
            $out[] = $l;
            if ($vat > 0) {
                $out[] = [$vatAccount, $debit > 0 ? $vat : 0, $credit > 0 ? $vat : 0, 'departure_id' => $l['departure_id'] ?? null, 'channel' => $l['channel'] ?? null];
            }
        }
        return $out;
    }

    /** Trip tags for a line: the trip, its bus, its route, the sales channel. */
    public function tripTags(?BusDeparture $d, ?string $channel = null): array
    {
        return [
            'departure_id' => $d ? (string)$d->id : null,
            'vehicle_id' => $d && $d->vehicle_id ? (string)$d->vehicle_id : null,
            'destination_id' => $d && $d->destination_id ? (string)$d->destination_id : null,
            'channel' => $channel,
        ];
    }

    /** A trip's ticket money is income once it is recognized (or when the trip left before the books started). */
    public function recognized(BusAccBook $books, ?BusDeparture $d): bool
    {
        if (!$d) {
            return true;
        }
        if ($d->leavesAt()->lt($books->starts_on->copy()->startOfDay())) {
            return true;
        }
        return DB::table('bus_acc_recognized')->where('departure_id', $d->id)->exists();
    }

    /** Trips that left (or whose time passed, or that were cancelled and are over): what is held for them becomes sales. */
    protected function recognize(BusAccBook $books, int $limit): int
    {
        $done = 0;
        $unearned = $this->ledger->account($books->agency_id, 'unearned');
        $this->unrecognizeReopened($books);
        $departures = BusDeparture::where('agency_id', $books->agency_id)
            ->whereIn('status', [BusDeparture::DEPARTED, BusDeparture::CANCELLED])
            ->where('date', '>=', $books->starts_on->copy()->subDay()->toDateString())
            ->whereNotExists(fn($q) => $q->from('bus_acc_recognized')->whereColumn('bus_acc_recognized.departure_id', 'bus_departures.id'))
            ->orderBy('date')->orderBy('time')->limit($limit)->get();
        foreach ($departures as $d) {
            if ($d->status === BusDeparture::CANCELLED && $d->leavesAt()->gt(now())) {
                // A cancelled trip still ahead: its passengers may still be moved or refunded.
                continue;
            }
            if ($d->leavesAt()->lt($books->starts_on->copy()->startOfDay())) {
                DB::table('bus_acc_recognized')->insertOrIgnore(['agency_id' => $books->agency_id, 'departure_id' => (string)$d->id, 'entry_id' => null, 'created_at' => now()]);
                continue;
            }
            try {
                DB::transaction(function () use ($books, $d, $unearned) {
                    // Any money move of this trip not posted yet goes first (it may still be held for it).
                    if (BusMoneyMove::where('agency_id', $books->agency_id)->whereNull('entry_id')->whereNull('skipped')
                        ->where(fn($q) => $q->where('departure_id', $d->id)->orWhere('from_departure_id', $d->id))->exists()) {
                        throw new \RuntimeException('moves pending');
                    }
                    $held = BusAccLine::where('agency_id', $books->agency_id)->where('account_id', $unearned->id)->where('departure_id', $d->id)
                        ->groupBy('channel')->selectRaw('channel, sum(credit) - sum(debit) as held')->pluck('held', 'channel');
                    $lines = [];
                    $tags = $this->tripTags($d);
                    foreach ($held as $channel => $amount) {
                        $amount = round((float)$amount, 2);
                        if (abs($amount) < 0.005) {
                            continue;
                        }
                        $sales = $this->ledger->account($books->agency_id, $channel === 'counter' ? 'sales_counter' : 'sales_online');
                        $lines[] = $amount > 0 ? [$unearned, $amount, 0, 'channel' => $channel] + $tags : [$unearned, 0, -$amount, 'channel' => $channel] + $tags;
                        $lines[] = $amount > 0 ? [$sales, 0, $amount, 'channel' => $channel] + $tags : [$sales, -$amount, 0, 'channel' => $channel] + $tags;
                    }
                    $entry = null;
                    if ($lines) {
                        $when = $d->departed_at ?? $d->leavesAt();
                        $entry = $this->ledger->post($books->agency_id, [
                            'date' => $when->gt(now()) ? now() : $when, 'journal' => 'sales', 'auto' => true,
                            'memo' => translate('Trip left: its tickets become sales') . ' · ' . $d->leavesAt()->format('d/m H:i') . ' ' . ($d->destination?->city ?? ''),
                            'lines' => $this->vatSplit($books, $lines), 'source_type' => 'departure', 'source_id' => (string)$d->id,
                            // A trip recognized again after running again gets its own key.
                            'source_key' => 'dep:' . $d->id . (($n = BusAccEntry::where('agency_id', $books->agency_id)->where('source_type', 'departure')->where('source_id', (string)$d->id)->whereNull('reverses_id')->count()) ? ':' . $n : ''),
                        ]);
                    }
                    DB::table('bus_acc_recognized')->insert(['agency_id' => $books->agency_id, 'departure_id' => (string)$d->id,
                        'entry_id' => $entry ? (string)$entry->id : null, 'created_at' => now()]);
                });
                $done++;
            } catch (Throwable $e) {
                if ($e->getMessage() !== 'moves pending') {
                    Log::warning('Bus accounting: a trip could not be recognized', ['departure' => $d->id, 'error' => $e->getMessage()]);
                }
            }
        }
        return $done;
    }

    /**
     * A trip counted as gone that runs again (a cancelled trip brought back when a lockdown is
     * lifted, or a departure undone): its sales go back to "paid for trips not gone yet" by
     * mirroring the recognition entry, and it is recognized again when it really leaves.
     */
    protected function unrecognizeReopened(BusAccBook $books): void
    {
        $rows = DB::table('bus_acc_recognized as r')->join('bus_departures as d', 'd.id', '=', 'r.departure_id')
            ->where('r.agency_id', $books->agency_id)->whereIn('d.status', BusDeparture::OPEN)->select('r.id', 'r.entry_id', 'r.departure_id')->get();
        foreach ($rows as $row) {
            try {
                DB::transaction(function () use ($row) {
                    if ($row->entry_id && ($entry = BusAccEntry::find($row->entry_id)) && !$entry->reversed_by_id) {
                        $this->ledger->reverse($entry, translate('Trip runs again: its tickets are held for it again'), null, null, 'unrec:' . $entry->id);
                    }
                    DB::table('bus_acc_recognized')->where('id', $row->id)->delete();
                });
            } catch (Throwable $e) {
                Log::warning('Bus accounting: a reopened trip could not be put back', ['departure' => $row->departure_id, 'error' => $e->getMessage()]);
            }
        }
    }

    /** E-Ride's monthly commission: owed when the statement is made, settled when it is paid. */
    protected function statements(BusAccBook $books): int
    {
        $n = 0;
        $startMonth = $books->starts_on->copy()->startOfMonth();
        $statements = BusStatement::where('agency_id', $books->agency_id)->where('total', '>', 0)
            ->where(fn($q) => $q->where('month', '>=', $startMonth->toDateString())->orWhere('paid_at', '>=', $books->starts_on->toDateString()))->get();
        foreach ($statements as $s) {
            try {
                if ($s->month->gte($startMonth) && !BusAccEntry::where('agency_id', $books->agency_id)->where('source_key', 'stmt:' . $s->id)->exists()) {
                    $end = $s->month->copy()->endOfMonth();
                    $this->ledger->post($books->agency_id, [
                        'date' => $end->gt(today()) ? today() : $end, 'journal' => 'purchase', 'auto' => true,
                        'memo' => translate('E-Ride commission') . ' · ' . $s->month->translatedFormat('F Y'),
                        'lines' => [[$this->ledger->account($books->agency_id, 'eride_commission'), $s->total, 0], [$this->ledger->account($books->agency_id, 'eride'), 0, $s->total]],
                        'source_type' => 'statement', 'source_id' => (string)$s->id, 'source_key' => 'stmt:' . $s->id,
                    ]);
                    $n++;
                }
                if ($s->status === BusStatement::PAID && $s->paid_at && $s->paid_at->gte($books->starts_on)
                    && !BusAccEntry::where('agency_id', $books->agency_id)->where('source_key', 'stmt_paid:' . $s->id)->exists()) {
                    $from = $s->company_statement_id ? 'head_office' : $books->setting('eride_from', 'mtn');
                    $this->ledger->post($books->agency_id, [
                        'date' => $s->paid_at, 'journal' => 'cash', 'auto' => true,
                        'memo' => translate('E-Ride commission paid') . ' · ' . $s->month->translatedFormat('F Y') . ($s->company_statement_id ? ' · ' . translate('by the head office') : ''),
                        'lines' => [[$this->ledger->account($books->agency_id, 'eride'), $s->total, 0], [$this->ledger->account($books->agency_id, $from), 0, $s->total]],
                        'source_type' => 'statement_paid', 'source_id' => (string)$s->id, 'source_key' => 'stmt_paid:' . $s->id,
                    ]);
                    $n++;
                }
            } catch (Throwable $e) {
                Log::warning('Bus accounting: a statement could not post', ['statement' => $s->id, 'error' => $e->getMessage()]);
            }
        }
        return $n;
    }

    /** Journal entries set to reverse on a date (accruals): reversed on that day. */
    protected function autoReversals(BusAccBook $books): int
    {
        $n = 0;
        $docs = BusAccDocument::where('agency_id', $books->agency_id)->where('type', BusAccDocument::JOURNAL)->where('status', 'posted')
            ->whereNotNull('reverse_on')->where('reverse_on', '<=', today()->toDateString())->whereNull('auto_reverse_entry_id')->get();
        foreach ($docs as $doc) {
            try {
                DB::transaction(function () use ($doc) {
                    $entry = BusAccEntry::find($doc->entry_id);
                    $date = $this->ledger->monthClosed($doc->agency_id, $doc->reverse_on) ? today()->toDateString() : $doc->reverse_on->toDateString();
                    $rev = $this->ledger->reverse($entry, translate('Reversed as planned') . ' · ' . $doc->number, null, $date, 'autorev:' . $doc->id);
                    $doc->update(['auto_reverse_entry_id' => (string)$rev->id]);
                });
                $n++;
            } catch (Throwable $e) {
                Log::warning('Bus accounting: an entry could not reverse on its date', ['document' => $doc->id, 'error' => $e->getMessage()]);
            }
        }
        return $n;
    }

    /** Branches with books, for the minute tick. */
    public function syncAll(): int
    {
        $n = 0;
        foreach (BusAccBook::pluck('agency_id') as $id) {
            // One branch's problem never stops the others (or the rest of the bus tick).
            try {
                $n += array_sum(array_filter($this->sync((string)$id, 300), 'is_int'));
            } catch (Throwable $e) {
                Log::error('Bus accounting: sync failed', ['agency' => (string)$id, 'error' => $e->getMessage()]);
            }
        }
        return $n;
    }
}
