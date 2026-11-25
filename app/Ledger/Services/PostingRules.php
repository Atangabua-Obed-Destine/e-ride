<?php

namespace App\Ledger\Services;

use App\Ledger\Models\AccountingSetting;
use App\Ledger\Models\ChartOfAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusMoneyMove;
use Modules\BusManagement\Entities\BusPayment;
use Modules\BusManagement\Entities\BusStatement;

/**
 * What a bus record puts in the ledger. E-Ride's version of posinnova's
 * PostingRules (which does the same for POS sales and purchases): each rule
 * returns the entry a record should have right now, or null when it has none,
 * and LedgerSync compares that with what is posted and corrects the difference
 * (paxhitestbed's remap). The accounts come from Mapping settings and Cash
 * accounts, never from code.
 *
 * Ticket money is held for the trip until the bus leaves (paxhitestbed's advice:
 * 477 Produits constatés d'avance), then it is a sale:
 *
 *   ticket paid                      Dr money (method)      Cr 477 held   (the trip left already: Cr 706 sales)
 *   paid beyond the fare             Dr money               Cr 4198 refunds owed
 *   refund owed                      Dr 477 (or 709)        Cr 4198
 *   refund given at the counter      Dr 477 (or 709)        Cr money
 *   refund sent                      Dr 4198                Cr money the refunds go out of
 *   moved to another trip            Dr 477 old trip (709)  Cr 477 new trip (706)       no money moves
 *   app payment, Fapshi's share      Dr 631 fees            Cr 5523 Fapshi  (in the same entry)
 *   the trip left                    Dr 477                 Cr 706, per channel, what was held for it
 *   E-Ride's monthly statement       Dr 632 commission      Cr 401 E-Ride
 *   the statement paid               Dr 401 E-Ride          Cr money (or 1811 when the head office paid it)
 *
 * "Held or sale" is decided by time, not by the order things are synced: a
 * payment, refund or move made before the bus left touches 477; one made after
 * it left goes straight to sales (706) or to refunds after the trip (709). The
 * trip's own entry moves to 706 exactly what was held for it. So the same bus
 * records always give the same entries, whatever order they are posted in.
 *
 * Every line carries the bus cost centres: trip, bus, route, channel, and the
 * counter agent on a till line.
 */
class PostingRules
{
    // The kinds of bus records that post (transaction_mappings.transaction_type).
    const MOVE = 'bus_move';
    const DEPARTURE = 'bus_departure';
    const STATEMENT = 'bus_statement';
    const STATEMENT_PAID = 'bus_statement_paid';
    const TYPES = [self::MOVE, self::DEPARTURE, self::STATEMENT, self::STATEMENT_PAID];

    private $codes;

    private $mapped = [];

    private $departures = [];

    public function __construct()
    {
        $this->codes = ChartOfAccount::pluck('id', 'account_code')->all();
    }

    // ---------------------------------------------------------------- money moves

