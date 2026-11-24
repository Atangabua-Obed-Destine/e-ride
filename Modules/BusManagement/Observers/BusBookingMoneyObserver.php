<?php

namespace Modules\BusManagement\Observers;

use Illuminate\Support\Facades\DB;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusMoneyMove;

/**
 * Writes down every time money moves on a booking, at the moment it moves, whatever screen or
 * job moved it: one bus_money_moves row per event, with its own date (history never lost to an
 * overwritten column). It runs inside the caller's transaction, so a sale that is rolled back
 * leaves no move. The books post from these rows (LedgerSync), never from the booking columns.
 *
 * Each event is the money itself:
 *  - sale: a ticket paid (counter: cash / MTN / Orange at the counter; app or web: Fapshi, or the
 *    head office account when the company collects);
 *  - extra: money paid beyond the fare (late, twice, or for a trip that stopped), owed back;
 *  - refund_owed: part of the fare to send back; cash_refund: given back there and then at the counter;
 *  - refund_sent: a refund sent (Mobile Money);
 *  - rebook: a paid booking moved to another trip.
 */
class BusBookingMoneyObserver
{
    public function created(BusBooking $b): void
    {
        if ($b->status === BusBooking::PAID && $b->paid_at) {
            $this->sale($b);
        }
    }

    public function updated(BusBooking $b): void
    {
        $was = fn(string $k) => $b->getOriginal($k);

        if ($b->paid_at && !$was('paid_at')) {
            $this->sale($b);
        }

        $extra = round((float)$b->extra_refund - (float)$was('extra_refund'), 2);
        if ($extra > 0) {
            $this->move($b, BusMoneyMove::EXTRA, $extra, $this->onlineMethod($b));
        }

        $refund = round((float)$b->refund_amount - (float)$was('refund_amount'), 2);
        if ($refund > 0) {
            // Owed (to send later) when what is owed grew by it; otherwise given back at the counter.
            $owedBefore = $was('refund_status') === 'due' ? (float)$was('refund_owed') : 0;
            $owedGrowth = round((float)$b->refund_owed - $owedBefore, 2);
            if ($owedGrowth >= $refund - 0.004) {
                $this->move($b, BusMoneyMove::REFUND_OWED, $refund);
            } else {
                $this->move($b, BusMoneyMove::CASH_REFUND, $refund, $b->source === BusBooking::COUNTER ? ($b->payment_method ?: 'cash') : $this->onlineMethod($b),
                    auth()->id() ?? $b->staff_user_id);
            }
        }

        if ($was('refund_status') === 'due' && $b->refund_status === 'sent' && $refund <= 0) {
            $sent = (float)$was('refund_owed') ?: (float)$was('refund_amount');
            if ($sent > 0) {
                $this->move($b, BusMoneyMove::REFUND_SENT, $sent);
            }
        }

        if ($b->isDirty('departure_id') || $b->wasChanged('departure_id')) {
            $from = $was('departure_id');
            $paid = (float)$b->amount - (float)$b->refund_amount;
            if ($from && (string)$from !== (string)$b->departure_id && $b->paid_at && $paid > 0 && $b->status === BusBooking::PAID) {
                $this->move($b, BusMoneyMove::REBOOK, $paid, null, null, (string)$from);
            }
        }
    }

    protected function sale(BusBooking $b): void
    {
        $counter = $b->source === BusBooking::COUNTER;
        $this->move($b, BusMoneyMove::SALE, (float)$b->amount,
            $counter ? ($b->payment_method ?: 'cash') : $this->onlineMethod($b),
            $counter ? $b->staff_user_id : null, null, $b->paid_at);
    }

    /** App and web payments land in the branch's Fapshi, or the head office's when the company collects. */
    protected function onlineMethod(BusBooking $b): string
    {
        $agency = BusAgency::with('company')->find($b->agency_id);
        return $agency?->company && $agency->company->collectsMoney() ? 'company' : 'fapshi';
    }

    protected function move(BusBooking $b, string $kind, float $amount, ?string $method = null, ?string $staff = null, ?string $from = null, $at = null): void
    {
        if ($amount <= 0) {
            return;
        }
        // Never stops a sale or a refund: a move that cannot be written is logged loudly instead
        // (the overview's books check and LedgerSync::pending() make a gap visible).
        try {
            $this->write($b, $kind, $amount, $method, $staff, $from, $at);
        } catch (\Throwable $e) {
            report($e);
            \Illuminate\Support\Facades\Log::error('Bus accounting: a money move was not written', ['booking' => (string)$b->id, 'kind' => $kind, 'amount' => $amount, 'error' => $e->getMessage()]);
        }
    }

    protected function write(BusBooking $b, string $kind, float $amount, ?string $method, ?string $staff, ?string $from, $at): void
    {
        BusMoneyMove::create([
            'agency_id' => (string)$b->agency_id,
            'booking_id' => (string)$b->id,
            'departure_id' => $b->departure_id ? (string)$b->departure_id : null,
            'from_departure_id' => $from,
            'kind' => $kind,
            'channel' => $b->source === BusBooking::COUNTER ? 'counter' : 'online',
            'method' => $method,
            'amount' => round($amount, 2),
            'staff_user_id' => $staff ? (string)$staff : null,
            'at' => $at ?? now(),
        ]);
    }
}
