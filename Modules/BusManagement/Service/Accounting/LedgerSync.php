<?php

namespace Modules\BusManagement\Service\Accounting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\BusManagement\Entities\BusAccBook;
use Modules\BusManagement\Entities\BusAccDocument;
use Modules\BusManagement\Entities\BusAccEntry;
use Modules\BusManagement\Entities\BusAccLine;
use Modules\BusManagement\Entities\BusAgency;
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
    public function sync(BusAgency|string $agency, int $limit = 1000): array
    {
        $agencyId = $agency instanceof BusAgency ? (string)$agency->id : (string)$agency;
        $books = $this->ledger->books($agencyId);
        if (!$books) {
            return [];
        }
        $lock = Cache::lock('bus-acc-sync:' . $agencyId, 120);
        if (!$lock->get()) {
            return ['busy' => 1];
        }
        try {
            $counts = ['moves' => $this->postMoves($books, $limit), 'trips' => $this->recognize($books, $limit)];
            $counts['statements'] = $this->statements($books);
            $counts['reversed'] = $this->autoReversals($books);
            $books->update(['synced_at' => now()]);
            return $counts;
        } finally {
            $lock->release();
        }
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
            'memo' => $memo, 'lines' => $lines, 'auto' => true,
            'source_type' => 'move', 'source_id' => (string)$m->id, 'source_key' => 'move:' . $m->id,
        ]);
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
                            'lines' => $lines, 'source_type' => 'departure', 'source_id' => (string)$d->id, 'source_key' => 'dep:' . $d->id,
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