    /** @return array|null ['date', 'description', 'reference', 'journal_type', 'lines' => [...]] */
    public function forMove(BusMoneyMove $m): ?array
    {
        $amount = round((float) $m->amount, 2);
        if ($amount <= 0 || $m->skipped) {
            return null;
        }
        $at = Carbon::parse($m->at);
        $d = $this->departure($m->departure_id);
        $tags = $this->tags($d, $m->channel);
        $code = DB::table('bus_bookings')->where('id', $m->booking_id)->value('code');
        // money in or out: the method's account; counter cash in the agent's till
        $money = function (?string $method) use ($m) {
            $method = $method ?: (string) (AccountingSetting::getValue('refunds_paid_from') ?: 'momo');

            return ['account_id' => $this->mapping('payment_method', $method), 'agent_id' => $method === 'cash' ? $m->staff_user_id : null];
        };
        // held for the trip, or (the trip had left) sales / refunds after the trip
        $ticketSide = fn (?BusDeparture $trip, bool $refund) => $this->heldAt($trip, $at)
            ? $this->mapping('tickets_held') : ($refund ? $this->mapping('refunds_after_trip') : $this->mapping('ticket_sales'));
        $L = fn (string $k) => __('ledger.l_bus_'.$k);

        switch ($m->kind) {
            case BusMoneyMove::SALE:
                $lines = [
                    $money($m->method) + ['debit' => $amount, 'credit' => 0, 'description' => $L('ticket_paid')] + $tags,
                    ['account_id' => $ticketSide($d, false), 'debit' => 0, 'credit' => $amount, 'description' => $L('ticket_paid')] + $tags,
                ];
                if (in_array($m->method, ['fapshi', 'company'], true) && ($fee = $this->fee($m))) {
                    // Fapshi pays the ticket less its share: the share is a charge, so the account reconciles to Fapshi's statement
                    $lines[] = ['account_id' => $this->mapping('gateway_fees'), 'debit' => $fee, 'credit' => 0, 'description' => $L('gateway_fee')] + $tags;
                    $lines[] = $money($m->method) + ['debit' => 0, 'credit' => $fee, 'description' => $L('gateway_fee')] + $tags;
                }
                $journal = 'sales';
                $what = 'ticket_paid';
                break;
            case BusMoneyMove::EXTRA:
                $lines = [
                    $money($m->method) + ['debit' => $amount, 'credit' => 0, 'description' => $L('extra')] + $tags,
                    ['account_id' => $this->mapping('refunds_owed'), 'debit' => 0, 'credit' => $amount, 'description' => $L('extra')] + $tags,
                ];
                $journal = 'cash';
                $what = 'extra';
                break;
            case BusMoneyMove::REFUND_OWED:
                $lines = [
                    ['account_id' => $ticketSide($d, true), 'debit' => $amount, 'credit' => 0, 'description' => $L('refund_owed')] + $tags,
                    ['account_id' => $this->mapping('refunds_owed'), 'debit' => 0, 'credit' => $amount, 'description' => $L('refund_owed')] + $tags,
                ];
                $journal = 'sales';
                $what = 'refund_owed';
                break;
            case BusMoneyMove::CASH_REFUND:
                $lines = [
                    ['account_id' => $ticketSide($d, true), 'debit' => $amount, 'credit' => 0, 'description' => $L('cash_refund')] + $tags,
                    $money($m->method) + ['debit' => 0, 'credit' => $amount, 'description' => $L('cash_refund')] + $tags,
                ];
                $journal = 'cash';
                $what = 'cash_refund';
                break;
            case BusMoneyMove::REFUND_SENT:
                $lines = [
                    ['account_id' => $this->mapping('refunds_owed'), 'debit' => $amount, 'credit' => 0, 'description' => $L('refund_sent')] + $tags,
                    $money(null) + ['debit' => 0, 'credit' => $amount, 'description' => $L('refund_sent')] + $tags,
                ];
                $journal = 'cash';
                $what = 'refund_sent';
                break;
            case BusMoneyMove::REBOOK:
                $from = $this->departure($m->from_departure_id);
                $lines = [
                    ['account_id' => $ticketSide($from, true), 'debit' => $amount, 'credit' => 0, 'description' => $L('rebook')] + $this->tags($from, $m->channel),
                    ['account_id' => $ticketSide($d, false), 'debit' => 0, 'credit' => $amount, 'description' => $L('rebook')] + $tags,
                ];
                $journal = 'sales';
                $what = 'rebook';
                break;
            default:
                return null;
        }

        return [
            'date' => $at->toDateString(),
            'description' => $L($what).($code ? ' · '.$code : ''),
            'reference' => $code,
            'journal_type' => $journal,
            'lines' => $lines,
        ];
    }

    // ---------------------------------------------------------------- the trip left

    /** A trip that left: what was held for it (477) becomes sales (706), per channel. Null while it has not left. */
    public function forDeparture(BusDeparture $d): ?array
    {
        $left = $this->leftAt($d);
        if (! $left) {
            return null;
        }
        $held = [];
        $moves = BusMoneyMove::where('agency_id', $d->agency_id)->whereNull('skipped')
            ->where(fn ($q) => $q->where('departure_id', $d->id)->orWhere('from_departure_id', $d->id))->get();
        foreach ($moves as $m) {
            if (Carbon::parse($m->at)->gte($left)) {
                continue; // after the trip left: it went straight to sales or refunds after the trip
            }
            $sign = match ($m->kind) {
                BusMoneyMove::SALE => 1,
                BusMoneyMove::REFUND_OWED, BusMoneyMove::CASH_REFUND => -1,
                BusMoneyMove::REBOOK => (string) $m->departure_id === (string) $m->from_departure_id ? 0
                    : ((string) $m->departure_id === (string) $d->id ? 1 : -1),
                default => 0,
            };
            $ch = $m->channel ?: 'app';
            $held[$ch] = ($held[$ch] ?? 0) + $sign * round((float) $m->amount, 2);
        }
        $lines = [];
        $desc = __('ledger.l_bus_trip_left');
        foreach ($held as $channel => $amount) {
            $amount = round($amount, 2);
            if (abs($amount) < 0.005) {
                continue;
            }
            $tags = $this->tags($d, $channel);
            $lines[] = ['account_id' => $this->mapping('tickets_held'), 'debit' => max(0, $amount), 'credit' => max(0, -$amount), 'description' => $desc] + $tags;
            $lines[] = ['account_id' => $this->mapping('ticket_sales'), 'debit' => max(0, -$amount), 'credit' => max(0, $amount), 'description' => $desc] + $tags;
        }
        if (! $lines) {
            return null;
        }
        $when = $left->gt(now()) ? now() : $left;

        return [
            'date' => $when->toDateString(),
            'description' => $desc.' · '.$d->leavesAt()->format('d/m H:i').' '.($d->destination?->city ?? ''),
            'reference' => null,
            'journal_type' => 'sales',
            'lines' => $lines,
        ];
    }

    // ---------------------------------------------------------------- E-Ride's statement

    /** E-Ride's commission for a month: owed when the statement is made (dated at the month's end). */
    public function forStatement(BusStatement $s): ?array
    {
        $total = round((float) $s->total, 2);
        if ($total <= 0) {
            return null;
        }
        $end = Carbon::parse($s->month)->endOfMonth();
        $desc = __('ledger.l_bus_commission', ['month' => Carbon::parse($s->month)->translatedFormat('F Y')]);

        return [
            'date' => ($end->gt(today()) ? today() : $end)->toDateString(),
            'description' => $desc,
            'reference' => $s->reference,
            'journal_type' => 'purchase',
            'lines' => [
                ['account_id' => $this->mapping('eride_commission'), 'debit' => $total, 'credit' => 0, 'description' => $desc],
                ['account_id' => $this->mapping('eride_payable'), 'debit' => 0, 'credit' => $total, 'description' => $desc],
            ],
        ];
    }

    /** The statement paid: from the account E-Ride is paid from, or by the head office (company bill). */
    public function forStatementPaid(BusStatement $s): ?array
    {
        $total = round((float) $s->total, 2);
        if ($total <= 0 || $s->status !== BusStatement::PAID || ! $s->paid_at) {
            return null;
        }
        $desc = __('ledger.l_bus_commission_paid', ['month' => Carbon::parse($s->month)->translatedFormat('F Y')]);
        $from = $s->company_statement_id ? $this->mapping('head_office')
            : $this->mapping('payment_method', (string) (AccountingSetting::getValue('eride_paid_from') ?: 'momo'));

        return [
            'date' => Carbon::parse($s->paid_at)->toDateString(),
            'description' => $desc,
            'reference' => $s->reference,
            'journal_type' => 'cash',
            'lines' => [
                ['account_id' => $this->mapping('eride_payable'), 'debit' => $total, 'credit' => 0, 'description' => $desc],
                ['account_id' => $from, 'debit' => 0, 'credit' => $total, 'description' => $desc],
            ],
        ];
    }

    // ---------------------------------------------------------------- helpers

    /** When a trip counts as gone: it departed (at the departure time recorded), or it was cancelled and its time has passed. */
    public function leftAt(?BusDeparture $d): ?Carbon
    {
        if (! $d) {
            return null;
        }
        if ($d->status === BusDeparture::DEPARTED) {
            return Carbon::parse($d->departed_at ?? $d->leavesAt());
        }
        if ($d->status === BusDeparture::CANCELLED && $d->leavesAt()->lte(now())) {
            return Carbon::parse($d->leavesAt());
        }

        return null;
    }

    /** Was money for this trip at $at still held for it (the trip had not left)? A move with no trip is a sale. */
    public function heldAt(?BusDeparture $d, Carbon $at): bool
    {
        if (! $d) {
            return false;
        }
        $left = $this->leftAt($d);

        return ! $left || $at->lt($left);
    }

    /** The bus cost centres of a line. */
    public function tags(?BusDeparture $d, ?string $channel): array
    {
        return array_filter([
            'departure_id' => $d ? (string) $d->id : null,
            'vehicle_id' => $d && $d->vehicle_id ? (string) $d->vehicle_id : null,
            'destination_id' => $d && $d->destination_id ? (string) $d->destination_id : null,
            'channel' => $channel,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Fapshi's share of an app payment: the payment's amount less what Fapshi says the agency
     * receives (payment-status revenue). Null when Fapshi gave no figure.
     */
    public function fee(BusMoneyMove $m): ?float
    {
        $payments = BusPayment::where('booking_id', $m->booking_id)->where('status', BusPayment::SUCCESSFUL)->orderBy('id')->get();
        $moves = BusMoneyMove::where('booking_id', $m->booking_id)->whereIn('kind', [BusMoneyMove::SALE, BusMoneyMove::EXTRA])
            ->whereIn('method', ['fapshi', 'company'])->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $i = array_search((int) $m->id, $moves, true);
        $p = $i === false ? null : ($payments[$i] ?? null);
        if (! $p) {
            return null;
        }
        $revenue = $p->raw_response['payment-status']['revenue'] ?? null;
        $amount = $p->raw_response['payment-status']['amount'] ?? $p->amount;
        if (! is_numeric($revenue) || ! is_numeric($amount)) {
            return null;
        }
        $fee = round((float) $amount - (float) $revenue, 2);

        return $fee > 0 && $fee < (float) $amount ? $fee : null;
    }

    private function departure($id): ?BusDeparture
    {
        if (! $id) {
            return null;
        }

        return $this->departures[(string) $id] ??= BusDeparture::with('destination')->find($id);
    }

    /** The account a mapping points at (Mapping settings / Cash accounts), or a clear refusal. */
    private function mapping(string $type, string $key = ''): int
    {
        $k = $type.'|'.$key;
        if (! array_key_exists($k, $this->mapped)) {
            $this->mapped[$k] = DefaultAccount::id($type, $key);
        }
        if (! $this->mapped[$k]) {
            throw new \RuntimeException(__('ledger.err_no_mapping', ['type' => __('ledger.map_'.$type), 'key' => $key]));
        }

        return (int) $this->mapped[$k];
    }
}
